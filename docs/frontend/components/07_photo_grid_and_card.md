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
- status?: queued | ready | failed
- selectable?: boolean
- selected?: boolean

## events

- click
- select
- retryPreview

## 実装メモ

- previewUrl 期限切れ時は placeholder を出し、親が preview 再発行 API を呼ぶ。
- guardian 画面では購入可否、staff 画面では編集可否ステータスを表示する。
