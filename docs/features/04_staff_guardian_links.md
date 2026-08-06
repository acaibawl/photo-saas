# 04. 園側 紐づけ管理API

## 対象機能

- 1.3 紐づけ済み保護者一覧確認
- 1.3 紐づけ解除
- 1.3 紐づけ復元（再紐づけ）

## API一覧

| 機能 | Method | Path |
|---|---|---|
| 紐づけ保護者一覧 | GET | `/staff/children/{child_id}/guardian-links` |
| 紐づけ解除 | POST | `/staff/guardian-links/{link_id}/unlink` |
| 紐づけ復元 | POST | `/staff/guardian-links/{link_id}/restore` |

## 1) 紐づけ保護者一覧

**Method / Path**: `GET /staff/children/{child_id}/guardian-links`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_id | path | string(ULID) | 必須 | 自園に属すること |
| include_unlinked | query | boolean | 任意 | 省略時 false |

### Output（200）

| フィールド | 型 |
|---|---|
| data[].link_id | string(ULID) |
| data[].guardian_id | string(ULID) |
| data[].guardian_name | string |
| data[].guardian_email | string |
| data[].label | string |
| data[].linked_at | string(datetime) |
| data[].unlinked_at | string(datetime or null) |

## 2) 紐づけ解除

**Method / Path**: `POST /staff/guardian-links/{link_id}/unlink`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| link_id | path | string(ULID) | 必須 | 自園データであること |
| reason | body | string | 任意 | max:255 |
| confirm_text | body | string | 必須 | 固定値 `UNLINK` |

### Output（200）

| フィールド | 型 |
|---|---|
| link_id | string(ULID) |
| unlinked_at | string(datetime) |
| unlinked_by_staff_id | string(ULID) |

### ドメイン制約

- 論理削除のみ（物理削除禁止）。
- 解除後も購入済み写真ダウンロード権（entitlements）は維持。

## 3) 紐づけ復元

**Method / Path**: `POST /staff/guardian-links/{link_id}/restore`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| link_id | path | string(ULID) | 必須 | 自園データであること |

### Output（200）

| フィールド | 型 |
|---|---|
| link_id | string(ULID) |
| unlinked_at | null |
| restored_at | string(datetime) |

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | STAFF_AUTH_REQUIRED | 未認証 |
| 403 | TENANT_SCOPE_VIOLATION | 他園データ操作 |
| 404 | GUARDIAN_LINK_NOT_FOUND | 対象不在 |
| 409 | GUARDIAN_LINK_ALREADY_UNLINKED | 既に解除済み |
| 409 | GUARDIAN_LINK_NOT_UNLINKED | 復元対象が未解除 |
| 422 | VALIDATION_ERROR | 入力不正 |
