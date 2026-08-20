# 画面設計: 保護者招待ランディング

## 目的

- QR招待URLから開く入口画面として、招待内容確認と次アクション分岐を提供する。

## ルート

- `GET /invitations/:token`

## 使用API

1. `GET /public/invitations/{rawToken}`
2. `POST /public/invitations/{rawToken}/accept`（未ログイン新規登録）
3. `POST /guardian/invitations/{rawToken}/accept`（ログイン済み追加紐づけ）

## 表示分岐

- 未ログイン: 招待情報 + 新規登録フォーム + ログイン導線
- ログイン済み（guardian）: 「この園児を追加する」確認表示

## 新規登録フロー

1. プレビュー取得
2. `name`, `email`, `password` 入力
3. `POST /public/invitations/{rawToken}/accept`
4. 成功で写真一覧へ遷移（初回はメール確認誘導を表示）

## 追加紐づけフロー

1. プレビュー取得
2. 追加確認
3. `POST /guardian/invitations/{rawToken}/accept`
4. 成功で `GET /guardian/children` 再取得しホームへ

## エラー処理

- `409 INVITATION_ALREADY_USED`
- `403 INVITATION_INVALID_OR_EXPIRED`
- `409 GUARDIAN_CHILD_LINK_ALREADY_EXISTS`
- `422 VALIDATION_ERROR`

## 実装メモ

- 1画面で未ログイン/ログイン済みを出し分けることで導線を単純化する。
