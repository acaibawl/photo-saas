# 02_2. 園児組管理API

## 対象機能

- 1.2 園児登録・編集
- 1.2 在籍状況管理
- 1.2 組単位のクラス管理

## API一覧

| 機能 | Method | Path |
|---|---|---|
| 組作成 | POST | `/staff/child-classes` |
| 組一覧 | GET | `/staff/child-classes` |
| 組詳細 | GET | `/staff/child-classes/{child_class_id}` |
| 組更新 | PATCH | `/staff/child-classes/{child_class_id}` |
| 組削除 | DELETE | `/staff/child-classes/{child_class_id}` |

## 前提と設計方針

- 組は園単位のマルチテナントに紐づくドメインエンティティとして扱う。
- `kindergarten_id` と `name` の組み合わせは園内で一意とする。
- 園児は `child_class_id` で組を参照し、組名を文字列のまま各園児に持たせない。
- 組名の表記ゆれは起きないようにし、同一園の組は正規の組レコードとして管理する。
- 組を削除する場合は、少なくともその組に紐づく園児が存在しない場合のみ許可する。

## 共通仕様

### 認証

- Auth: `auth:staff`
- すべての API は staff 権限で実行する。

### 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | STAFF_AUTH_REQUIRED | 未認証 |
| 403 | TENANT_SCOPE_VIOLATION | 他園データへのアクセス |
| 404 | CHILD_CLASS_NOT_FOUND | 対象の組が存在しない |
| 409 | CHILD_CLASS_NAME_ALREADY_EXISTS | 同一園内で重複する組名 |
| 409 | CHILD_CLASS_IN_USE | 園児が紐づいているため削除不可 |
| 422 | VALIDATION_ERROR | 入力不正 |

## 1) 組作成

**Method / Path**: `POST /staff/child-classes`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| name | body | string | 必須 | max:50 |

### Output（201）

| フィールド | 型 |
|---|---|
| id | string(ULID) |
| kindergarten_id | string(ULID) |
| name | string |
| created_at | string(datetime) |
| updated_at | string(datetime) |

### ドメイン制約

- 同一園内では `name` が重複してはいけない。
- 重複時は `409 CHILD_CLASS_NAME_ALREADY_EXISTS` を返す。
- 組名は画面上の表示名として利用し、園児の `class_name` は API レスポンスで別途併記される場合があるが、正本は `child_class_id` である。

## 2) 組一覧

**Method / Path**: `GET /staff/child-classes`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| keyword | query | string | 任意 | max:50 |
| page | query | integer | 任意 | min:1 |
| per_page | query | integer | 任意 | min:1, max:100 |

### Output（200）

| フィールド | 型 |
|---|---|
| data | array<childClass> |
| meta.current_page | integer |
| meta.per_page | integer |
| meta.total | integer |

### childClass の構造

| フィールド | 型 |
|---|---|
| id | string(ULID) |
| kindergarten_id | string(ULID) |
| name | string |
| created_at | string(datetime) |
| updated_at | string(datetime) |

## 3) 組詳細

**Method / Path**: `GET /staff/child-classes/{child_class_id}`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_class_id | path | string(ULID) | 必須 | 自園に属すること |

### Output（200）

| フィールド | 型 |
|---|---|
| id | string(ULID) |
| kindergarten_id | string(ULID) |
| name | string |
| created_at | string(datetime) |
| updated_at | string(datetime) |

## 4) 組更新

**Method / Path**: `PATCH /staff/child-classes/{child_class_id}`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_class_id | path | string(ULID) | 必須 | 自園に属すること |
| name | body | string | 任意 | max:50 |

### Output（200）

更新後の childClass オブジェクト（作成時と同型）

### ドメイン制約

- 変更後の `name` が同一園内で重複した場合は `409 CHILD_CLASS_NAME_ALREADY_EXISTS` を返す。
- 既に園児に紐づく組名を変更しても、園児の `child_class_id` は維持したまま、組の正規名だけを更新する。

## 5) 組削除

**Method / Path**: `DELETE /staff/child-classes/{child_class_id}`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_class_id | path | string(ULID) | 必須 | 自園に属すること |

### Output（200）

| フィールド | 型 |
|---|---|
| deleted | boolean |
| id | string(ULID) |

### ドメイン制約

- 組に紐づく園児が 1 件でも存在する場合は削除不可とする。
- 削除不可時は `409 CHILD_CLASS_IN_USE` を返す。
- 削除可能な場合、関連する園児がいない組だけを物理削除する。

## 代表的な制約

### 1. 同一園内での組名の一意性

- 組は `kindergarten_id` と `name` で一意に識別される。
- 「ひよこ組」「ひよこ組」などの重複文字列は同一園内で許可しない。

### 2. 園児との整合性

- 園児が所属する組は `child_class_id` を通じて参照する。
- 組名の変更や削除は、園児側のデータへ直接文字列を持たせないことで整合性を保つ。

### 3. 所有者境界

- 他園の組ID を指定した場合は `403 TENANT_SCOPE_VIOLATION` を返す。
- そのため、組名の検索・更新・削除は常に `kindergarten_id` のスコープで実行する。

## 影響範囲

- 既存の園児作成・更新 API は `class_name` の入力を受け付けるが、内部では `child_class_id` を保持し、組テーブルへ正規化する。
- 画面側では `class_name` を表示用に使いつつ、バックエンドでは `child_class_id` を正式な参照先として扱う。
- これにより、同じ組名の表記揺れや、組の再命名・統合時の整合性維持が容易になる。
