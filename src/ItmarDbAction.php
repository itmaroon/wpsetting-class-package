<?php

namespace Itmar\WpsettingClassPackage;

if (!defined('ABSPATH')) exit;

class ItmarDbAction
{
    //インポートのサーバーサイド実行処理
    public function json_import_data($groupArr, $uploaded_medias, $import_mode)
    {
        //エラーログ
        $error_logs = [];
        //実行結果
        $result_arr = [];

        //親IDの初期化
        $parent_id = 0;

        //リビジョンの生成を止めてから挿入
        add_filter('wp_save_post_revision_check_for_changes', '__return_false');
        add_filter('wp_revisions_to_keep', '__return_zero', 10, 2);

        foreach ($groupArr as $entry) {
            //JSONのデコード結果から情報を取り出し
            $post_id = isset($entry['ID']) ? intval($entry['ID']) : 0;
            $post_title = isset($entry['title']) ? esc_html($entry['title']) : '';
            $post_type = isset($entry['post_type']) ? esc_html($entry['post_type']) : '';
            $post_status = isset($entry['post_status']) ? esc_html($entry['post_status']) : '';
            $post_date = isset($entry['date']) ? $entry['date'] : current_time('mysql');

            $post_author = isset($entry['author']) ? get_user_by('login', $entry['author'])->ID ?? 1 : 1;
            $post_name = isset($entry['post_name']) ? esc_html($entry['post_name']) : '';
            $thumbnail_path = $entry['thumbnail_path'] ?? null;
            //投稿日付が将来日付でpublishの時はステータスを変更
            $entry_time = strtotime($post_date);
            $now = current_time('timestamp'); // WordPressの現在時刻（タイムゾーン考慮）
            if ($entry_time !== false && $entry_time > $now && $post_status = 'publish') {
                $post_status = 'future';
            }

            // 投稿タイプが登録されていない場合はスキップ
            if (!post_type_exists($post_type)) {
                $error_logs[] = esc_html__("Skip (unregistered post type)", "wpsetting-class-package");
                $result_arr = [
                    'result' => 'error',
                    'id' => null,
                    'message' => esc_html__("Skip (unregistered post type)", "wpsetting-class-package"),
                    'log' => $error_logs
                ];
                return $result_arr;
            }

            //ID上書きのリビジョンデータはスキップ
            if ($post_id > 0 && get_post($post_id) && $import_mode === "update" && $post_type === "revision") {
                $error_logs[] = esc_html__("Skip (Existing revison data available)", "wpsetting-class-package");
                continue;
            }

            //投稿本文内のメディアファイルのパスを配列にする
            $post_content = $entry['content'] ?? '';
            $content_mediaURLs = [];
            if (isset($post_content)) {
                $matches = [];
                preg_match_all('/exported_media\/(.+?\.[a-zA-Z0-9]+)/u', $post_content, $matches);
                $content_mediaURLs = $matches[0] ?? []; // `matches[0]` にフルパス名が格納される
            }

            // 投稿データ

            $entry_time = strtotime($post_date);
            if ($entry_time !== false) {
                $formatted_date = date('Y-m-d H:i:s', $entry_time);
                $formatted_gmt = get_gmt_from_date($formatted_date); // WordPressのGMT変換

                $post_data = array(
                    'post_title'   => $post_title,
                    'post_content' => wp_slash($post_content),
                    'post_excerpt' => $entry['excerpt'] ?? '',
                    'post_status'  => $post_status,
                    'post_type'    => $post_type,
                    'post_date'     => $formatted_date,
                    'post_date_gmt' => $formatted_gmt,
                    'post_author'   => $post_author,
                    'edit_date'      => true, // ← これがポイント！
                );
            } else {
                $post_data = array(
                    'post_title'   => $post_title,
                    'post_content' => wp_slash($post_content),
                    'post_excerpt' => $entry['excerpt'] ?? '',
                    'post_status'  => $post_status,
                    'post_type'    => $post_type,
                    'post_author'   => $post_author,
                );
            }
            //revisionレコードの場合
            if ($parent_id != 0 && $post_type === "revision") {
                $post_data["post_parent"] = $parent_id;
                $post_data['post_name'] = "{$parent_id}-revision-v1"; // 一意なリビジョン名
            } else {
                $post_data['post_name'] = $post_name;
            }

            // インポートモードがupdateで、既存投稿があり、ポストタイプが一致すれば上書き、なければ新規追加
            $post_check = get_post($post_id);
            if ($post_id > 0 && get_post($post_id) && $import_mode === "update" && $post_check->post_type === $post_type) {
                $post_data['ID'] = $post_id;
                $updated_post_id = wp_update_post($post_data, true);
                if (is_wp_error($updated_post_id)) {
                    $result = esc_html__("Error (update failed)", "wpsetting-class-package");
                    $error_logs[] = "ID " . $post_id . ": " . $updated_post_id->get_error_message();
                } else {
                    $result = esc_html__("Overwrite successful", "wpsetting-class-package");
                    if ($post_type === "revision") {
                        $error_logs[] = esc_html__("Addition successful", "wpsetting-class-package");
                    }
                    $new_post_id = $updated_post_id;
                }
            } else {
                $new_post_id = wp_insert_post($post_data, true);

                if (is_wp_error($new_post_id)) {
                    $result = esc_html__("Error (addition failed)", "wpsetting-class-package");
                    $error_logs[] = "ID " . $post_id . ": " . $new_post_id->get_error_message();
                } else {
                    $result = esc_html__("Addition successful", "wpsetting-class-package");
                    if ($post_type === "revision") {
                        $error_logs[] = esc_html__("Addition successful", "wpsetting-class-package");
                    }
                }
            }

            //親データとしてIDをキープとログの記録
            if ($post_status != "inherit") {
                $parent_id = $new_post_id;
                $error_logs[] = "==={$post_title}(ID:{$new_post_id} TYPE:{$post_type})===";
            } else {
                //ログの記録
                $error_logs[] = "( ID:{$new_post_id} TYPE:{$post_type} Parent ID:{$parent_id})";
            }


            //投稿データのインポート終了後
            if ($new_post_id && !is_wp_error($new_post_id)) {
                if (isset($entry['thumbnail_id'])) { //メディアIDの時
                    set_post_thumbnail($new_post_id, $entry['thumbnail_id']);
                }
                // **ターム（カテゴリー・タグ・カスタム分類）を登録**
                // 1) 投稿タイプの全タクソノミーを取得して一括クリア
                $all_taxes = get_object_taxonomies(get_post_type($new_post_id));
                wp_delete_object_term_relationships($new_post_id, $all_taxes);
                // 2) 渡された terms をセット
                foreach ($entry['terms'] as $taxonomy => $terms) {
                    $tax_result = wp_set_object_terms($new_post_id, $terms, $taxonomy);
                    //エラーの場合はエラーを記録
                    if (is_wp_error($tax_result)) {
                        $error_logs[] = "ID " . $new_post_id . ": " . $tax_result->get_error_message() . esc_html__("Taxonomy: ", "wpsetting-class-package") . $taxonomy;
                    } else {
                        $error_logs[] = esc_html__("Taxonomy: ", "wpsetting-class-package") . $taxonomy . "  " . esc_html__("has been registered.", "wpsetting-class-package");
                    }
                }

                //カスタムフィールドのインポート
                if (isset($entry['custom_fields'])) {
                    foreach ($entry['custom_fields'] as $field => $value) {
                        update_post_meta($new_post_id, $field, $value);
                        $error_logs[] = esc_html__("Custom Field Import:", "wpsetting-class-package") . $field;
                    }
                }
                //acfフィールドのインポート
                if (isset($entry['acf_fields'])) {
                    if ($this->is_acf_active()) { //acfのインストールチェック
                        $acf_fields = $entry['acf_fields'];
                        $acf_mediaURLs = [];
                        //メディアフィールドを探索し、メディアのURLを配列に格納
                        foreach ($acf_fields as $key => $value) {
                            if (is_string($value) && preg_match('/exported_media\/(.+?\.[a-zA-Z0-9]+)/u', $value, $matches)) { //メディアフィールド
                                $acf_mediaURLs[] = [
                                    'key' => $key,
                                    'value' => $value
                                ];
                            } else if (is_array($value)) {
                                $image_arr = [];
                                foreach ($value as $elm) {
                                    if (is_string($elm) && preg_match('/exported_media\/(.+?\.[a-zA-Z0-9]+)/u', $elm, $matches)) { //メディアフィールド
                                        $image_arr[] = $elm;
                                    }
                                }
                                $acf_mediaURLs[] = [
                                    'key' => $key,
                                    'value' => $image_arr
                                ];
                            }
                        }
                        $group_fields = []; // グループフィールドを格納する配列

                        foreach ($acf_fields as $key => $value) {
                            // グループのプレフィックスを探す
                            if ($value === '_group') {
                                $group_prefix = $key . '_'; // グループのプレフィックス
                                $group_fields[$key] = []; // グループフィールドの配列を初期化

                                // グループ要素を抽出
                                foreach ($acf_fields as $sub_key => $sub_value) {
                                    if (strpos($sub_key, $group_prefix) === 0) {
                                        $sub_field_key = str_replace($group_prefix, '', $sub_key);
                                        $group_fields[$key][$sub_field_key] = $sub_value;
                                    }
                                }
                            }
                        }

                        // 通常のACFフィールドを更新
                        foreach ($acf_fields as $key => $value) {
                            if ($value === '_group') {
                                continue; // グループ要素はここでは処理しない
                            }
                            update_field($key, $value, $new_post_id);
                            $error_logs[] = esc_html__("Custom Field Import(ACF):", "wpsetting-class-package") . $key;
                        }

                        // ACFグループフィールドを更新
                        foreach ($group_fields as $group_key => $group_value) {
                            update_field($group_key, $group_value, $new_post_id);
                            $error_logs[] = esc_html__("Custom Field Import(ACF GROUP):", "wpsetting-class-package") . $group_key;
                        }
                    } else {
                        $error_logs[] = "ID " . $new_post_id . esc_html__(": ACF or SCF is not installed", "wpsetting-class-package");
                    }
                }
                //コメントのインポート
                if (isset($entry['comments'])) {
                    $result_count = $this->insert_comments_with_meta($entry['comments'], $new_post_id, $import_mode === "update");
                    $error_logs[] = $result_count . esc_html__("comment item has been registered.", "wpsetting-class-package");
                }
            }

            //メディアのアップロードとレコードのセット
            //サムネイル
            if ($thumbnail_path) {
                $media_result = $this->set_media($uploaded_medias, $new_post_id, $thumbnail_path, "thumbnail");
                $error_logs[] = $media_result['message'];
            } else {
                delete_post_thumbnail($new_post_id);
            }

            //コンテンツ内画像
            $updated_content = $post_content; //コンテンツを別の変数に置き換え
            foreach ($content_mediaURLs as $content_path) {
                if ($content_path) {
                    $media_result = $this->set_media($uploaded_medias, $new_post_id, $content_path, "content");
                    $updated_content = str_replace($content_path, $media_result['attachment_url'][0], $updated_content);
                    $error_logs[] = $media_result['message'];
                }
            }
            // 投稿を更新
            $update_data = array(
                'ID'           => $new_post_id,
                'post_content' => wp_slash($updated_content),
            );
            wp_update_post($update_data, true);
            //ACF画像
            foreach ($acf_mediaURLs as $acf_path) {
                if ($acf_path) {
                    $media_result = $this->set_media($uploaded_medias, $new_post_id, $acf_path, "acf_field");
                    $error_logs[] = $media_result['message'];
                }
            }

            //inherit以外のレコードで結果生成
            if ($post_status != "inherit") {
                $result_arr = [
                    'result' => $post_type,
                    'id' => $new_post_id,
                    'title' => $post_title,
                    'parentID' => $parent_id,
                    'message' => $result,
                ];
            }
        }
        //リビジョンの生成を戻す
        remove_filter('wp_save_post_revision_check_for_changes', '__return_false');
        remove_filter('wp_revisions_to_keep', '__return_zero', 10);
        //ログは最後に入れる
        $result_arr['log'] = array_map('esc_html', $error_logs);
        return $result_arr;
    }

    //インポートメディアの処理
    public function set_media($media_array, $post_id, $file_path, $media_type)
    {
        // 一時的に GD に切り替えるフィルターを追加
        add_filter('wp_image_editors', [$this, 'force_gd_editor']);

        //$file_pathが配列の時に備えてすべて配列で対応
        $file_names = [];
        //acf_fieldのときはオブジェクトが来るのでそれに対応
        if ($media_type === 'acf_field') {
            $acf_field = $file_path['key'];
            $acf_paths = $file_path['value'];
            if (is_array($acf_paths)) { //$file_pathが配列の時（gallery対応）
                foreach ($acf_paths as $acf_path) {
                    $file_names[] = basename($acf_path);
                }
            } else {
                $file_names[] = basename($acf_paths);
            }
        } else {
            $file_names[] = basename($file_path);
        }

        //$attachment_idをストックする配列
        $attachment_ids = [];
        foreach ($file_names as $file_name) {
            // `name` キーに `$file_name` が一致する要素を検索
            $matched_files = array_filter($media_array, function ($file) use ($file_name) {
                return $file['name'] === $file_name;
            });
            // 1つだけ取得
            $file = reset($matched_files) ?: null;
            //取得できなければ終了
            if (is_null($file)) {
                return array(
                    "status" => 'error',
                    "message" => esc_html__("File not found (file name:", "wpsetting-class-package") . $file_name . ")",
                );
            }

            $upload_dir = wp_upload_dir();
            $dest_path = $upload_dir['path'] . '/' . basename($file['name']);

            if (file_exists($dest_path)) {
                //既に同じ名前のファイルが存在したらアップロードしない
                $attachment_id = $this->get_attachment_id_by_file_path($dest_path);
                if ($attachment_id) {
                    $result = 'success';
                    $message = esc_html__("Processing stopped due to existing file found (media ID:", "wpsetting-class-package") . $attachment_id . ")";
                }
                $attachment_ids[] = $attachment_id;
            } else {
                // wp_handle_upload の前準備
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                $upload_overrides = array('test_form' => false);
                $movefile = wp_handle_upload($file, $upload_overrides);

                if ($movefile && !isset($movefile['error'])) {
                    $dest_path = $movefile['file'];
                    $file_name = isset($file['name']) ? $file['name'] : basename($dest_path);
                    $filetype = wp_check_filetype(basename($dest_path), null);

                    $attachment = array(
                        'post_mime_type' => $filetype['type'],
                        'post_title'     => sanitize_file_name($file_name),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    );

                    $attachment_id = wp_insert_attachment($attachment, $dest_path);
                    $attach_data = wp_generate_attachment_metadata($attachment_id, $dest_path);
                    wp_update_attachment_metadata($attachment_id, $attach_data);
                    $attachment_ids[] = $attachment_id;
                    // 成功時のレスポンス
                    $result = 'success';
                    $message  = esc_html__("File uploaded", "wpsetting-class-package");
                } else {
                    $result = 'error';
                    $message  = esc_html__("Failed to upload file", "wpsetting-class-package");
                }
            }
        }

        // 投稿データにメディア情報を反映

        if ($attachment_ids) {
            if ($media_type === 'thumbnail') {
                set_post_thumbnail($post_id, $attachment_ids[0]);
                $message = esc_html__('Upload thumbnail: ', "wpsetting-class-package") . $message;
            } elseif ($media_type === 'content') {
                $message = esc_html__('Uploading in-content media: ', "wpsetting-class-package") . $message;
            } elseif ($media_type === 'acf_field') {
                if (!empty($acf_field)) {
                    if (count($attachment_ids) > 1) {
                        update_field($acf_field, $attachment_ids, $post_id);
                    } else {
                        update_field($acf_field, $attachment_ids[0], $post_id);
                    }

                    $message = esc_html__('Uploading acf media: ', "wpsetting-class-package") . $message;
                }
            }
        }

        // フィルターを元に戻す
        remove_filter('wp_image_editors', [$this, 'force_gd_editor']);
        //呼び出しもとに返すためにURLを取得
        $attachment_urls = [];
        foreach ($attachment_ids as $attachment_id) {
            $attachment_urls[] = wp_get_attachment_url($attachment_id);
        }

        return array(
            "status" => $result,
            "message" => $message,
            "attachment_id" => $attachment_ids,
            "attachment_url" => $attachment_urls,
        );
    }

    public function force_gd_editor($editors)
    {
        return ['WP_Image_Editor_GD'];
    }

    //acfまたはscfがアクティブかどうかを判定する関数
    public function is_acf_active()
    {
        return (
            // ACF の判定
            (defined('ACF_VERSION') && function_exists('get_field') && class_exists('ACF')) ||

            // SCF の判定
            (class_exists('SCF') && method_exists('SCF', 'get') && function_exists('get_field'))
        );
    }

    //投稿タイプを取得する関数
    public function get_post_type_label($post_type)
    {
        $post_type_object = get_post_type_object($post_type);
        return $post_type_object ? $post_type_object->label : esc_html__('Unregistered Post Types', 'wpsetting-class-package');
    }

    //WordPress のメディアライブラリからファイルのメディア ID を取得する関数
    public function get_attachment_id_by_file_path($file_path)
    {

        // WordPressのアップロードディレクトリの情報を取得
        $upload_dir = wp_upload_dir();

        // アップロードディレクトリを削除して相対パスを取得
        $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);

        // `_wp_attached_file` でメディアIDを取得（完全一致検索）

        $attachments = get_posts([
            'post_type'  => 'attachment',
            'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'     => '_wp_attached_file',
                    'value'   => $relative_path,
                    'compare' => '=',
                ],
            ],
            'numberposts' => 1,
            'fields' => 'ids',
        ]);

        $attachment_id = ! empty($attachments) ? $attachments[0] : 0;


        return $attachment_id ? intval($attachment_id) : false;
    }

    //meta_key から field_XXXXXXX を取得
    public function get_acf_field_key($meta_key)
    {
        $ret = false;

        // ACFフィールドをすべて取得（投稿オブジェクトで取得）
        $acf_fields = get_posts([
            'post_type'      => 'acf-field',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ]);

        if (! $acf_fields) {
            return false; // ACF フィールドが見つからない
        }

        $non_group_fields = [];

        // グループ・リピーター・フレキシブル以外のフィールドを抽出
        foreach ($acf_fields as $field) {
            $field_content = maybe_unserialize($field->post_content);

            if (
                ! isset($field_content['type']) ||
                ! in_array($field_content['type'], ['group', 'repeater', 'flexible_content'], true)
            ) {
                $non_group_fields[] = $field;
            }
        }

        // meta_key と post_excerpt の一致をチェック
        foreach ($non_group_fields as $field) {
            if ($field->post_excerpt === $meta_key) {
                return $field->post_name; // 完全一致で即返す
            } elseif (strpos($meta_key, $field->post_excerpt) !== false) {
                $potential_field = $field;
                $current_field = $potential_field;

                // 親フィールドの post_excerpt が $meta_key に含まれるか
                while ($current_field && $current_field->post_type !== 'acf-field') {
                    $parent_key = (int) $current_field->post_parent;
                    if (! $parent_key) {
                        break;
                    }
                    // 親フィールドが見つからない場合は終了
                    $parent_field = get_post($parent_key);
                    if (! $parent_field) {
                        $potential_field = null; // 仮候補を消去
                        break;
                    }
                    // グループ名が含まれていなければ判定終了
                    if (strpos($meta_key, $parent_field->post_excerpt) === false) {
                        $potential_field = null; // 仮候補を消去
                        break;
                    }

                    $current_field = $parent_field;
                }

                if ($potential_field) {
                    $ret = $potential_field->post_name;
                }
            }
        }

        return $ret;
    }

    //コメントデータの取得（metaデータを含む）
    public function get_comments_with_meta($post_id)
    {
        $args = array(
            'post_id' => $post_id,
            'status'  => 'approve',
            'orderby' => 'comment_date',
            'order'   => 'ASC'
        );

        $comments = get_comments($args);
        $formatted_comments = array();

        foreach ($comments as $comment) {
            // メタデータを取得
            $meta_data = get_comment_meta($comment->comment_ID);
            $meta_formatted = array();

            // メタデータを整形（配列をそのまま使うとJSONで不便なので平坦化）
            foreach ($meta_data as $key => $value) {
                $meta_formatted[$key] = is_array($value) ? $value[0] : $value; // 配列なら最初の値だけ取得
            }

            // コメントデータをフォーマット（メタデータを "meta" キーに格納）
            $formatted_comments[] = array(
                'comment_ID'         => strval($comment->comment_ID),
                'comment_post_ID'    => strval($comment->comment_post_ID),
                'comment_author'     => $comment->comment_author,
                'comment_author_email' => $comment->comment_author_email,
                'comment_date'       => $comment->comment_date,
                'comment_date_gmt'   => $comment->comment_date_gmt,
                'comment_content'    => $comment->comment_content,
                'comment_karma'      => strval($comment->comment_karma),
                'comment_approved'   => strval($comment->comment_approved),
                'comment_type'       => $comment->comment_type,
                'comment_parent'     => strval($comment->comment_parent),
                'user_id'            => strval($comment->user_id),
                'meta'               => $meta_formatted // メタデータを "meta" に格納
            );
        }

        return $formatted_comments;
    }

    //コメントをメタデータとともにインサートする関数
    public function insert_comments_with_meta($comments_data, $post_id, $override_flg)
    {
        $comment_id_map = []; // 旧コメントID → 新コメントID のマッピング用配列
        $pending_comments = []; // 親コメントが未登録のコメントを一時保存
        $ret_count = 0;

        // まず親コメントを登録（`comment_parent` が 0 のもの）
        foreach ($comments_data as $comment_data) {
            $existing_comment = false; //上書きの判断フラグを初期化
            if ($override_flg) {
                // 既存のコメントがあるか確認
                $existing_comment = get_comment($comment_data['comment_ID']);
            }
            if ($comment_data['comment_parent'] == 0) {
                $new_comment_id = $this->post_single_comment($comment_data, $post_id, $existing_comment);
                if ($new_comment_id) {
                    $comment_id_map[$comment_data['comment_ID']] = $new_comment_id;
                    //登録コメント数をインクリメント
                    $ret_count++;
                }
            } else {
                // 親コメントがまだ登録されていないので後で処理する
                $pending_comments[] = $comment_data;
            }
        }

        // 子コメントを登録（`comment_parent` が 0 以外のもの）
        foreach ($pending_comments as $comment_data) {
            $old_parent_id = $comment_data['comment_parent'];

            // マッピングが存在すれば、新しいIDに変換
            if (isset($comment_id_map[$old_parent_id])) {
                $comment_data['comment_parent'] = $comment_id_map[$old_parent_id];
            } else {
                // 親コメントが見つからない場合は 0 にする
                $comment_data['comment_parent'] = 0;
            }

            // 子コメントを挿入
            $new_comment_id = $this->post_single_comment($comment_data, $post_id, $existing_comment);
            if ($new_comment_id) {
                $comment_id_map[$comment_data['comment_ID']] = $new_comment_id;
                //登録コメント数をインクリメント
                $ret_count++;
            }
        }
        //登録数を返す
        return $ret_count;
    }

    // 単一のコメントを `wp_insert_comment()` で挿入
    private function post_single_comment($comment_data, $post_id, $override_flg)
    {
        $comment_arr = array(
            'comment_post_ID'      => intval($post_id),
            'comment_author'       => $comment_data['comment_author'],
            'comment_author_email' => $comment_data['comment_author_email'],
            'comment_content'      => $comment_data['comment_content'],
            'comment_date'         => $comment_data['comment_date'],
            'comment_date_gmt'     => $comment_data['comment_date_gmt'],
            'comment_karma'        => intval($comment_data['comment_karma']),
            'comment_approved'     => intval($comment_data['comment_approved']),
            'comment_type'         => $comment_data['comment_type'],
            'comment_parent'       => intval($comment_data['comment_parent']), // ここで新しいIDが適用される
            'user_id'              => intval($comment_data['user_id'])
        );
        if ($override_flg) {
            $comment_arr["comment_ID"] = intval($comment_data['comment_ID']);

            $new_comment_id = wp_update_comment($comment_arr);
            if ($new_comment_id === 1 || $new_comment_id === 0) { //更新成功であれば、メタデータのコメントIDを更新結果に代入
                $new_comment_id = intval($comment_data['comment_ID']);
            }
        } else {
            $new_comment_id = wp_insert_comment($comment_arr);
        }


        if ($new_comment_id) {
            //メタデータを update_comment_meta() を使うことで、既存のデータを上書き or 追加
            if (!empty($comment_data['meta'])) {
                foreach ($comment_data['meta'] as $meta_key => $meta_value) {
                    update_comment_meta($new_comment_id, $meta_key, $meta_value);
                }
            }
        }
        return $new_comment_id;
    }
}
