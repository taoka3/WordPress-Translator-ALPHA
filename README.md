# WordPress-Translator（WordPress 英訳スタジオ）とは
ローカルに落としてきたWordPressデータを同テーブルに新たなカラムを追加し英訳保存するシステム
# 前提条件
### ※ ローカルサーバーが設置出来る事
### ※ ローカルのDBサーバーにWordPressデータを流し込んでいる事
### ※ LM Studio を使用できAIモデルを読み込んでいるAPIサーバーが稼働している事
### ※ database/database.sqliteの設置

## 使用方法
1. .envを設定
2. ローカルサーバーを起動
3. ターミナルより`php artisan migrate`と`npm i && npm run build `を実行
4. ブラウザを開き、WordPress-TranslatorのURLを入力し移動
5. 自動英訳を開始／再開ボタンをクリックし実行
<img width="1476" height="887" alt="image" src="https://qiita-user-contents.imgix.net/https%3A%2F%2Fqiita-image-store.s3.ap-northeast-1.amazonaws.com%2F0%2F779064%2F19ba86d1-4057-4e65-8d34-89860abd19e8.png?ixlib=rb-4.1.1&auto=format&gif-q=60&q=75&w=1400&fit=max&s=44ba3c9eef903905b5545c512ffb8d22" />
