# 共通設計: 写真グリッドとカード

## 目的

- 写真一覧系 UI を staff/guardian で再利用する。

## 対象

- component: PhotoGrid
- component: PhotoCard
- component: PhotoMetaChips

## 想定利用画面

- 07_staff_photo_management.md
- 13_guardian_photo_gallery.md
- 16_guardian_purchased_photos.md

## PhotoCard props

- photoId: string
- previewUrl?: string
- price?: number | null
- eventDate?: string
- tags?: string[]
- previewStatus?: queued | ready | failed
- isSellable?: boolean（guardian 向け購入可否表示）
- editable?: boolean（staff 向け編集可否表示）
- selectable?: boolean
- selected?: boolean

## events

- click
- select
- retryPreview

## 実装メモ

- previewUrl 期限切れ時は placeholder を出し、親が preview 再発行 API を呼ぶ。
- `previewStatus` はプレビュー処理状態（`queued|ready|failed`）のみを表現し、購入可否や編集可否を混在させない。
- guardian 画面の購入可否表示は `isSellable`（または親提供 slot）を正とし、`previewStatus` から推測しない。
- staff 画面の編集可否は `preview_status === ready` から親で算出して `editable` に渡す（または同等の親提供 slot で表示する）。
