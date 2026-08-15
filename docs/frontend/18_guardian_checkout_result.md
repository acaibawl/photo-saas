# 画面設計: Checkout結果（成功/キャンセル）

## 目的

- Stripe Checkout 復帰後に購入結果を明示し、次の行動へ誘導する。

## ルート

- `GET /guardian/checkout/result`

## クエリ想定

- `status=success|cancel`
- `order_id`（任意）

## 使用API

1. `GET /guardian/orders`（直近注文を再確認したい場合）
2. `GET /guardian/purchased-photos`（成功後の遷移先）

## 表示分岐

- success: 「購入が完了しました」+ 購入済み写真へ
- cancel: 「購入をキャンセルしました」+ 写真詳細へ戻る

## 実装メモ

- success時でもサーバーWebhook反映に数秒かかる可能性があるため、注文反映待ち表示を許容する。
