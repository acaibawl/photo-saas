# 07. 保護者アカウント・認証API

## 対象機能

- 2.1 招待QRからの新規登録
- 2.1 ログイン
- 2.1 メールアドレス確認

## API一覧

| 機能 | Method | Path |
|---|---|---|
| 招待プレビュー取得 | GET | `/public/invitations/{raw_token}` |
| 招待受諾（新規登録） | POST | `/public/invitations/{raw_token}/accept` |
| 保護者ログイン | POST | `/guardian/auth/login` |
| アクセストークン更新 | POST | `/guardian/auth/refresh` |
| メール確認要求 | POST | `/guardian/auth/email/verification-notification` |
| メール確認完了 | POST | `/guardian/auth/email/verify` |

## 1) 招待プレビュー取得

**Method / Path**: `GET /public/invitations/{raw_token}`  
**Auth**: 不要

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| raw_token | path | string | 必須 | 128bit以上ランダム値 |

### Output（200）

| フィールド | 型 |
|---|---|
| kindergarten_name | string |
| child_name | string |
| class_name | string |
| label | string |
| expires_at | string(datetime) |

### ドメイン制約

- `used_at`, `revoked_at`, `expires_at` を検証し、無効時は `403`。

## 2) 招待受諾（新規登録）

**Method / Path**: `POST /public/invitations/{raw_token}/accept`  
**Auth**: 不要

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| raw_token | path | string | 必須 | 有効招待であること |
| name | body | string | 必須 | max:100 |
| email | body | string | 必須 | email形式, max:255, guardiansで一意 |
| password | body | string | 必須 | min:8, max:72 |

### Output（200）

| フィールド | 型 |
|---|---|
| access_token | string |
| token_type | string |
| expires_in | integer |
| guardian.id | string(ULID) |
| linked_child.id | string(ULID) |

### トランザクション要件

- `SELECT ... FOR UPDATE` で招待行ロック。
- `guardians` 作成、`guardian_child` 作成、`used_at` 更新を同一トランザクションで実行。
- 受諾後の紐づけはその時点で有効なものとして扱い、以後の一覧は `GET /guardian/children` で現在有効な紐づけのみ返す。

## 3) 保護者ログイン

**Method / Path**: `POST /guardian/auth/login`  
**Auth**: 不要

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| email | body | string | 必須 | email形式 |
| password | body | string | 必須 | min:8, max:72 |

### Output（200）

`access_token`, `token_type`, `expires_in`, `guardian`（staff版と同構造）

## 4) アクセストークン更新

**Method / Path**: `POST /guardian/auth/refresh`  
**Auth**: 不要（refresh cookie必須）

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| refresh_token | cookie | string | 必須 | SHA-256照合対象、未失効・期限内 |

### Output（200）

| フィールド | 型 |
|---|---|
| access_token | string |
| token_type | string |
| expires_in | integer |

### ドメイン制約

- ローテーション・再利用検知はstaff側と同じ。

## 5) メール確認

前提:

- 招待受諾時点で `guardians` レコードは作成され、`email_verified_at = null` の仮登録（未確認）状態でログイン可能とする。
- 確認メール送信APIは「ログイン済みかつ未確認ユーザーが再送する」用途のため `auth:guardian` を必須とする。

### 5-1) 確認メール送信

**Method / Path**: `POST /guardian/auth/email/verification-notification`  
**Auth**: `auth:guardian`

**Input**: なし  
**Output（202）**: `{ "queued": true }`

### 5-2) 確認完了

**Method / Path**: `POST /guardian/auth/email/verify`  
**Auth**: 不要（署名トークン検証）

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| id | body | string(ULID) | 必須 | guardians存在 |
| hash | body | string | 必須 | メール確認ハッシュ一致 |
| signature | body | string | 必須 | 署名有効 |
| expires | body | integer | 必須 | 有効期限内 |

**Output（200）**: `{ "email_verified_at": "datetime" }`

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 403 | INVITATION_INVALID_OR_EXPIRED | 招待が無効 |
| 409 | INVITATION_ALREADY_USED | 使用済み |
| 409 | GUARDIAN_EMAIL_ALREADY_EXISTS | 既存メール |
| 422 | VALIDATION_ERROR | 入力不正 |
| 429 | RATE_LIMITED | 招待確認/受諾の試行過多 |
