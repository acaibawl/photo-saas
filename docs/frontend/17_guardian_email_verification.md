# 画面設計: 保護者 メール確認

## 目的

- メール確認の案内と再送を行う。

## ルート

- `GET /guardian/email-verification`

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
3. メール内リンクで検証完了後、写真一覧へ遷移

## エラー処理

- `401`: 未ログイン
- 署名期限切れ: 再送を促す

## 実装メモ

- 購入操作時に未確認ならこの画面へ誘導する。
