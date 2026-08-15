# 画面設計: 保護者 購入済み写真

## 目的

- 購入済み写真の一覧表示とダウンロード導線を提供する。

## ルート

- `GET /guardian/purchased-photos`

## 使用API

1. `GET /guardian/purchased-photos`
2. `POST /guardian/photos/{photoId}/download-url`

## フィルタ

- album_id
- event_date_from / event_date_to

## 表示

- 写真サムネイル
- 購入日時
- ダウンロードボタン

## ダウンロードフロー

1. ボタン押下
2. `POST /guardian/photos/{photoId}/download-url`
3. 返却された `download_url` へ遷移して保存

## 仕様上の重要点

- この画面は entitlement 基準で表示するため、紐づけ解除後でも購入済み写真は表示・DL可能。

## エラー処理

- 共通
	- `401 GUARDIAN_AUTH_REQUIRED`
- `GET /guardian/purchased-photos`
	- entitlement が1件もない場合は `200` で空配列（`data=[]`）を返す
	- エラーではなく空状態として扱い、「購入済み写真はまだありません」を表示する
- `POST /guardian/photos/{photoId}/download-url`
	- entitlement 不在の場合は `404 ENTITLEMENT_NOT_FOUND` を返す
	- entitlement は存在していても `storage_path` が空の場合、現行実装では同じく `404 ENTITLEMENT_NOT_FOUND` を返す
