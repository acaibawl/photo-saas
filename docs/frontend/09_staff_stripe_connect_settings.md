# 画面設計: Stripe Connect 設定（owner専用）

## 目的

- Stripe Connect オンボーディング開始と販売可否確認を行う。

## ルート

- `GET /staff/settings/stripe`

## アクセス制御

- `role=owner` のみ

## 使用API

1. `GET /staff/stripe/connect/status`
2. `GET /staff/sales/availability`
3. `POST /staff/stripe/connect/onboarding-link`

## UI構成

- 接続状態カード（charges/payouts）
- 未充足要件一覧（requirements_due）
- オンボーディング開始ボタン
- 販売可否バナー

## フロー

1. 初期表示で status と sales availability を取得
2. ボタン押下時に `return_url`, `refresh_url` を送信して onboarding URL 発行
3. 受け取った `onboarding_url` に遷移
4. 戻り後に再度 status を取得して状態更新

## エラー処理

- `502 STRIPE_API_ERROR`: 再試行案内
- `403 STAFF_ROLE_FORBIDDEN`: owner限定
- `422`: URL不正

## 実装メモ

- `return_url` はこの画面自身に戻す。
