# 01. 園側アカウント・認証API

## 対象機能

- 1.1 ログイン
- 1.1 テナントスコープ

## API一覧

| 機能 | Method | Path |
|---|---|---|
| スタッフログイン | POST | `/staff/auth/login` |
| アクセストークン更新 | POST | `/staff/auth/refresh` |
| ログアウト | POST | `/staff/auth/logout` |
| ログイン中スタッフ取得 | GET | `/staff/auth/me` |

## 1) スタッフログイン

**Method / Path**: `POST /staff/auth/login`  
**Auth**: 不要

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| email | body | string | 必須 | email形式, max:255 |
| password | body | string | 必須 | min:8, max:72 |

### レート制限 / アカウント保護

- 現時点では共通 middleware のログイン専用制限は未実装のため、`POST /staff/auth/login` に個別制限を適用する。
- login単位（`email` 正規化値 + 送信元IP）: 1分あたり5回まで。超過時は `429` を返す。
- 送信元単位（IP）: 1分あたり30回まで。超過時は `429` を返す。
- 連続失敗時は段階的遅延を適用する（例: 失敗6回目以降は `2^n` 秒、上限60秒）。
- 成功ログイン時は当該login単位の失敗カウンタと遅延状態をリセットする。

### Output（200）

| フィールド | 型 | 説明 |
|---|---|---|
| access_token | string | JWT（有効期限短） |
| token_type | string | 常に `Bearer` |
| expires_in | integer | 秒 |
| staff.id | string(ULID) | スタッフID |
| staff.kindergarten_id | string(ULID) | 所属園ID |
| staff.role | string | `owner` or `staff` |

### 備考

- リフレッシュトークンは `httpOnly + Secure + SameSite=Strict` Cookie で返却。
- Cookie Pathは `/staff/auth/refresh` に限定。

## 2) アクセストークン更新

**Method / Path**: `POST /staff/auth/refresh`  
**Auth**: 不要（refresh cookie必須）

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| refresh_token | cookie | string | 必須 | 128bit以上ランダム値、サーバー側でSHA-256照合 |

### Output（200）

| フィールド | 型 | 説明 |
|---|---|---|
| access_token | string | 新しいJWT |
| token_type | string | `Bearer` |
| expires_in | integer | 秒 |

### ドメイン制約

- リフレッシュトークンは使い切りローテーション。
- 使用済みトークン再利用時は同一 `family_id` を全失効し `401` を返す。

## 3) ログアウト

**Method / Path**: `POST /staff/auth/logout`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| all_sessions | body | boolean | 任意 | 省略時false |

### Output（200）

| フィールド | 型 | 説明 |
|---|---|---|
| revoked_count | integer | 失効したrefresh token件数 |

## 4) ログイン中スタッフ取得

**Method / Path**: `GET /staff/auth/me`  
**Auth**: `auth:staff`

### Input

なし

### Output（200）

| フィールド | 型 | 説明 |
|---|---|---|
| id | string(ULID) | スタッフID |
| kindergarten_id | string(ULID) | 所属園ID |
| name | string | 氏名 |
| email | string | メール |
| role | string | `owner` or `staff` |

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | STAFF_AUTH_INVALID_CREDENTIALS | ログイン失敗 |
| 401 | STAFF_AUTH_REFRESH_INVALID | refresh token不正/期限切れ |
| 401 | STAFF_AUTH_REFRESH_REUSE_DETECTED | 再利用検知 |
| 403 | STAFF_AUTH_FORBIDDEN | guard不一致 |
| 429 | STAFF_AUTH_RATE_LIMITED | ログイン試行回数超過 |
| 422 | VALIDATION_ERROR | 入力不正 |
