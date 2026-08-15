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

- `404 ENTITLEMENT_NOT_FOUND`
- `401 GUARDIAN_AUTH_REQUIRED`
