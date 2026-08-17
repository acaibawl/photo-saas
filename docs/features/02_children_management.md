# 02. 園児管理API

## 対象機能

- 1.2 園児登録・編集
- 1.2 在籍状況管理

## API一覧

| 機能 | Method | Path |
|---|---|---|
| 園児作成 | POST | `/staff/children` |
| 園児一覧 | GET | `/staff/children` |
| 園児詳細 | GET | `/staff/children/{child_id}` |
| 園児更新 | PATCH | `/staff/children/{child_id}` |
| 在籍状態更新 | PATCH | `/staff/children/{child_id}/status` |

## 1) 園児作成

**Method / Path**: `POST /staff/children`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| name | body | string | 必須 | max:100 |
| child_class_id | body | string(ULID) | 必須 | 自園の child class であること |
| status | body | string | 任意 | `enrolled` / `graduated` / `withdrawn`, 省略時 `enrolled` |

> `class_name` は `GET /staff/child-classes` で取得した組レコードの表示名を解決するための派生値であり、リクエストの正本は `child_class_id` とする。

### Output（201）

| フィールド | 型 |
|---|---|
| id | string(ULID) |
| kindergarten_id | string(ULID) |
| name | string |
| class_name | string |
| status | string |
| created_at | string(datetime) |

## 2) 園児一覧

**Method / Path**: `GET /staff/children`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| status | query | string | 任意 | `enrolled` / `graduated` / `withdrawn` |
| child_class_id | query | string(ULID) | 任意 | 自園の child class であること |
| keyword | query | string | 任意 | max:100 |
| page | query | integer | 任意 | min:1 |
| per_page | query | integer | 任意 | min:1, max:100 |

### Output（200）

| フィールド | 型 |
|---|---|
| data | array<child> |
| meta.current_page | integer |
| meta.per_page | integer |
| meta.total | integer |

## 3) 園児詳細

**Method / Path**: `GET /staff/children/{child_id}`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_id | path | string(ULID) | 必須 | 自園に属すること |

### Output（200）

| フィールド | 型 |
|---|---|
| id | string(ULID) |
| kindergarten_id | string(ULID) |
| name | string |
| class_name | string |
| status | string |
| created_at | string(datetime) |
| updated_at | string(datetime) |

## 4) 園児更新

**Method / Path**: `PATCH /staff/children/{child_id}`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_id | path | string(ULID) | 必須 | 自園に属すること |
| name | body | string | 任意 | max:100 |
| child_class_id | body | string(ULID) | 任意 | 自園の child class であること |

> `class_name` を body 入力として直接受け取らず、`child_class_id` に対する解決済みの表示名を扱う。表示レイヤーが必要なら `GET /staff/child-classes` の `name` を使って描画する。

### Output（200）

更新後の child オブジェクト（作成時と同型）

## 5) 在籍状態更新

**Method / Path**: `PATCH /staff/children/{child_id}/status`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_id | path | string(ULID) | 必須 | 自園に属すること |
| status | body | string | 必須 | `enrolled` / `graduated` / `withdrawn` |

### ドメイン制約

| 現在status | 更新後status | 可否 | 扱い |
|---|---|---|---|
| enrolled | graduated | 可 | 在籍終了として保存する |
| enrolled | withdrawn | 可 | 退園として保存する |
| enrolled | enrolled | 可 | 同一status更新として no-op で受理する |
| graduated | graduated | 可 | 同一status更新として no-op で受理する |
| graduated | enrolled | 可 | 誤操作の訂正として在籍に戻す |
| graduated | withdrawn | 可 | 誤操作の訂正として退園に変更する |
| withdrawn | withdrawn | 可 | 同一status更新として no-op で受理する |
| withdrawn | enrolled | 可 | 誤操作の訂正として在籍に戻す |
| withdrawn | graduated | 可 | 誤操作の訂正として卒園に変更する |

- 在籍状態は誤操作を訂正できるよう、すべての状態間で変更を許可する。

### Output（200）

| フィールド | 型 |
|---|---|
| id | string(ULID) |
| status | string |
| updated_at | string(datetime) |

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | STAFF_AUTH_REQUIRED | 未認証 |
| 403 | TENANT_SCOPE_VIOLATION | 他園データへのアクセス |
| 404 | CHILD_NOT_FOUND | 対象不在 |
| 409 | CHILD_STATUS_TRANSITION_NOT_ALLOWED | 禁止遷移 |
| 422 | VALIDATION_ERROR | 入力不正 |
