# 画面設計: 写真管理

## 目的

- 写真の一括アップロード、一覧閲覧、価格・タグ・アルバムを管理する。

## ルート

- `GET /staff/photos`

## 使用API

1. `GET /staff/photos`
2. `POST /staff/photos/upload-batch`
3. `GET /staff/photos/upload-batch/{uploadRequestId}`
4. `GET /staff/photos/{photoId}`
5. `PATCH /staff/photos/{photoId}`
6. `POST /staff/albums`（アルバム新規作成）
7. `GET /staff/children`（タグ付け候補）

## UI構成

- フィルタバー（アルバム、園児、価格状態、プレビュー状態）
- 写真グリッド
- 一括アップロードドロワー
- 写真編集サイドパネル

## 一括アップロード

1. `album_id`, `price`, `child_ids`, `files[]` を送信
2. `202` で `batch_id` を受け取り進捗監視
3. 進捗は `GET /staff/photos/upload-batch/{uploadRequestId}` をポーリング
4. 完了後に一覧再取得

## 写真編集

- 更新項目: `album_id`, `price`, `child_ids`
- `preview_status != ready` の写真は編集不可にする

## エラー処理

- `409 PHOTO_PREVIEW_PROCESSING`, `PHOTO_NOT_READY_FOR_UPDATE`: 編集禁止表示
- `422 VALIDATION_ERROR`: ファイル形式、サイズ、タグ不正

## 実装メモ

- 大量画像を扱うため、サムネイルは遅延ロードする。
