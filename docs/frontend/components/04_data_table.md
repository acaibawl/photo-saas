# 共通設計: データテーブル

## 目的

- 管理系一覧のテーブル表示を共通化する。

## 対象

- component: DataTable

## 想定利用画面

- 04_staff_children_list.md
- 06_staff_child_classes.md
- 08_staff_member_management.md
- 15_guardian_orders.md

## props

- columns: Array<{ key, label, width?, align? }>
- rows: Array<Record<string, unknown>>
- rowKey: string
- loading?: boolean
- emptyText?: string

## slots

- cell-{key}
- actions
- empty

## events

- rowClick
- sortChange

## 実装メモ

- 操作列は slot で差し替え可能にする。
- 列定義を JSON ライクにして画面間の差分を吸収する。
