<?php

namespace Itmar\WpsettingClassPackage;

//リビジョンの制御

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

class ItmarRevisionClass
{
    // 唯一のインスタンスを保持する静的プロパティ
    private static $instance = null;

    //プライベート変数
    private $enabled_option = 'itmar_revision_enabled'; // オプションキー
    private $default_option = 'itmar_revision_default'; // サイト全体の既定リビジョン数
    private $meta_key = 'custom_revisions_count';
    private $enabled = false; // フラグ保持

    /**
     * コンストラクタをプライベートにして外部からのインスタンス化を防ぐ
     */
    private function __construct()
    {
        // オプションを確認
        $this->enabled = get_option($this->enabled_option, 0);
        // リビジョン制御有効時のみフック
        if ($this->enabled) {
            // リビジョン数をカスタマイズするフィルター
            add_filter('wp_revisions_to_keep', array($this, 'customize_revisions_to_keep'), 10, 2);

            // メタボックスの追加
            add_action('add_meta_boxes', array($this, 'add_revisions_meta_box'));

            // 投稿保存時の処理
            add_action('save_post', array($this, 'save_revisions_meta'));
        }
    }
    // クローンを禁止
    public function __clone() {}

    // シリアライゼーションを禁止
    public function __wakeup() {}

    // インスタンスを取得するための静的メソッド
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * リビジョン数をカスタマイズする
     *
     * 優先順位は 投稿ごとの指定 > サイト全体の既定 > WordPress の既定。
     *
     * @param int $num デフォルトのリビジョン数
     * @param \WP_Post|null $post 投稿オブジェクト
     * @return int カスタマイズされたリビジョン数
     */
    public function customize_revisions_to_keep($num, $post)
    {
        if (!$post) return $num; // 安全のため null チェック

        // 投稿ごとの指定が最優先
        $custom_revisions = get_post_meta($post->ID, $this->meta_key, true);
        if (is_numeric($custom_revisions) && $custom_revisions >= 0) {
            return (int) $custom_revisions;
        }

        // 次にサイト全体の既定
        $site_default = $this->get_site_default();
        if (null !== $site_default) {
            return $site_default;
        }

        return $num; // WordPress の既定
    }

    /**
     * サイト全体の既定リビジョン数
     *
     * @return int|null 未設定なら null（＝WordPress の既定に従う）
     */
    private function get_site_default()
    {
        $value = get_option($this->default_option, '');
        if ('' === $value || null === $value) {
            return null;
        }
        return max(0, (int) $value);
    }

    /** WPのデフォルトリビジョン設定を取得 */
    private function get_default_limit()
    {
        if (defined('WP_POST_REVISIONS')) {
            $revisions_setting = constant('WP_POST_REVISIONS');
        } else {
            $revisions_setting = true;
        }
        return $revisions_setting;
    }

    /** いま実際に適用される既定値を人が読める形で返す */
    private function describe_effective_default()
    {
        $site_default = $this->get_site_default();
        if (null !== $site_default) {
            return 0 === $site_default
                ? esc_html__('Do not save', 'wpsetting-class-package')
                /* translators: %d: number of revisions to keep. */
                : sprintf(esc_html__('Save up to %d revisions', 'wpsetting-class-package'), $site_default);
        }

        $wp_default = $this->get_default_limit();
        if ($wp_default === true) {
            return esc_html__('Not set (Unlimited)', 'wpsetting-class-package');
        }
        if ($wp_default === false || $wp_default === 0) {
            return esc_html__('Do not save', 'wpsetting-class-package');
        }
        /* translators: %d: number of revisions to keep. */
        return sprintf(esc_html__('Save up to %d revisions', 'wpsetting-class-package'), (int) $wp_default);
    }

    /**
     * リビジョン設定用のメタボックスを追加
     *
     * リビジョンが溜まるのは投稿だけではないため、revisions をサポートする
     * 投稿タイプに出す。ただし wp_block（パターン）や wp_navigation のような
     * 非公開の内部投稿タイプは対象外にする。
     */
    public function add_revisions_meta_box()
    {
        foreach (get_post_types(array('public' => true, 'show_ui' => true), 'names') as $post_type) {
            if (!post_type_supports($post_type, 'revisions')) {
                continue;
            }
            add_meta_box(
                'custom_revisions_meta',
                esc_html__("Revision Settings", "wpsetting-class-package"),
                array($this, 'render_revisions_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * メタボックスの内容をレンダリング
     *
     * @param \WP_Post $post 現在の投稿オブジェクト
     */
    public function render_revisions_meta_box($post)
    {
        // ノンスフィールドを追加してセキュリティを強化
        wp_nonce_field('custom_revisions_nonce', 'custom_revisions_nonce');

        // 現在の値を取得。未設定なら空欄にして既定に従わせる。
        $custom_revisions = get_post_meta($post->ID, $this->meta_key, true);
        $field_value = is_numeric($custom_revisions) ? (string) (int) $custom_revisions : '';

        echo '<label for="custom_revisions_count">' . esc_html__("Maximum number of revisions for this post", "wpsetting-class-package") . ' </label>';
        echo '<input type="number" id="custom_revisions_count" name="custom_revisions_count" value="' . esc_attr($field_value) . '" min="0" style="width:100%" />';
        echo '<p class="description">' . esc_html__("Leave blank to follow the default below. Enter 0 to stop saving revisions for this post.", "wpsetting-class-package") . '</p>';
        echo '<p class="description"><strong>' . esc_html__("Current default:", "wpsetting-class-package") . '</strong> ' . $this->describe_effective_default() . '</p>';
    }

    /**
     * メタデータを保存
     *
     * @param int $post_id 投稿ID
     */
    public function save_revisions_meta($post_id)
    {
        // 自動保存時は処理しない
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // ノンスを確認
        if (!isset($_POST['custom_revisions_nonce']) || !wp_verify_nonce($_POST['custom_revisions_nonce'], 'custom_revisions_nonce')) {
            return;
        }

        // 権限を確認
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['custom_revisions_count'])) {
            return;
        }

        // 空欄は「既定に従う」。intval('') は 0 になり、リビジョンを
        // 完全に止めてしまうため、メタごと削除する。
        $raw = trim((string) wp_unslash($_POST['custom_revisions_count']));
        if ('' === $raw) {
            delete_post_meta($post_id, $this->meta_key);
            return;
        }

        update_post_meta($post_id, $this->meta_key, max(0, (int) $raw));
    }

    /** 設定画面の保存 */
    public function save_settings()
    {
        $enabled = isset($_POST[$this->enabled_option]) ? 1 : 0;
        update_option($this->enabled_option, $enabled);

        $raw = isset($_POST[$this->default_option]) ? trim((string) wp_unslash($_POST[$this->default_option])) : '';
        update_option($this->default_option, ('' === $raw) ? '' : (string) max(0, (int) $raw));
    }

    /** 設定画面の表示 */
    public function render_settings_section()
    {
        $enabled      = get_option($this->enabled_option, 0);
        $site_default = get_option($this->default_option, '');
?>
        <h2><?php esc_html_e('Revision Control Settings', 'wpsetting-class-package'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable per-post revision limit control', 'wpsetting-class-package'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($this->enabled_option); ?>" value="1" <?php checked($enabled, 1); ?> />
                        <?php esc_html_e('Enable individual post revision limit setting.', 'wpsetting-class-package'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('The number for each post is set in the "Revision Settings" box in the sidebar of the post edit screen.', 'wpsetting-class-package'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Site-wide Default Revisions', 'wpsetting-class-package'); ?></th>
                <td>
                    <input type="number" name="<?php echo esc_attr($this->default_option); ?>" value="<?php echo esc_attr($site_default); ?>" min="0" class="small-text" />
                    <p class="description">
                        <?php esc_html_e('Applied to posts that have no individual setting. Leave blank to follow the WordPress default. Enter 0 to stop saving revisions.', 'wpsetting-class-package'); ?>
                    </p>
                </td>
            </tr>
        </table>
<?php
    }
}
