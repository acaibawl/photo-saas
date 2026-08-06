# 08. 保護者 紐づけAPI

## 対象機能

- 2.2 兄弟姉妹の追加紐づけ
- 2.2 複数園児の一元管理

## API一覧

| 機能 | Method | Path |
|---|---|---|
| 招待受諾（ログイン済み追加紐づけ） | POST | `/guardian/invitations/{raw_token}/accept` |
| 紐づけ園児一覧 | GET | `/guardian/children` |

## 1) 追加紐づけ

**Method / Path**: `POST /guardian/invitations/{raw_token}/accept`  
**Auth**: `auth:guardian`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| raw_token | path | string | 必須 | 有効招待であること |

### Output（200）

| フィールド | 型 |
|---|---|
| guardian_id | string(ULID) |
| linked_child.id | string(ULID) |
| linked_child.kindergarten_id | string(ULID) |
| linked_at | string(datetime) |

### ドメイン制約

- 同一 `(guardian_id, child_id)` に有効行がある場合は重複作成せず `409`。
- 招待消費は `guardian_child` 追加と同一トランザクションで実施。

## 2) 紐づけ園児一覧（現在有効な紐づけのみ）

**Method / Path**: `GET /guardian/children`  
**Auth**: `auth:guardian`

### Input

なし

### Output（200）

現在有効な紐づけのみを返す。解除済み・過去の紐づけは含めない。

| フィールド | 型 |
|---|---|
| data[].child_id | string(ULID) |
| data[].child_name | string |
| data[].class_name | string |
| data[].kindergarten_id | string(ULID) |
| data[].kindergarten_name | string |
| data[].label | string |
| data[].linked_at | string(datetime) |

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | GUARDIAN_AUTH_REQUIRED | 未認証 |
| 403 | INVITATION_INVALID_OR_EXPIRED | 招待無効 |
| 409 | GUARDIAN_CHILD_LINK_ALREADY_EXISTS | 重複紐づけ |
| 422 | VALIDATION_ERROR | 入力不正 |
