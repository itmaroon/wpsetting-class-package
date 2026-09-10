<?php

namespace Itmar\WpsettingClassPackage;

if (!defined('ABSPATH')) exit;

class ItmarSecuritySettings
{
    private static $instance = null;
    private $login_slug_option = 'itmar_custom_login_slug';
    private $redirect_option = 'itmar_redirect_to_subdir';
    private $disable_author_archive_option = 'itmar_disable_author_archive';
    private $disable_xmlrpc_option = 'itmar_disable_xmlrpc';
    /** カスタムログインURL経由で wp-login.php を読み込んだか。 */
    private $via_custom_login = false;
    /** 著者アーカイブ遮断により 404 を返すか。 */
    private $force_404 = false;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // カスタムログインURLにリライトルール追加
        add_action('init', [$this, 'register_custom_login_rewrite']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_custom_login']);

        // wp-login.php直アクセス防止
        add_action('login_init', [$this, 'block_default_login']);
        // ログインURLを置換
        add_filter('site_url', [$this, 'replace_login_url'], 10, 4);
        add_filter('wp_redirect', [$this, 'redirect_login_url'], 10, 2);

        // ユーザー名漏洩防止
        add_filter('request', [$this, 'block_author_query']);
        add_action('template_redirect', [$this, 'apply_forced_404'], 1);
        add_filter('redirect_canonical', [$this, 'disable_author_redirect'], 10, 2);
        add_filter('rest_endpoints', [$this, 'disable_rest_user_endpoint']);
        //XML-RPC 無効化
        add_filter('xmlrpc_enabled', [$this, 'disable_xmlrpc']);

        // スラッグ衝突の通知
        add_action('admin_notices', [$this, 'admin_notice_login_slug_error']);
    }

    /**
     * カスタムリライトルール追加
     */
    public function register_custom_login_rewrite()
    {
        $custom_slug = get_option($this->login_slug_option, '');

        if (!empty($custom_slug)) {
            // WordPressの内部処理に渡す形式で登録
            add_rewrite_rule("^{$custom_slug}/?$", 'index.php?itmar_custom_login=1', 'top');
        }
    }

    /**
     * カスタムクエリ変数登録
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'itmar_custom_login';
        return $vars;
    }

    /**
     * カスタムURLアクセス時、wp-login.phpを読み込む
     */
    public function handle_custom_login()
    {

        if (get_query_var('itmar_custom_login')) {
            global $user_login, $error;
            $user_login = ''; // 空で定義
            $error = ''; // エラー変数も空定義
            $this->via_custom_login = true; // block_default_login に経路を伝える
            require_once ABSPATH . 'wp-login.php';
            exit;
        }
    }

    /**
     * wp-login.php直アクセスをブロック
     *
     * カスタムURL経由かどうかはフラグで判定する。REQUEST_URI の文字列照合だと、
     * スラッグに "login" を含めた瞬間に /wp-login.php 自身が一致してしまい、
     * ブロックが丸ごと無効化される。
     *
     * GET だけでなく POST も塞ぐ。総当たり攻撃は wp-login.php へ直接 POST して
     * くるため、GET だけ塞いでもログインURLを変えた意味がない。
     */
    public function block_default_login()
    {
        $custom_slug = get_option($this->login_slug_option, '');
        if (empty($custom_slug)) {
            return; // 設定されていない場合は通常動作
        }

        // カスタムURL経由の読み込みは当然通す
        if ($this->via_custom_login) {
            return;
        }

        // 認証以外の用途はフロント機能なので通す。
        // postpass はパスワード保護記事のフォーム、logout はログアウト処理。
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
        if (in_array($action, array('postpass', 'logout'), true)) {
            return;
        }

        wp_die(esc_html__('404 Not Found', 'wpsetting-class-package'), '', array('response' => 404));
    }

    /**
     * ログインURLを置換
     */
    public function replace_login_url($url, $path, $orig_scheme, $blog_id)
    {
        $custom_slug = get_option($this->login_slug_option, '');
        if (empty($custom_slug)) {
            return $url;
        }

        if ($path === 'wp-login.php' || preg_match('/wp-login\.php\?action=\w+/', $path)) {
            $use_home = get_option($this->redirect_option, 0) !== 0;

            // クエリ部分を一時保存
            $parsed = wp_parse_url($url);
            $query = isset($parsed['query']) ? $parsed['query'] : '';

            // ベースURL作成
            $base = $use_home ? home_url("/{$custom_slug}") : str_replace('wp-login.php', $custom_slug, $url);

            // クエリパラメータを再付与（あれば）
            if ($query) {
                $base = remove_query_arg(null, $base); // クエリ除去（万一含まれていた場合）
                $base .= '?' . $query;
            }

            return $base;
        }

        return $url;
    }



    /**
     * ログアウト後リダイレクト先も置換
     */
    public function redirect_login_url($location, $status)
    {
        $custom_slug = get_option($this->login_slug_option, '');
        if (empty($custom_slug)) {
            return $location;
        }

        if (is_user_logged_in() && strpos($_SERVER['REQUEST_URI'], $custom_slug) !== false) {
            $location = str_replace('wp-login.php', $custom_slug, $location);
        }
        return $location;
    }



    /**
     * 著者アーカイブ防止 - クエリ段階
     *
     * ここで wp_die() すると素のエラー画面になり、テーマの 404 テンプレートを
     * 経由しない。著者指定を落としたうえでフラグを立て、通常の 404 として扱う。
     */
    public function block_author_query($query_vars)
    {
        $disable_author = get_option($this->disable_author_archive_option, 1);

        if ($disable_author && isset($query_vars['author'])) {
            unset($query_vars['author']);
            $this->force_404 = true;
        }
        return $query_vars;
    }

    /** テーマの 404 テンプレートで応答する */
    public function apply_forced_404()
    {
        if (!$this->force_404) {
            return;
        }
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }

    /** 著者アーカイブ防止 - リダイレクト阻止 */
    public function disable_author_redirect($redirect_url, $requested_url)
    {
        $disable_author = get_option($this->disable_author_archive_option, 1);

        if ($disable_author && is_author()) {
            return false;
        }
        return $redirect_url;
    }

    /** REST API 経由のユーザー情報取得防止 */
    public function disable_rest_user_endpoint($endpoints)
    {
        $disable_author = get_option($this->disable_author_archive_option, 1);

        // 塞ぐのは未ログインの列挙だけ。ログイン中も消すと、ブロックエディタの
        // 投稿者パネルなど管理側の正当な利用まで壊れる。
        if ($disable_author && !is_user_logged_in()) {
            unset($endpoints['/wp/v2/users']);
            unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        }
        return $endpoints;
    }

    //XML-RPC 無効化
    public function disable_xmlrpc($enabled)
    {
        $disable_xmlrpc = get_option($this->disable_xmlrpc_option, 1);

        if ($disable_xmlrpc) {
            return false;
        }
        return $enabled;
    }

    /**
     * ログインスラッグが既存のURLと衝突しないか調べる。
     *
     * add_rewrite_rule を 'top' で登録するため、衝突したまま保存すると
     * 該当ページが表示できなくなる。保存前に弾く。
     *
     * @return string|null 衝突理由。問題なければ null。
     */
    private function find_login_slug_conflict($slug)
    {
        $reserved = array(
            'wp-admin',
            'wp-login',
            'wp-content',
            'wp-includes',
            'wp-json',
            'feed',
            'rss',
            'rss2',
            'atom',
            'embed',
            'trackback',
            'page',
            'comments',
            'search',
            'author',
        );
        if (in_array($slug, $reserved, true)) {
            /* translators: %s: requested login slug. */
            return sprintf(__('"%s" is reserved by WordPress.', 'wpsetting-class-package'), $slug);
        }

        $existing = get_page_by_path($slug, OBJECT, get_post_types(array('public' => true)));
        if ($existing) {
            /* translators: 1: requested login slug, 2: title of the conflicting content. */
            return sprintf(__('"%1$s" is already used by the content "%2$s".', 'wpsetting-class-package'), $slug, $existing->post_title);
        }

        foreach (get_post_types(array('public' => true), 'objects') as $post_type) {
            $rewrite = is_array($post_type->rewrite) ? ($post_type->rewrite['slug'] ?? '') : '';
            $archive = is_string($post_type->has_archive) ? $post_type->has_archive : '';
            if ($slug === $rewrite || $slug === $archive) {
                /* translators: 1: requested login slug, 2: post type name. */
                return sprintf(__('"%1$s" is already used by the post type "%2$s".', 'wpsetting-class-package'), $slug, $post_type->name);
            }
        }

        foreach (get_taxonomies(array('public' => true), 'objects') as $taxonomy) {
            $rewrite = is_array($taxonomy->rewrite) ? ($taxonomy->rewrite['slug'] ?? '') : '';
            if ($slug === $rewrite) {
                /* translators: 1: requested login slug, 2: taxonomy name. */
                return sprintf(__('"%1$s" is already used by the taxonomy "%2$s".', 'wpsetting-class-package'), $slug, $taxonomy->name);
            }
        }

        return null;
    }

    /** 🔹 設定保存 */
    public function save_settings()
    {
        $requested_slug = sanitize_title(wp_unslash($_POST[$this->login_slug_option] ?? ''));
        $current_slug   = get_option($this->login_slug_option, '');

        if ('' === $requested_slug) {
            // 空にするのはいつでも許可（ロックアウトからの復帰手段）
            update_option($this->login_slug_option, '');
            delete_option('itmar_login_slug_error');
        } elseif ($requested_slug !== $current_slug) {
            $conflict = $this->find_login_slug_conflict($requested_slug);
            if (null === $conflict) {
                update_option($this->login_slug_option, $requested_slug);
                delete_option('itmar_login_slug_error');
            } else {
                // 衝突しているので採用しない。元の設定を維持する。
                update_option('itmar_login_slug_error', $conflict);
            }
        }

        update_option($this->disable_author_archive_option, isset($_POST[$this->disable_author_archive_option]) ? 1 : 0);
        update_option($this->disable_xmlrpc_option, isset($_POST[$this->disable_xmlrpc_option]) ? 1 : 0);

        // フラッシュしてルールを反映
        flush_rewrite_rules();
    }

    /** スラッグ衝突を管理画面に通知する */
    public function admin_notice_login_slug_error()
    {
        $message = get_option('itmar_login_slug_error');
        if (!$message) {
            return;
        }
?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($message); ?></p>
            <p><?php esc_html_e('The login URL was not changed. Choose a different slug.', 'wpsetting-class-package'); ?></p>
        </div>
<?php
        delete_option('itmar_login_slug_error');
    }

    /** 🔹 設定画面HTML */
    public function render_settings_section()
    {
        $login_slug = get_option($this->login_slug_option, '');
        $disable_author = get_option($this->disable_author_archive_option, 1);
        $disable_xmlrpc = get_option($this->disable_xmlrpc_option, 1);
?>
        <h2><?php esc_html_e('Security Settings', 'wpsetting-class-package'); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Custom Login URL', 'wpsetting-class-package'); ?></th>
                <td>
                    <input type="text" name="<?php echo esc_attr($this->login_slug_option); ?>" value="<?php echo esc_attr($login_slug); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Change the default login URL (wp-login.php).', 'wpsetting-class-package'); ?></p>
                    <?php if ('' !== $login_slug) : ?>
                        <p class="description">
                            <strong><?php esc_html_e('Current login URL:', 'wpsetting-class-package'); ?></strong>
                            <code><?php echo esc_html(home_url('/' . $login_slug . '/')); ?></code>
                        </p>
                        <p class="description">
                            <?php esc_html_e('Bookmark this URL. While it is set, wp-login.php returns 404 for both GET and POST. Clearing this field restores the default login URL.', 'wpsetting-class-package'); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Disable Author Archives & REST API User Endpoint', 'wpsetting-class-package'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->disable_author_archive_option); ?>" value="1" <?php checked($disable_author, 1); ?> />
                        <?php esc_html_e('Block access to /?author= and REST API /wp/v2/users.', 'wpsetting-class-package'); ?>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Disable XML-RPC', 'wpsetting-class-package'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->disable_xmlrpc_option); ?>" value="1" <?php checked($disable_xmlrpc, 1); ?> />
                        <?php esc_html_e('Disable XML-RPC endpoint (used for pingbacks, remote publishing, etc).', 'wpsetting-class-package'); ?>
                    </label>
                </td>
            </tr>
        </table>
<?php
    }
}
