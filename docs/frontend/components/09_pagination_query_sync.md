# 共通設計: ページネーションとURLクエリ同期

## 目的

- 一覧画面のページ番号・フィルタを URL と同期し、再訪時再現性を担保する。

## 対象

- composable: useQuerySync
- component: PaginationControl

## 想定利用画面

- 04_staff_children_list.md
- 06_staff_child_classes.md
- 07_staff_photo_management.md
- 13_guardian_photo_gallery.md
- 15_guardian_orders.md
- 16_guardian_purchased_photos.md

## useQuerySync 仕様

### 入力

- defaults: Record<string, unknown>
- parseRules: Record<string, QueryParser>

### 返却

- state
- setState
- resetState
- toQuery

## PaginationControl props

- currentPage: number
- perPage: number
- total: number

## events

- changePage
- changePerPage

## 実装メモ

- query 変更時の API 再取得を debounce し、連打による過剰通信を避ける。
