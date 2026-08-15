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
- fieldKey?: string
- required?: boolean
- error?: string
- hint?: string

### フィールドエラー解決ルール（422）

- 本設計では「親コンポーネントが 422 レスポンスを解釈し、各フィールドに対応する `error` 文字列を渡す」方式を採用する。
- `FormField` は `error` をそのまま表示し、422 レスポンス全体のパースは担当しない。
- `fieldKey` は親側のマッピング処理で使用するキー（例: `email`, `password`, `child_class_id`）を明示する補助情報として利用する。

## Text/Select/Password 共通props

- modelValue
- disabled?: boolean
- placeholder?: string

## events

- update:modelValue
- blur
- enter

## 実装メモ

- API の 422 エラー（`errors[fieldKey][0]`）を親で解決し、各 `FormField` の `error` に渡す。
