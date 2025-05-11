<?php

namespace Itmar\WpsettingClassPackage;

if (!defined('ABSPATH')) exit;

class ItmarModifyPost
{
    private static $instance = null;

    private $post_label_option = 'itmar_post_label';
    private $has_archive_option = 'itmar_post_has_archive';
    private $archive_slug_option = 'itmar_post_archive_slug';
    private $supports_option = 'itmar_post_supports';

    private function __construct()
    {
        // 管理画面側でラベルとメニュー変更
        add_action('init', [$this, 'change_post_labels']);
        add_action('admin_menu', [$this, 'change_post_menu'], 99);
        add_action('admin_head', [$this, 'change_post_submenu']);

        add_action('after_setup_theme', [$this, 'register_theme_supports']); // アイキャッチなど
        add_action('init', [$this, 'modify_post_supports'], 20);              // 投稿タイプ supports 変更
        add_filter('register_post_type_args', [$this, 'filter_post_type_args'], 10, 2); // アーカイブスラッグ

        add_action('init', [$this, 'maybe_flush_rewrite_rules'], 99); //設定更新後のフラッシュ
        add_action('init', [$this, 'maybe_block_trackback'], 1); //トラックバックの停止
    }

    /** シングルトン取得 */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    //データ更新後のflush処理
    public function maybe_flush_rewrite_rules()
    {
        if (get_option('itmar_post_needs_flush')) {
            flush_rewrite_rules();
            update_option('itmar_post_needs_flush', 0);
            error_log('[ItmarModifyPost] flush_rewrite_rules() executed');
        }
    }

    /**
     * テーマ機能の登録（アイキャッチなど）
     */
    public function register_theme_supports()
    {
        $saved_supports = get_option($this->supports_option);
        if (!is_array($saved_supports)) return;

        if (!empty($saved_supports['thumbnail'])) {
            add_theme_support('post-thumbnails');
        }

        if (!empty($saved_supports['post-formats'])) {
            add_theme_support('post-formats', ['aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat']);
        }
    }

    /**
     * 投稿タイプ 'post' の supports をオプション値に基づいて変更
     */
    public function modify_post_supports()
    {
        $support_options = [
            'title',
            'editor',
            'author',
            'excerpt',
            'trackbacks',
            'custom-fields',
            'comments',
            'revisions',
            'post-formats',
            'thumbnail'
        ];

        $saved_supports = get_option($this->supports_option);
        if (!is_array($saved_supports)) return;

        // 一旦すべての supports を削除
        foreach ($support_options as $support) {
            remove_post_type_support('post', $support);
        }

        // オプションに基づいて追加
        foreach ($saved_supports as $key => $enabled) {
            if ($enabled) {
                add_post_type_support('post', $key);
            }
        }
    }


    /**
     * 投稿タイプ 'post' のアーカイブスラッグや rewrite を変更
     */
    public function filter_post_type_args($args, $post_type)
    {
        if ('post' !== $post_type) return $args;

        $has_archive = get_option($this->has_archive_option, 0);
        if ($has_archive) {
            $slug = get_option($this->archive_slug_option, 'archive');
            $args['has_archive'] = $slug;
            $args['rewrite'] = [
                'slug' => $slug,
                'with_front' => false,
            ];
        }

        return $args;
    }

    /** 投稿ラベル変更 */

    public function change_post_labels()
    {
        global $wp_post_types;

        $custom_label = get_option($this->post_label_option, '投稿');

        $labels = &$wp_post_types['post']->labels;
        $labels->name = $custom_label;
        $labels->singular_name = $custom_label;
        $labels->menu_name = $custom_label;
        $labels->name_admin_bar = $custom_label;
        $labels->add_new = '新規' . $custom_label;
        $labels->add_new_item = '新規' . $custom_label . 'を追加';
        $labels->edit_item = $custom_label . 'を編集';
        $labels->new_item = '新規' . $custom_label;
        $labels->view_item = $custom_label . 'を表示';
        $labels->search_items = $custom_label . 'を検索';
        $labels->not_found = $custom_label . 'が見つかりませんでした';
        $labels->not_found_in_trash = 'ゴミ箱内に' . $custom_label . 'が見つかりませんでした';
    }

    /** 管理画面のメインメニュー表示を変更 */
    public function change_post_menu()
    {
        global $menu;

        $custom_label = get_option($this->post_label_option, '投稿');

        foreach ($menu as $key => $item) {
            if (isset($item[2]) && $item[2] === 'edit.php') {
                $menu[$key][0] = $custom_label;
            }
        }
    }

    /** 管理画面のサブメニュー（投稿 > 投稿一覧、新規追加など）の表示変更 */
    public function change_post_submenu()
    {
        global $submenu;

        $custom_label = get_option($this->post_label_option, '投稿');

        if (isset($submenu['edit.php'])) {
            foreach ($submenu['edit.php'] as $key => $item) {
                if (isset($item[0])) {
                    // 投稿一覧
                    if (strpos($item[0], '投稿一覧') !== false) {
                        $submenu['edit.php'][$key][0] = $custom_label . '一覧';
                    }
                    // 新規追加
                    if (strpos($item[0], '新規追加') !== false) {
                        $submenu['edit.php'][$key][0] = '新規' . $custom_label;
                    }
                }
            }
        }
    }

    //トラックバックの停止
    public function maybe_block_trackback()
    {
        $saved_supports = get_option($this->supports_option);
        if (empty($saved_supports['trackbacks'])) {
            if (strpos($_SERVER['REQUEST_URI'], 'wp-trackback.php') !== false) {
                wp_die('Trackbacks are disabled.', 'Trackbacks Disabled', ['response' => 403]);
            }
        }
    }


    /** 設定保存処理 */
    public function save_settings()
    {
        update_option($this->post_label_option, sanitize_text_field($_POST['itmar_post_label'] ?? ''));
        update_option($this->has_archive_option, isset($_POST['itmar_post_has_archive']) ? 1 : 0);
        update_option($this->archive_slug_option, sanitize_title($_POST['itmar_post_archive_slug'] ?? ''));
        update_option($this->supports_option, $_POST['itmar_post_supports'] ?? []);

        // 実際の flush は次回リクエストで実行
        update_option('itmar_post_needs_flush', 1);
    }

    /** 設定画面HTML出力 */
    public function render_settings_section()
    {
        $post_label = get_option($this->post_label_option, 'Post');
        $has_archive = get_option($this->has_archive_option, 0);
        $archive_slug = get_option($this->archive_slug_option, 'archive');
        $saved_supports = get_option($this->supports_option);

        $support_options = [
            'title'           => __('Title', 'wpsetting-class-package'),
            'editor'          => __('Editor', 'wpsetting-class-package'),
            'author'          => __('Author', 'wpsetting-class-package'),
            'excerpt'         => __('Excerpt', 'wpsetting-class-package'),
            'trackbacks'      => __('Trackbacks(Deprecated)', 'wpsetting-class-package'),
            'custom-fields'   => __('Custom Fields', 'wpsetting-class-package'),
            'comments'        => __('Comments', 'wpsetting-class-package'),
            'revisions'       => __('Revisions', 'wpsetting-class-package'),
            'post-formats'    => __('Post Formats(Deprecated)', 'wpsetting-class-package'),
            'thumbnail'       => __('Featured Image', 'wpsetting-class-package'),
        ];

        // レンダリング用の supports 配列を準備
        $supports = [];

        if ($saved_supports) {
            // 保存済みがあればそちらを優先
            $supports = $saved_supports;
        } else {
            // なければ現行状態
            $current_supports = get_all_post_type_supports('post');
            foreach ($support_options as $key => $label) {
                $supports[$key] = isset($current_supports[$key]) ? 1 : 0;
            }
        }
?>
        <h2><?php echo __("Modify Post Menu Settings", "wpsetting-class-package"); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php echo __("Post Label Name", "wpsetting-class-package"); ?></th>
                <td><input type="text" name="itmar_post_label" value="<?php echo esc_attr($post_label); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><?php echo __("Enable Archive Page", "wpsetting-class-package"); ?></th>
                <td>
                    <label><input type="checkbox" name="itmar_post_has_archive" value="1" <?php checked(1, $has_archive); ?> /><?php echo __("Enable archive", "wpsetting-class-package"); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __("Archive Slug", "wpsetting-class-package"); ?></th>
                <td><input type="text" name="itmar_post_archive_slug" value="<?php echo esc_attr($archive_slug); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><?php echo __("Post Supports", "wpsetting-class-package"); ?></th>
                <td>
                    <div class="post-supports-wrapper">
                        <?php foreach ($support_options as $key => $label): ?>
                            <label>
                                <input type="checkbox" name="itmar_post_supports[<?php echo esc_attr($key); ?>]" value="1" <?php checked(1, $supports[$key] ?? 0); ?> />
                                <?php echo $label; ?>
                            </label><br>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
        </table>
<?php
    }
}
