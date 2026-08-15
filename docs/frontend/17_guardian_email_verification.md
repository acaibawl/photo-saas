# 画面設計: 保護者 メール確認

## 目的

- メール確認の案内と再送を行う。

## ルート

- `GET /guardian/email-verification`
- `GET /guardian/email-verification/result`

## 使用API

1. `POST /guardian/auth/email/verification-notification`
2. `GET /guardian/auth/email/verify/{id}/{hash}`（署名付きURLからの遷移先）

## UI構成

- 現在メールアドレス表示
- 「確認メールを再送」ボタン
- 受信手順の案内

## フロー

1. 未確認ユーザーにこの画面を表示
2. 再送ボタンで通知API実行
3. メール内リンク `GET /guardian/auth/email/verify/{id}/{hash}` にアクセスして検証を実行
4. 検証成功時はフロントエンドの結果画面（例: `/guardian/email-verification/result?status=success`）へ遷移し、その後写真一覧へ誘導する

## メール検証リンクの遷移契約

- 検証リンク: `GET /guardian/auth/email/verify/{id}/{hash}`（署名付きURL）
- 検証成功時:
	- 既存の認証状態（ログインセッション）は維持する
	- ログイン済みなら結果画面へ遷移し、完了メッセージと次導線を表示する
- 未ログインでリンクを開いた場合:
	- ログイン画面へ遷移する
	- `return_to` は安全な同一オリジンの内部パスのみ許可し、検証結果画面へ戻せる値を付与する
- 期限切れ・不正リンク:
	- 結果画面（例: `/guardian/email-verification/result?status=expired` または `status=invalid`）へ遷移
	- 「リンクが無効または期限切れ」の表示と、確認メール再送導線を表示する

## 結果画面クエリ仕様

- 対象ルート: `GET /guardian/email-verification/result`
- クエリ: `status=success|expired|invalid`

### status 解釈

- `status=success`
	- 表示: 「メール確認が完了しました」
	- 導線: 写真一覧（`/guardian/photos`）またはホーム（`/guardian`）へ遷移ボタン
- `status=expired`
	- 表示: 「確認リンクの有効期限が切れています」
	- 導線: メール再送画面（`/guardian/email-verification`）への遷移ボタン
- `status=invalid`
	- 表示: 「確認リンクが無効です」
	- 導線: メール再送画面（`/guardian/email-verification`）への遷移ボタン

### 不正・未指定クエリの扱い

- `status` 未指定、または `success|expired|invalid` 以外の値は `invalid` と同等に扱う
- 画面表示のみ切り替え、追加の検証APIは呼ばない（検証はバックエンドの署名付きURLで完了済み）

## エラー処理

- `401`: 未ログイン
- 署名期限切れ/不正署名: 結果画面でエラー表示し、再送を促す

## 実装メモ

- 購入操作時に未確認ならこの画面へ誘導する。
