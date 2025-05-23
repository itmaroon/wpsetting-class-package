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
    private $current_revision_limit = null;
    private $enabled_option = 'itmar_revision_enabled'; // オプションキー
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
     * @param int $num デフォルトのリビジョン数
     * @param WP_Post|null $post 投稿オブジェクト
     * @return int カスタマイズされたリビジョン数
     */
    public function customize_revisions_to_keep($num, $post)
    {
        if (!$post) return $num; // 安全のため null チェック

        // 投稿ごとにカスタムフィールドの値を取得
        $custom_revisions = get_post_meta($post->ID, 'custom_revisions_count', true);

        // 値が設定されている場合、その値をリビジョン数として適用
        if (is_numeric($custom_revisions) && $custom_revisions >= 0) {
            return (int) $custom_revisions;
        }
        // **プロパティに保存**
        $this->current_revision_limit = $num;

        return $num; // デフォルトのリビジョン数
    }

    /**
     * リビジョン設定用のメタボックスを追加
     */
    public function add_revisions_meta_box()
    {
        add_meta_box(
            'custom_revisions_meta',
            esc_html__("Revision Settings", "wpsetting-class-package"),
            array($this, 'render_revisions_meta_box'),
            'post',
            'side',
            'high'
        );
    }

    /**
     * メタボックスの内容をレンダリング
     *
     * @param WP_Post $post 現在の投稿オブジェクト
     */
    public function render_revisions_meta_box($post)
    {
        // ノンスフィールドを追加してセキュリティを強化
        wp_nonce_field('custom_revisions_nonce', 'custom_revisions_nonce');

        // 現在の値を取得

        $custom_revisions = get_post_meta($post->ID, 'custom_revisions_count', true);
        $revision_limit = is_numeric($custom_revisions) ? $custom_revisions : $this->current_revision_limit;
        $revision_limit = $revision_limit == -1 ? "" : $revision_limit;
        // 入力フィールドを表示
        echo '<label for="custom_revisions_count">' . esc_html__("Maximum number of revisions for this post", "wpsetting-class-package") . ' </label>';
        echo '<input type="number" id="custom_revisions_count" name="custom_revisions_count" value="' . esc_attr($revision_limit) . '" min="0" style="width:100%" />';
        echo '<p class="description">' . esc_html__("If left blank, there is no limit.", "wpsetting-class-package") . '</p>';
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

        // カスタムフィールドを更新
        if (isset($_POST['custom_revisions_count'])) {
            update_post_meta($post_id, 'custom_revisions_count', intval($_POST['custom_revisions_count']));
        }
    }

    /** 設定画面の保存 */
    public function save_settings()
    {
        $enabled = isset($_POST[$this->enabled_option]) ? 1 : 0;
        update_option($this->enabled_option, $enabled);
    }

    /** 設定画面の表示 */
    public function render_settings_section()
    {
        $enabled = get_option($this->enabled_option, 0);
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
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Default Revision Setting', 'wpsetting-class-package'); ?></th>
                <td>
                    <p class="description">
                        <?php
                        // 現在の WordPress デフォルト設定を表示
                        $revisions_setting = $this->get_default_limit();

                        if ($revisions_setting === true) {
                            esc_html_e('Not set (Unlimited)', 'wpsetting-class-package');
                        } elseif ($revisions_setting === false || $revisions_setting === 0) {
                            esc_html_e('Do not save', 'wpsetting-class-package');
                        } else {
                            printf(
                                esc_html__('Save up to %d revisions', 'wpsetting-class-package'),
                                esc_html($revisions_setting)
                            );
                        }
                        ?>
                    </p>
                </td>
            </tr>
        </table>
<?php
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
}
