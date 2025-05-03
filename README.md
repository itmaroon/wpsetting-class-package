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
namespace Itmar\WpSettingClassPackage;
class ItmarRedirectControl      
class ItmarRevisionClass 
class ItmarModifyPost  
class ItmarSecuritySettings  
class ItmarSEOSettings

## 変更履歴

= 1.1.0 =  
実用化のためのメジャーバージョンアップ

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