<?php

namespace Itmar\WpSettingClassPackage;

//リビジョンの制御

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

class ItmarRevisionClass
{
    // 唯一のインスタンスを保持する静的プロパティ
    private static $instance = null;

    private $current_revision_limit = null;

    /**
     * コンストラクタをプライベートにして外部からのインスタンス化を防ぐ
     */
    private function __construct()
    {
        // リビジョン数をカスタマイズするフィルター
        add_filter('wp_revisions_to_keep', array($this, 'customize_revisions_to_keep'), 10, 2);

        // メタボックスの追加
        add_action('add_meta_boxes', array($this, 'add_revisions_meta_box'));

        // 投稿保存時の処理
        add_action('save_post', array($this, 'save_revisions_meta'));
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
            'リビジョン設定',
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
        echo '<label for="custom_revisions_count">この投稿のリビジョン最大数: </label>';
        echo '<input type="number" id="custom_revisions_count" name="custom_revisions_count" value="' . esc_attr($revision_limit) . '" min="0" style="width:100%" />';
        echo '<p class="description">空欄の場合は無制限です。</p>';
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
}
