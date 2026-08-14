# 03. 園側 招待API

## 対象機能

- 1.3 招待QRコード発行
- 1.3 招待の印刷用出力
- 1.3 招待の失効
- 1.3 招待の再発行

## API一覧

| 機能 | Method | Path |
|---|---|---|
| 招待発行 | POST | `/staff/children/{child_id}/invitations` |
| 招待一覧 | GET | `/staff/children/{child_id}/invitations` |
| 印刷用データ取得 | GET | `/staff/invitations/{invitation_id}/print` |
| 招待失効 | POST | `/staff/invitations/{invitation_id}/revoke` |
| 招待再発行 | POST | `/staff/invitations/{invitation_id}/reissue` |

## 1) 招待発行

**Method / Path**: `POST /staff/children/{child_id}/invitations`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_id | path | string(ULID) | 必須 | 自園に属すること |
| label | body | string | 必須 | max:50（例: 父用, 母用） |
| expires_in_days | body | integer | 任意 | min:1, max:365, 省略時90 |

### Output（201）

| フィールド | 型 | 説明 |
|---|---|---|
| invitation_id | string(ULID) | 招待ID |
| invite_url | string(url) | 生トークンを含むURL |
| token_expires_at | string(datetime) | 期限 |
| qr_payload | string | QR生成用文字列（URLと同値） |

### ドメイン制約

- DB保存は `token_hash` のみ。生トークンはレスポンス返却時のみ利用。

## 2) 招待一覧

**Method / Path**: `GET /staff/children/{child_id}/invitations`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_id | path | string(ULID) | 必須 | 自園に属すること |
| status | query | string | 任意 | `active` / `used` / `expired` / `revoked` |
| page | query | integer | 任意 | min:1 |
| per_page | query | integer | 任意 | min:1, max:100 |

### Output（200）

| フィールド | 型 |
|---|---|
| data[].invitation_id | string(ULID) |
| data[].label | string |
| data[].expires_at | string(datetime) |
| data[].used_at | string(datetime or null) |
| data[].revoked_at | string(datetime or null) |
| meta.total | integer |

## 3) 印刷用データ取得

**Method / Path**: `GET /staff/invitations/{invitation_id}/print`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| invitation_id | path | string(ULID) | 必須 | 自園に属すること |
| token | query | string | 必須 | 発行/再発行レスポンスの `invite_url` に含まれる生トークンと一致すること |

### Output（200）

PDFファイル（`Content-Type: application/pdf`）をそのまま返却する。

### ドメイン制約

- 生トークンは `token_hash` としてのみ保存されるため、印刷時は発行/再発行時にクライアントが保持している生トークンを `token` として渡す。ハッシュ照合に失敗した場合は `403 INVITATION_TOKEN_MISMATCH`。

## 4) 招待失効

**Method / Path**: `POST /staff/invitations/{invitation_id}/revoke`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| invitation_id | path | string(ULID) | 必須 | 自園に属すること |
| reason | body | string | 任意 | max:255 |

### Output（200）

| フィールド | 型 |
|---|---|
| invitation_id | string(ULID) |
| revoked_at | string(datetime) |

## 5) 招待再発行

**Method / Path**: `POST /staff/invitations/{invitation_id}/reissue`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| invitation_id | path | string(ULID) | 必須 | 元招待が自園に属すること、かつ `used_at` / `revoked_at` が未設定で再発行可能であること |
| label | body | string | 任意 | max:50, 省略時は元招待のlabel |
| expires_in_days | body | integer | 任意 | min:1, max:365 |

### Output（201）

| フィールド | 型 |
|---|---|
| invitation_id | string(ULID) |
| invite_url | string(url) |
| token_expires_at | string(datetime) |

### ドメイン制約

- 元招待が `used_at` 設定済み、または `revoked_at` 設定済みの場合は再発行しない（`409`）。
- 再発行時は旧招待の `token_hash` を保持したまま、旧招待の `revoked_at` を新招待発行と同一トランザクションで更新し、旧トークンを直ちに無効化する。
- 新招待は新しい生トークンと新しい `token_hash` を持ち、旧招待とは独立したレコードとして発行する。
- 再発行済みの旧招待は以後使用不可とし、同一招待チェーンとして再利用させない。
- 再発行回数は元招待ごとに最大3回までとし、上限超過時は拒否する。
- 旧招待の失効と新招待の発行は分離せず、片方でも失敗した場合は全体をロールバックする。

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | STAFF_AUTH_REQUIRED | 未認証 |
| 403 | TENANT_SCOPE_VIOLATION | 他園データ操作 |
| 404 | INVITATION_NOT_FOUND | 招待不在 |
| 403 | INVITATION_TOKEN_MISMATCH | 印刷用トークンがハッシュと不一致 |
| 409 | INVITATION_ALREADY_USED | 使用済み招待への失効不可・再発行不可 |
| 409 | INVITATION_ALREADY_REVOKED | 失効済み招待への再発行不可 |
| 409 | INVITATION_REISSUE_LIMIT_EXCEEDED | 再発行回数上限（3回）超過 |
| 422 | VALIDATION_ERROR | 入力不正 |
