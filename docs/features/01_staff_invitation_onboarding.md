# 01. 園側 スタッフ招待・初回設定API

## 対象機能

- 1.1 staff 招待
- 1.1 初回パスワード設定
- 1.1 owner による staff 管理

## 目的

- `owner` が自園の追加スタッフを招待できるようにする。
- 招待されたスタッフは、招待トークン経由で初回パスワード設定を行って利用開始する。
- 運営者 command で作成された初回 `owner` のセットアップフローとも整合する設計にする。

## API一覧

| 機能 | Method | Path |
|---|---|---|
| スタッフ招待作成 | POST | `/staff/staff-invitations` |
| スタッフ招待一覧 | GET | `/staff/staff-invitations` |
| スタッフ招待失効 | POST | `/staff/staff-invitations/{invitation_id}/revoke` |
| staff 一覧取得 | GET | `/staff/staff-members` |
| staff 詳細取得 | GET | `/staff/staff-members/{staff_id}` |
| staff ロール変更 | PATCH | `/staff/staff-members/{staff_id}/role` |
| staff 停止 | POST | `/staff/staff-members/{staff_id}/deactivate` |
| staff 再有効化 | POST | `/staff/staff-members/{staff_id}/reactivate` |
| スタッフ招待プレビュー取得 | GET | `/public/staff-invitations/{raw_token}` |
| スタッフ招待受諾（初回設定） | POST | `/public/staff-invitations/{raw_token}/accept` |

## 1) スタッフ招待作成

**Method / Path**: `POST /staff/staff-invitations`  
**Auth**: `auth:staff` + `role:owner`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| name | body | string | 必須 | max:100 |
| email | body | string | 必須 | email形式, max:255, `kindergarten_staff.email` で一意 |
| role | body | string | 必須 | `staff` のみ |
| expires_in_days | body | integer | 任意 | min:1, max:30, 省略時7 |

### Output（201）

| フィールド | 型 |
|---|---|
| invitation_id | string(ULID) |
| invited_name | string |
| invited_email | string |
| role | string |
| expires_at | string(datetime) |

### ドメイン制約

- `owner` のみが実行可能。
- 招待作成時点では `kindergarten_staff` 本体はまだ作成せず、招待レコードのみ保持する。
- 同一 `kindergarten_id` + `email` に有効な未受諾招待がある場合は `409` を返す。
- 生トークンはレスポンスに含めず、メール送信または別の安全な通知経路でのみ配布する。
- `kindergarten_staff` には `kindergarten_id` と正規化済み `email_normalized` の複合ユニーク制約を付ける。

## 2) スタッフ招待一覧

**Method / Path**: `GET /staff/staff-invitations`  
**Auth**: `auth:staff` + `role:owner`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| status | query | string | 任意 | `pending` / `accepted` / `revoked` / `expired` |
| page | query | integer | 任意 | min:1 |
| per_page | query | integer | 任意 | min:1, max:100 |

### Output（200）

| フィールド | 型 |
|---|---|
| data[].invitation_id | string(ULID) |
| data[].name | string |
| data[].email | string |
| data[].role | string |
| data[].status | string |
| data[].expires_at | string(datetime) |
| data[].accepted_at | string(datetime or null) |
| meta.current_page | integer |
| meta.total | integer |

## 3) スタッフ招待失効

**Method / Path**: `POST /staff/staff-invitations/{invitation_id}/revoke`  
**Auth**: `auth:staff` + `role:owner`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| invitation_id | path | string(ULID) | 必須 | 自園の招待のみ |

### Output（200）

| フィールド | 型 |
|---|---|
| invitation_id | string(ULID) |
| revoked_at | string(datetime) |

### ドメイン制約

- 受諾済み招待は失効不可とし `409` を返す。
- 失効後は同じメールアドレスに対して新しい招待を再発行できる。

## 4) スタッフ招待プレビュー取得

**Method / Path**: `GET /public/staff-invitations/{raw_token}`  
**Auth**: 不要

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| raw_token | path | string | 必須 | 128bit以上ランダム値 |

### Output（200）

| フィールド | 型 |
|---|---|
| kindergarten_name | string |
| invited_name | string |
| invited_email | string |
| role | string |
| expires_at | string(datetime) |

### ドメイン制約

- `accepted_at`, `revoked_at`, `expires_at` を検証し、無効時は `403`。
- メールアドレスはマスク表示でもよいが、受諾対象者が判別できる程度の情報は返す。

## 5) staff 一覧取得

**Method / Path**: `GET /staff/staff-members`  
**Auth**: `auth:staff` + `role:owner`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| status | query | string | 任意 | `active` / `inactive` |
| role | query | string | 任意 | `owner` / `staff` |
| keyword | query | string | 任意 | max:100（name/email 部分一致） |
| page | query | integer | 任意 | min:1 |
| per_page | query | integer | 任意 | min:1, max:100 |

### Output（200）

| フィールド | 型 |
|---|---|
| data[].staff_id | string(ULID) |
| data[].name | string |
| data[].email | string |
| data[].role | string |
| data[].status | string |
| data[].last_login_at | string(datetime or null) |
| data[].invited_at | string(datetime or null) |
| data[].joined_at | string(datetime) |
| meta.current_page | integer |
| meta.total | integer |

### ドメイン制約

- 自園所属の staff のみ返す。
- `status=inactive` は論理停止中アカウント（例: `deactivated_at` 非null）を指す。

## 6) staff 詳細取得

**Method / Path**: `GET /staff/staff-members/{staff_id}`  
**Auth**: `auth:staff` + `role:owner`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| staff_id | path | string(ULID) | 必須 | 自園 staff のみ |

### Output（200）

| フィールド | 型 |
|---|---|
| staff_id | string(ULID) |
| name | string |
| email | string |
| role | string |
| status | string |
| last_login_at | string(datetime or null) |
| invited_at | string(datetime or null) |
| joined_at | string(datetime) |
| deactivated_at | string(datetime or null) |

## 7) staff ロール変更

**Method / Path**: `PATCH /staff/staff-members/{staff_id}/role`  
**Auth**: `auth:staff` + `role:owner`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| staff_id | path | string(ULID) | 必須 | 自園 staff のみ |
| role | body | string | 必須 | `owner` / `staff` |

### Output（200）

| フィールド | 型 |
|---|---|
| staff_id | string(ULID) |
| role | string |
| updated_at | string(datetime) |

### ドメイン制約

- 自分自身のロール変更は不可（`409`）。
- 園内の有効 `owner` が0人になる変更は不可（`409`）。
- owner最小人数チェックと更新は同一トランザクションで実行し、`owner` 行を `SELECT ... FOR UPDATE` でロックした上で判定する。
- 判定後の更新は同じトランザクション内で実行し、並行実行時に最後の有効 `owner` を失わないことを保証する。

## 8) staff 停止

**Method / Path**: `POST /staff/staff-members/{staff_id}/deactivate`  
**Auth**: `auth:staff` + `role:owner`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| staff_id | path | string(ULID) | 必須 | 自園 staff のみ |

### Output（200）

| フィールド | 型 |
|---|---|
| staff_id | string(ULID) |
| deactivated_at | string(datetime) |

### ドメイン制約

- 自分自身の停止は不可（`409`）。
- 最後の有効 `owner` を停止する操作は不可（`409`）。
- owner最小人数チェックは「7) staff ロール変更」と同じ原子的処理を使用し、同一の `owner` 行ロックまたは同等の原子的更新で判定と停止更新を一体化する。
- 並行する `deactivate` / `role変更` は同一のトランザクション境界とロック順序で直列化し、`kindergarten_id` 単位で有効 `owner` 集合を `SELECT ... FOR UPDATE` してから判定・更新する。
- 停止時に当該 staff の refresh token family を失効させる。

## 9) staff 再有効化

**Method / Path**: `POST /staff/staff-members/{staff_id}/reactivate`  
**Auth**: `auth:staff` + `role:owner`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| staff_id | path | string(ULID) | 必須 | 自園 staff のみ |

### Output（200）

| フィールド | 型 |
|---|---|
| staff_id | string(ULID) |
| reactivated_at | string(datetime) |

## 10) スタッフ招待受諾（初回設定）

**Method / Path**: `POST /public/staff-invitations/{raw_token}/accept`  
**Auth**: 不要

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| raw_token | path | string | 必須 | 有効招待であること |
| password | body | string | 必須 | min:8, max:72 |
| password_confirmation | body | string | 必須 | `password` と一致 |

### Output（200）

| フィールド | 型 |
|---|---|
| access_token | string |
| token_type | string |
| expires_in | integer |
| staff.id | string(ULID) |
| staff.kindergarten_id | string(ULID) |
| staff.role | string |

### トランザクション要件

- `SELECT ... FOR UPDATE` で招待行をロックする。
- `kindergarten_staff` 作成、パスワードハッシュ設定、`accepted_at` 更新を同一トランザクションで実行する。
- 受諾成功後は staff 用の access token と refresh token を発行する。

### ドメイン制約

- 受諾時に `email` は招待レコードのものをそのまま採用し、受諾処理の最初で同じ正規化関数を通した `email_normalized` を算出してから重複判定と作成に使う。
- 既に同じ `kindergarten_id` + `email_normalized` の staff が存在する場合は `409` を返す。
- duplicate check と `kindergarten_staff` 作成は同一の正規化済み email 値を使い、別表現のメールで重複を見逃さない。
- 運営者 command で作成した初回 `owner` の初回設定も、同じ招待受諾フローまたは同等のトークン検証フローに寄せる。

## セキュリティ考慮

- 招待トークンは生値を保存せず、SHA-256 ハッシュのみ保持する。
- `GET /public/staff-invitations/{raw_token}` と受諾APIの両方にレートリミットを設ける。
- 招待メール誤送信時に `owner` が即座に失効できるようにする。

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | STAFF_AUTH_REQUIRED | 未認証 |
| 403 | STAFF_ROLE_FORBIDDEN | `owner` 以外による実行 |
| 403 | STAFF_INVITATION_INVALID_OR_EXPIRED | 招待が無効 |
| 404 | STAFF_MEMBER_NOT_FOUND | 対象 staff が存在しない |
| 409 | STAFF_INVITATION_ALREADY_EXISTS | 同一メールに有効招待あり |
| 409 | STAFF_INVITATION_ALREADY_ACCEPTED | 受諾済み |
| 409 | STAFF_EMAIL_ALREADY_EXISTS | staffメール重複 |
| 409 | STAFF_ROLE_CHANGE_SELF_FORBIDDEN | 自分自身の role 変更 |
| 409 | STAFF_DEACTIVATE_SELF_FORBIDDEN | 自分自身の停止 |
| 409 | OWNER_MINIMUM_REQUIRED | 有効 owner が0人になる操作 |
| 422 | VALIDATION_ERROR | 入力不正 |
