# 画面設計: スタッフ招待受諾（初回設定）

## 目的

- 招待されたスタッフが初回パスワード設定を行い、利用を開始する。

## ルート

- `GET /staff/invitations/:token`

## 使用API

1. `GET /public/staff-invitations/{raw_token}`
2. `POST /public/staff-invitations/{raw_token}/accept`
3. `GET /staff/auth/me`（受諾後）

## 初期表示

- 招待情報（園名、招待メール、ロール、有効期限）
- パスワード入力、確認入力

## 送信フロー

1. `GET /public/staff-invitations/{raw_token}` でプレビュー
2. パスワード入力後 `POST /public/staff-invitations/{raw_token}/accept`
3. 成功時 `access_token` 保存
4. `GET /staff/auth/me` 実行
5. `/staff` に遷移

## バリデーション

- password: 8〜72文字
- password_confirmation: 一致必須

## エラー処理

- `403 STAFF_INVITATION_INVALID_OR_EXPIRED`: 期限切れ/失効
- `409 STAFF_INVITATION_ALREADY_EXISTS` 等: 受諾不可状態
- `422 VALIDATION_ERROR`: 入力エラー

## 実装メモ

- トークンは URL パラメータから直接利用し、ローカル保存しない。
