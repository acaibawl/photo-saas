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
2. ボタン押下時に `return_url` と `refresh_url` を明示して onboarding URL を発行する
3. `return_url` と `refresh_url` は、同じ画面の URL を指定してもよいが、両方ともこの画面自身の内部パスに固定する
4. 受け取った `onboarding_url` に遷移する
5. Stripe Connect から `return_url` / `refresh_url` のいずれかへ戻った後、再度 status を取得して状態更新する

### URL 定義規約

- `return_url`: オンボーディング完了後に戻る画面。通常は `GET /staff/settings/stripe`
- `refresh_url`: オンボーディング中断・再認証時に戻る画面。通常は `GET /staff/settings/stripe`
- `return_url` と `refresh_url` が未定義の場合は、画面側で `GET /staff/settings/stripe` をデフォルト値として使う
- 外部 URL の受け入れは禁止し、常にアプリ内の同一画面 URL のみを許可する
- これにより、Stripe のリダイレクト先が外部ドメインに飛ばされないようにし、アプリ内の状態復元と 403/422 表示を一貫させる

## エラー処理

- `502 STRIPE_API_ERROR`: 再試行案内
- `403 STAFF_ROLE_FORBIDDEN`: owner限定
- `422`: URL不正

## 実装メモ

- `return_url` と `refresh_url` はともに `GET /staff/settings/stripe` を使い、同じ画面へ戻す。
- 外部URLはフォーム/入力側で許可せず、サーバー側でも `https` のアプリ内 URL のみ受け付けるようにする。
