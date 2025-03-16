# wpsetting-class-package

## 概要
WordPressの設定で管理画面のメニューから設定することができないものを集めたパッケージです。
## インストール
コマンドプロンプト等から次のように入力してください。
```
composer require itmar/wpsetting-class-package
```
## 収納されている名前空間・クラス
namespace Itmar\WpSettingPackage;    
class ItmarRevisionClass 


## 変更履歴

= 1.0.0 =  
最初のリリース

## メソッドの機能と引数
### 名前空間・クラス
\Itmar\WpSettingPackage\ItmarRevisionClass

#### block_init(string $text_domain, string $file_path)
##### 説明
$file_path内に含まれている複数のブロックを登録します。同時にPHPとJavascriptの翻訳関数をセットします。翻訳のためのpot,po,moの各ファイルはプラグインのルートフォルダ内のlanguagesフォルダに配置されていることが必要です。
また、WordPressの関数で取得する変数をフロントエンドのJavaScriptで使用できるようにローカライズします。
ローカライズされた変数はテキストドメイン名の'-'を'_'に置換した名称のオブジェクトに次のように収納されます。  
home_url・・・WordPressサイトのホームURL  
plugin_url・・・プラグインルートのURL  

##### 引数
- `$text_domain` string 必須  
プラグインで設定したテキストドメイン名
- `$file_path` string 必須  
プラグインのルートフォルダへの絶対パス。通常は__FILE__を設定する。
##### 戻り値
なし
##### 呼び出し例
```
$block_entry = new \Itmar\BlockClassPakage\ItmarEntryClass();

add_action('init', function () use ($block_entry) {
	$block_entry->block_init('text-domain', __FILE__);
});
```

