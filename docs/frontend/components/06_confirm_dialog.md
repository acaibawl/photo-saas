# 共通設計: 確認ダイアログ

## 目的

- 失効、解除、停止など破壊的操作の確認 UI を統一する。

## 対象

- component: ConfirmDialog

## 想定利用画面

- 05_staff_child_detail.md
- 08_staff_member_management.md
- 06_staff_child_classes.md

## props

- open: boolean
- title: string
- message: string
- confirmLabel?: string
- cancelLabel?: string
- requireText?: string
- loading?: boolean

## events

- confirm
- cancel
- update:open

## 特記事項

- 紐づけ解除 API は confirm_text=UNLINK が必須のため、requireText に UNLINK を指定可能にする。

## 実装メモ

- Enter キー誤爆を防ぐため、requireText が一致しない限り confirm を disabled にする。
