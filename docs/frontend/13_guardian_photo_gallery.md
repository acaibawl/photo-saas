# 画面設計: 保護者 写真一覧

## 目的

- 閲覧可能な写真をアルバム/園児条件で絞り込み表示する。

## ルート

- `GET /guardian/photos`

## 使用API

1. `GET /guardian/children`（園児フィルタ候補）
2. `GET /guardian/albums`（アルバムフィルタ候補）
3. `GET /guardian/photos`
4. `POST /guardian/photos/{photoId}/preview-url`（期限切れサムネイル再取得）

## フィルタ

- child_id
- album_id
- event_date_from / event_date_to

## 表示

- 写真グリッド（preview_url, 価格, 撮影日）
- ページネーション

## 取得フロー

1. フィルタ候補（children, albums）を先に取得
2. `GET /guardian/photos` で一覧取得
3. サムネイル期限切れ時のみ preview-url 再発行

## エラー処理

- `403 PHOTO_ACCESS_DENIED`
- `404 PHOTO_NOT_FOUND`
- `422 VALIDATION_ERROR`

## 実装メモ

- 絞り込み条件は URL クエリに保持する。
