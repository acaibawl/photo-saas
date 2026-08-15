# 共通設計: フィルタバー

## 目的

- 一覧画面の検索・絞り込み UI を共通化する。

## 対象

- component: FilterBar
- sub-components: FilterSelect, FilterDateRange, FilterNumberRange, FilterKeyword

## 想定利用画面

- 04_staff_children_list.md
- 07_staff_photo_management.md
- 13_guardian_photo_gallery.md
- 15_guardian_orders.md
- 16_guardian_purchased_photos.md

## props

- modelValue: Record<string, unknown>
- schema: Array<FilterFieldDefinition>
- busy?: boolean

## events

- update:modelValue
- submit
- reset

## FilterFieldDefinition

- key: string
- type: select | text | dateRange | numberRange
- label: string
- options?: Array<{ label, value }>
- placeholder?: string
- fromKey?: string（`numberRange` の下限クエリキー。既定: `${key}_min`）
- toKey?: string（`numberRange` の上限クエリキー。既定: `${key}_max`）
- min?: number（`numberRange` の入力下限）
- max?: number（`numberRange` の入力上限）
- step?: number（`numberRange` の入力刻み）

## 実装メモ

- フィルタ値の正本は親ページが持つ。
- submit で API 再取得、reset で初期クエリに戻す。
