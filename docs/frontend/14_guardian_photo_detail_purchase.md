# 画面設計: 保護者 写真詳細・購入

## 目的

- 写真詳細確認と Stripe Checkout への遷移を行う。

## ルート

- `GET /guardian/photos/:photoId`

## 使用API

1. `GET /guardian/photos/{photoId}`
2. `POST /guardian/photos/{photoId}/preview-url`
3. `POST /guardian/purchases/checkout-session`

## UI構成

- プレビュー画像
- アルバム名、撮影日、タグ園児
- 価格表示
- 購入ボタン

## 購入フロー

1. 詳細取得
2. ボタン押下時に `photo_ids=[photoId]` で checkout session 作成
3. `checkout_url` にリダイレクト

## 購入リクエスト

- `photo_ids`: 対象写真ID配列
- `checkout_amount`: 画面表示価格（サーバー再計算で検証）
- `success_url`, `cancel_url`: このフロントの結果画面URL

## エラー処理

- `403 PHOTO_PURCHASE_NOT_ALLOWED`
- `403 SALES_DISABLED_FOR_KINDERGARTEN`
- `409 CHECKOUT_AMOUNT_MISMATCH`（価格再取得を促す）
- `422 VALIDATION_ERROR`
