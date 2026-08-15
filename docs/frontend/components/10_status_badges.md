# 共通設計: ステータスバッジ群

## 目的

- ドメイン状態（在籍、招待、Stripe、注文）の色・文言表現を統一する。

## 対象

- component: StatusBadge
- constants: statusLabelMap, statusToneMap

## 想定利用画面

- 04_staff_children_list.md
- 05_staff_child_detail.md
- 08_staff_member_management.md
- 09_staff_stripe_connect_settings.md
- 15_guardian_orders.md

## props

- type: childStatus | invitationStatus | stripeStatus | orderStatus
- value: string

## 返却表示例

- childStatus: enrolled / graduated / withdrawn
- invitationStatus: active / used / expired / revoked
- stripeStatus: enabled / pending / disabled
- orderStatus: pending / paid / failed / refunded

## 実装メモ

- type と value の未知組み合わせは neutral 表示にフォールバックする。
