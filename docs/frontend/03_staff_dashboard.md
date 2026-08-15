# 画面設計: スタッフ ダッシュボード

## 目的

- ログイン直後の起点画面として、運用状態と主要導線を提示する。

## ルート

- `GET /staff`

## 使用API

1. `GET /staff/auth/me`
2. `GET /staff/sales/availability`（ownerのみ）
3. `GET /staff/stripe/connect/status`（ownerのみ）

## 表示項目

- ログインユーザー名、ロール
- 販売可否ステータス（ownerのみ）
- Stripeオンボーディング進捗（ownerのみ）
- 主要メニューリンク

## 挙動

- `role=staff` では Stripe 設定カードと販売可否カードを非表示
- `role=owner` のみ `sales_enabled=false` の場合、写真販売関連UIに警告表示
- `403 STAFF_ROLE_FORBIDDEN` の場合、owner限定の販売ステータスと警告表示を非表示にする

## エラー処理

- `401`: ログイン画面へ
- `403 STAFF_ROLE_FORBIDDEN`: owner限定カードを非表示
