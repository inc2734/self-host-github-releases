GitHub Releases にプロダクトがリリース（タグベース）されたときに、GitHub Webhooks を通して下記の処理をおこないます。

- 自前サーバーにタグ毎にプロダクトの zip を保存
- 自前サーバーに GitHub Latest Release API と同じ形式の json ファイルを保存（zip の URL は自前サーバーに保存したモノに変更）

## 使い方
### GitHub Webhooks の設定
https://github.com/アカウント名/リポジトリ名/settings/hooks から設定

#### Payload URL
https://設置URL/github-api/webhook.php と入力

#### Content type
**application/json** を選択

#### Secret
適当な文字列を入力（サーバー側で処理するときに、この Secret のときだけ処理するようにしています）

#### Which events would you like to trigger this webhook?
**Let me select individual events.** を選択し、**Releases** にのみチェック

### アクセストークンの設定
GitHub Releases からそのリリースを消すためにアクセストークンが必要です。
https://github.com/settings/tokens から **public_repo** だけを許可したアクセストークンを取得してください。

## config.php の書き換え
環境にあわせて書き換えてください。

## サーバーへの設置
```
github-api/
├ config.php
└ webhook.php
```
