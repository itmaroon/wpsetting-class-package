<?php

namespace Itmar\WpSettingClassPackage;

if (!defined('ABSPATH')) exit;

class ItmarSecuritySettings
{
    private static $instance = null;
    private $login_slug_option = 'itmar_custom_login_slug';
    private $disable_author_archive_option = 'itmar_disable_author_archive';
    private $disable_xmlrpc_option = 'itmar_disable_xmlrpc';

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
        add_filter('redirect_canonical', [$this, 'disable_author_redirect'], 10, 2);
        add_filter('rest_endpoints', [$this, 'disable_rest_user_endpoint']);
        //XML-RPC 無効化
        add_filter('xmlrpc_enabled', [$this, 'disable_xmlrpc']);
    }

    /**
     * カスタムリライトルール追加
     */
    public function register_custom_login_rewrite()
    {
        $custom_slug = get_option($this->login_slug_option, '');

        if (!empty($custom_slug)) {
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
        $custom_login = get_query_var('itmar_custom_login');

        if ($custom_login) {
            global $user_login, $error;
            $user_login = ''; // 空で定義
            $error = ''; // エラー変数も空定義

            require_once ABSPATH . 'wp-login.php';
            exit;
        }
    }

    /**
     * wp-login.php直アクセスをブロック
     */
    public function block_default_login()
    {
        $custom_slug = get_option($this->login_slug_option, '');
        if (empty($custom_slug)) {
            return; // 設定されていない場合は通常動作
        }

        $request_uri = $_SERVER['REQUEST_URI'];

        if (strpos($request_uri, 'wp-login.php') !== false && strpos($request_uri, $custom_slug) === false) {
            wp_die(__('404 Not Found', 'wp-extra-settings'), '', array('response' => 404));
        }
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

        if (($path === 'wp-login.php' || preg_match('/wp-login\.php\?action=\w+/', $path)) &&
            (is_user_logged_in() || strpos($_SERVER['REQUEST_URI'], $custom_slug) !== false)
        ) {
            $url = str_replace('wp-login.php', $custom_slug, $url);
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

    /** 著者アーカイブ防止 - クエリ段階 */
    public function block_author_query($query_vars)
    {
        $disable_author = get_option($this->disable_author_archive_option, 1);

        if ($disable_author && isset($query_vars['author'])) {
            wp_die(__('404 Not Found', 'wp-extra-settings'), '', array('response' => 404));
        }
        return $query_vars;
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

        if ($disable_author) {
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

    /** 🔹 設定保存 */
    public function save_settings()
    {
        update_option($this->login_slug_option, sanitize_title($_POST[$this->login_slug_option] ?? ''));
        update_option($this->disable_author_archive_option, isset($_POST[$this->disable_author_archive_option]) ? 1 : 0);
        update_option($this->disable_xmlrpc_option, isset($_POST[$this->disable_xmlrpc_option]) ? 1 : 0);
    }

    /** 🔹 設定画面HTML */
    public function render_settings_section()
    {
        $login_slug = get_option($this->login_slug_option, '');
        $disable_author = get_option($this->disable_author_archive_option, 1);
        $disable_xmlrpc = get_option($this->disable_xmlrpc_option, 1);
?>
        <h2><?php _e('Security Settings', 'wp-extra-settings'); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Custom Login URL', 'wp-extra-settings'); ?></th>
                <td>
                    <input type="text" name="<?php echo esc_attr($this->login_slug_option); ?>" value="<?php echo esc_attr($login_slug); ?>" class="regular-text" />
                    <p class="description"><?php _e('Change the default login URL (wp-login.php).', 'wp-extra-settings'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Disable Author Archives & REST API User Endpoint', 'wp-extra-settings'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->disable_author_archive_option); ?>" value="1" <?php checked($disable_author, 1); ?> />
                        <?php _e('Block access to /?author= and REST API /wp/v2/users.', 'wp-extra-settings'); ?>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Disable XML-RPC', 'wp-extra-settings'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->disable_xmlrpc_option); ?>" value="1" <?php checked($disable_xmlrpc, 1); ?> />
                        <?php _e('Disable XML-RPC endpoint (used for pingbacks, remote publishing, etc).', 'wp-extra-settings'); ?>
                    </label>
                </td>
            </tr>
        </table>
<?php
    }
}
