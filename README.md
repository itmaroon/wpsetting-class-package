# wpsetting-class-package

## 概要
WordPressの設定で管理画面のメニューから設定することができないものをGUIで設定できるようにしたものを集めたパッケージです。  
このパッケージにおさめられたクラスは全てシングルトンモデルです。newでインスタンスをすることはできません。
インスタンスはget_instanceメソッドで呼び出してください。

## インストール
コマンドプロンプト等から次のように入力してください。
```
composer require itmar/wpsetting-class-package
```
## 収納されている名前空間・クラス
namespace Itmar\WpsettingClassPackage  
class ItmarRedirectControl      
class ItmarRevisionClass  
class ItmarModifyPost  
class ItmarSecuritySettings  
class ItmarSEOSettings  
class ItmarDbAction

## 変更履歴

= 1.3.1 =  
ItmarDbActionクラスのset_mediaメソッドの$file_path引数に配列がセットされたときの処理を新たに加えた

= 1.3.0 =  
ItmarDbCacheクラスを新たに加えた

= 1.2.0 =  
ItmarDbActionクラスを新たに加えた

= 1.1.0 =  
実用化のためのマイナーバージョンアップ

= 1.0.0 =  
最初のリリース

## メソッドの機能と引数
### 名前空間・クラス
\Itmar\WpSettingClassPackage\ItmarRedirectControl

### 説明
ItmarRedirectControlのインスタンスを次のように呼び出します。
```
\Itmar\WpSettingClassPackage\ItmarRedirectControl::get_instance();
```
その上で、
```
\Itmar\WpSettingClassPackage\ItmarRedirectControl::get_instance()->render_settings_section();
```
これによってレンダリングされたチェックボックスをチェックすると、サイトにアクセスするためのURLがドメインのルートURLになります。  
この機能が働くのはWordPressサイトがサブドメインにインストールされた場合だけです。ルートドメインにインストールされている場合はチェックするとエラーになります。  

### 名前空間・クラス
\Itmar\WpSettingClassPackage\ItmarRevisionClass

### 説明
ItmarRevisionClassのインスタンスを次のように呼び出します。
```
\Itmar\WpSettingClassPackage\ItmarRevisionClass::get_instance();
```
すると、次のようなGUIが投稿編集画面のサイドバーに表示されるようになります。  
  
![image.png](/assets/revision-scsho.png)  
  
このテキストボックスに数値を入れることで、投稿ごとのリビジョンの最大保存数を設定することができます。
なお、デフォルトのリビジョンの最大保存数はwp-config.phpに次の記述を行うことでしか設定することはできません。
```
define('WP_POST_REVISIONS', 5); // 5個までリビジョンを保存
```
この記述はwp-config.phpを直接編集する必要があります。
ただし、この設定があれば、その数字がテキストボックスに表示されます。
空欄の場合は、設定がなく、リビジョンの最大保存数は無制限になります。

### 名前空間・クラス
\Itmar\WpSettingClassPackage\ItmarDbAction
#### メソッドの説明
json_import_data($groupArr, $uploaded_medias, $import_mode)  
$groupArr内の情報で投稿を実行する。それと同時に$uploaded_mediasに渡されたファイルを基にメディアライブラリにメディアファイルをアップロードし、そのメディアを投稿内のメディア情報（アイキャッチ画像、投稿本文内メディア情報、ACF画像フィールド）とリンクさせる。  

##### 引数
- `$groupArr` array
名前付き配列を要素とする配列を格納する。
この配列の要素一つで１投稿がインポートされる。本投稿とリビジョンを併せて、この配列に格納することで、リビジョンを含めた投稿のインポートが可能である。
各添え字付き要素の生成における注意点は以下のとおり。
- ID　····　投稿ID。$import_modeがupdateで、指定したIDが存在すれば、その投稿を更新。ここで指定したIDが存在しないか、0の時は新規に投稿する（number）
- title　····　タイトル（string）
- content　····　投稿本文（string）
- excerpt　···· 抜粋（string）
- date　····　2025-02-16 13:16:53などの形式の文字列（string）
- author　····　ユーザーのID。省略するとカレントユーザーのIDが自動で入る（number）
- post_name　····　パーマリンクの文字列。省略可（string）
- post_type　····　revisionを指定すれば、先行するpost_statusがinherit以外の投稿タイプのIDがpost_parentにセットされる（string）
- post_status　····　revisionやattachimentの場合は必ずinheritを指定。省略不可（string）
- edit_date ····  更新モードで、dateを指定するときは必ずtrueを設定（boolean）
- post_parent　····　本投稿のID。自動入力なので省略可（number）
- thumbnail_url　···· アイキャッチ画像のURL、エクスポートの基となる（string）
- thumbnail_path　···· アイキャッチ画像のインポート元のフィルパス（string）
- thumbnail_id　···· アイキャッチ画像のメディアライブラリのID。thumbnail_pathが優先（number）
- terms　····  $taxonomy => $terms形式の連想配列（array）
- custom_fields ····  $field_name => $value形式の連想配列（array）
- acf_fields····  $field_name => $value形式の連想配列。グループ内のフィールドの時はgroup_$field_name => $valueとする（array）
- comments　····  以下の連想配列を要素とする配列（array）
    - comment_ID　····　本コメントのID。自動入力なので省略可（number）
    - comment_post_ID　····　本投稿のID。省略不可（number）
    - comment_author　····　コメント投稿者名（string）
    - comment_author_email　····　コメント投稿者のメールアドレス（string）
    - comment_date　····　2025-02-16 13:16:53などの形式の文字列（string）
    - comment_date_gmt　····　2025-02-16 13:16:53などの形式の文字列。GMT変換したもの（string）
    - comment_content　····　コメント本文（string）
    - comment_karma
    - comment_approved
    - comment_type
    - comment_parent　····　返信先のコメントID（number）
    - user_id
    - meta
  
- `$uploaded_medias` array
FormData で送られたBlob形式ファイルの配列  
フロントエンドからのJavaScriptによる送信例
```
const formData = new FormData();
formData.append('action', 'itmar_post_ajax');
formData.append('nonce', ajax_object.nonce);

const blob = await fetch(url).then(res => res.blob());
const path = new URL(url).pathname;
const name = path.split('/').pop().split('?')[0] || `insta_carousel_${i}.jpg`;
const file = new File([blob], name, {
    type: blob.type
});

formData.append('media_files', file);

const response = await fetch(ajaxUrl, {
    method: 'POST',
    body: formData,
});

```
  
- `$import_mode` string
新規投稿のとき　····　　create
更新のとき　····　　update

#### メソッドの説明
set_media($media_array, $post_id, $file_path, $media_type)   
$media_arrayで渡されたアップロードファイルの配列から、ファイル名が$file_pathと一致するものを選び出し、メディアライブラリに登録するとともに、$post_idのIDをもつ投稿に関連付ける。関連付けの方法は次の３とおり。
$file_pathが配列で複数のメディアのパスを渡した場合にも対応する。
- アイキャッチ画像
$media_typeがthumbnail
- ACFメディア
$media_typeがacf_field
- コンテンツ内メディア
$media_typeがcontent
  

##### 引数
- `$media_array` array
FormData で送られたBlob形式ファイルの配列  
- `$post_id` number
関連付ける投稿のID（必須）  
- `$file_path` string
選び出すファイル名（必須）   
- `$media_type` string
関連付けの種類（必須） 
thumbnail、acf_field、contentのいずれか  

##### 戻り値
次の要素を連想配列で返す。
- status 　····　　'success','error'
- message　····　　 結果のメッセージ文字列
- attachment_id　····　　メディアライブラリのID,
- attachment_url　····　メディアライブラリのURL,
  

#### メソッドの説明
is_acf_active()  
ACFまたはSCFがインストールされているかを判定する  
  
##### 戻り値
boolean型の判定値を返す。
  
#### メソッドの説明
get_post_type_label($post_type)   
$post_typeに当てはめられている投稿タイプのラベルを返す。 

##### 引数
- `$post_type` string
投稿タイプのスラッグ
  
##### 戻り値
string型でラベルを返す。登録がない場合はUnregistered Post Typesを返す
  

#### メソッドの説明
get_attachment_id_by_file_path($file_path)   
メディアライブラリ内のメディアから、$file_pathと一致するファイルパスをもつメディアのIDを返す。 

##### 引数
- `$file_path` string
フォルダ名を除いたファイル名。
  
##### 戻り値
メディアIDをnumberで返す。
  
#### メソッドの説明
get_acf_field_key($meta_key)   
ACFで登録されたフィールドの meta_key（例：my_fieldやgroup_field_subfield）から、内部のフィールドキー（例：field_xxxxx）を逆引きで取得する関数
##### 引数
- `$meta_key` string
ACFで保存されたカスタムフィールドの meta_key。通常は get_post_meta() や $_POST などに現れるキー。グループフィールドやリピーター内のフィールドにも対応。
  
##### 戻り値
該当する ACF フィールドの field_key。見かからない場合はfalse
  

#### メソッドの説明
insert_comments_with_meta($comments_data, $post_id, $override_flg)  
$comments_dataを基に$post_idが示す投稿にコメントを挿入する。

##### 引数
- `$comments_data` array
次のコードで取得できる連想配列
```
$args = array(
    'post_id' => $post_id,
    'status'  => 'approve',
    'orderby' => 'comment_date',
    'order'   => 'ASC'
);

$comments = get_comments($args);
```
- `$post_id` number
関連付ける投稿のID（必須）  
- `$override_flg` boolean
既存のコメントIDを持つコメントがあれば上書きするかどうかのフラグ 
  
##### 戻り値
登録数をnumberで返す。
  

#### メソッドの説明
get_comments_with_meta($post_id)   
$post_idが示す投稿に関連付けられているコメントを返す

##### 引数
- `$post_id` number
対象とする投稿のID
  
##### 戻り値
次のような連想配列を配列で返す
```
array(
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
```

### 名前空間・クラス  
\Itmar\WpSettingClassPackage\ItmarDbCache

#### クラスの概要  
`ItmarDbCache` は `$wpdb` を用いた WordPress の DB 操作にキャッシュ機構を付加するためのユーティリティクラスです。Plugin Check で警告されがちな直接DB呼び出しに対し、キャッシュ処理 (`wp_cache_get` / `wp_cache_set` / `wp_cache_delete`) を適切に加えることで、パフォーマンスとコード品質を両立させます。

---

#### メソッドの説明  

---

### `get_var_cached($sql, $cache_key, $expire = 3600)`  
SQL文字列を実行し、結果をキャッシュ付きで1行1列取得する。

##### 引数  
- `string $sql` : 実行するSQL（`$wpdb->prepare()`済み推奨）  
- `string $cache_key` : キャッシュキー  
- `int $expire` : キャッシュの有効期間（秒）

##### 戻り値  
- `mixed` : 結果行の1カラム目の値

##### 使用例  
```php
$sql = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
$result = ItmarDbCache::get_var_cached($sql, 'table_exists_' . md5($table_name));
```

---

### `get_row_cached($sql, $cache_key, $expire = 3600, $output = ARRAY_A)`  
SQL文字列を実行し、1行のデータをキャッシュ付きで取得する。

##### 引数  
- `string $sql` : 実行するSQL  
- `string $cache_key` : キャッシュキー  
- `int $expire` : キャッシュの有効期間（秒）  
- `string $output` : 返却形式。`OBJECT`, `ARRAY_A`, `ARRAY_N` のいずれか（デフォルト：`ARRAY_A`）

##### 戻り値  
- `mixed` : 1行の結果データ

##### 使用例  
```php
$sql = $wpdb->prepare("SELECT * FROM {$table} WHERE token = %s", $token);
$row = ItmarDbCache::get_row_cached($sql, 'row_token_' . md5($token));
```

---

### `update_and_clear_cache($table, $data, $where, ?array $data_format = null, ?array $where_format = null, array $cache_keys = [])`  
レコードを更新し、指定したキャッシュキーのキャッシュを削除する。

##### 引数  
- `string $table` : 対象テーブル名  
- `array $data` : 更新データ  
- `array $where` : WHERE 条件  
- `?array $data_format` : 更新データのフォーマット（`%s`など）  
- `?array $where_format` : WHERE 条件のフォーマット  
- `array $cache_keys` : 削除対象のキャッシュキー配列

##### 戻り値  
- `int|false` : 成功時は更新された行数、失敗時は false

##### 使用例  
```php
$result = ItmarDbCache::update_and_clear_cache(
    $wpdb->users,
    ['user_pass' => $hashed_password],
    ['ID' => $user_id],
    ['%s'],
    ['%d'],
    ['user_cache_' . $user_id]
);
```

---

### `delete_and_clear_cache($table, $where, ?array $where_format = null, array $cache_keys = [])`  
レコードを削除し、指定したキャッシュキーのキャッシュを削除する。

##### 引数  
- `string $table` : 対象テーブル名  
- `array $where` : WHERE 条件  
- `?array $where_format` : WHERE 条件のフォーマット  
- `array $cache_keys` : 削除対象のキャッシュキー配列

##### 戻り値  
- `int|false` : 削除された行数、または失敗時は false

##### 使用例  
```php
$result = ItmarDbCache::delete_and_clear_cache(
    $table,
    ['id' => $row['id']],
    ['%d'],
    ['row_token_' . md5($row['token'])]
);
```

---

### `set_cache_group($group)`  
キャッシュのグループ名を変更する。

##### 引数  
- `string $group` : グループ名（デフォルトは `itmar_cache`）

##### 戻り値  
- なし

##### 使用例  
```php
ItmarDbCache::set_cache_group('my_plugin_group');
```


  






  