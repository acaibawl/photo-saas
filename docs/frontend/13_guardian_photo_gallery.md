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

## ページネーション契約

- 本画面は `page/per_page` 方式を採用する（cursor方式は採用しない）。
- リクエスト:
	- `page`: 1以上の整数（未指定時は `1`）
	- `per_page`: 1〜100（未指定時は `20`）
- レスポンスメタデータ:
	- `meta.current_page`: 現在ページ
	- `meta.total`: 総件数
	- `meta.per_page`: 1ページ件数（未返却の場合はリクエスト値を採用）
	- `meta.next_page`: 次ページ番号（未返却の場合は `current_page * per_page < total` で算出、なければ `null`）
- URLクエリ同期:
	- `child_id`, `album_id`, `event_date_from`, `event_date_to`, `page`, `per_page` を URL クエリに保持する。
	- 画面初期表示時は URL クエリを復元して同条件で取得する。

## 取得フロー

1. フィルタ候補（children, albums）を先に取得
2. `GET /guardian/photos` を `page/per_page` とフィルタ条件付きで一覧取得
3. `child_id` または `album_id`（および日付フィルタ）が変更された場合は `page=1` にリセットして再取得
4. サムネイル期限切れ時のみ preview-url 再発行

## エラー処理

- `403 PHOTO_ACCESS_DENIED`
- `404 PHOTO_NOT_FOUND`
- `422 VALIDATION_ERROR`

## 実装メモ

- 絞り込み条件は URL クエリに保持する。
