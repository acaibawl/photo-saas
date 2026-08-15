# 共通設計: フォーム入力グループ

## 目的

- バリデーションエラー表示付きの入力要素を統一する。

## 対象

- component: FormField
- component: TextInput
- component: SelectInput
- component: PasswordInput

## 想定利用画面

- 01_staff_login.md
- 02_staff_invitation_accept.md
- 10_guardian_invite_landing.md
- 11_guardian_login.md
- 05_staff_child_detail.md
- 08_staff_member_management.md

## FormField props

- label: string
- required?: boolean
- error?: string
- hint?: string

## Text/Select/Password 共通props

- modelValue
- disabled?: boolean
- placeholder?: string

## events

- update:modelValue
- blur
- enter

## 実装メモ

- API の 422 エラーを field key でマッピングしやすい構造に揃える。
