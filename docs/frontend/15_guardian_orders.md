# 画面設計: 保護者 注文履歴

## 目的

- 過去の注文状態を一覧で確認する。

## ルート

- `GET /guardian/orders`

## 使用API

1. `GET /guardian/orders`

## フィルタ

- status: `pending` / `paid` / `failed` / `refunded`

## 表示項目

- 注文ID
- ステータス
- 合計金額
- 注文日時
- 注文明細（写真件数やサムネイル）

## エラー処理

- `401`: ログインへ
- `422`: クエリ不正
