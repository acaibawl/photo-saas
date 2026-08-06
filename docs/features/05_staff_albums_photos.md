# 05. 園側 アルバム・写真管理API

## 対象機能

- 1.4 アルバム作成
- 1.4 写真アップロード
- 1.4 写真への園児タグ付け
- 1.4 価格設定

## API一覧

| 機能 | Method | Path |
|---|---|---|
| アルバム作成 | POST | `/staff/albums` |
| 写真一括アップロード受付 | POST | `/staff/photos/upload-batch` |
| 写真一覧取得 | GET | `/staff/photos` |
| 写真詳細取得 | GET | `/staff/photos/{photo_id}` |
| 写真情報更新（アルバム・価格・タグ） | PATCH | `/staff/photos/{photo_id}` |

## 非同期一括アップロードの運用方針（推奨設定値）

### 前提

- 初期実装では、1リクエストあたり最大10枚、1枚あたり最大12MB、総計約120MBを上限とする。
- ファイル受付は同期処理ではなく、アップロード要求を受け付けた後にキューへ投入し、プレビュー生成・ストレージ保存・DB登録を非同期で実行する。
- 受付APIは `202 Accepted` を返し、実処理結果はジョブ完了イベントまたはステータスAPIで追跡する。

### 推奨設定値案

| 項目 | 推奨値 | 理由 |
|---|---:|---|
| Nginx `client_max_body_size` | `200m` | 1リクエストあたりの総アップロード容量を確保するため |
| Nginx `client_body_timeout` | `600s` | 大容量アップロードのタイムアウト対策 |
| Nginx `client_header_timeout` | `600s` | ヘッダー/ボディ読み取りのタイムアウト対策 |
| PHP `upload_max_filesize` | `20M` | 1ファイル単位の上限を確保するため |
| PHP `post_max_size` | `200M` | 10枚一括時の合計サイズを受け入れるため |
| PHP `max_file_uploads` | `10` | 1リクエストで許容するファイル数を制御するため |
| PHP `memory_limit` | `512M` | 画像処理・プレビュー生成のメモリ確保のため |
| PHP `max_execution_time` | `600` | アップロード・処理の長時間実行を許容するため |
| PHP `max_input_time` | `600` | 大容量入力の処理時間を確保するため |
| Queue worker timeout | `600` | 画像変換・プレビュー生成の処理上限を確保するため |
| Queue worker tries | `3` | 一時的な失敗時の再試行を扱うため |

### 実装方針

- アップロード受付時はファイルを一時保存し、DBには `upload_request` / `upload_job` のような状態管理レコードを残す。
- 画像変換・プレビュー生成・メタデータ登録はキューで実行する。
- 失敗時は再試行可能なジョブとして扱い、ユーザーに進捗/失敗状態を通知する。

## 1) アルバム作成

**Method / Path**: `POST /staff/albums`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| title | body | string | 必須 | max:120 |
| event_date | body | string(date) | 必須 | `YYYY-MM-DD`, 今日以前を推奨 |

### Output（201）

| フィールド | 型 |
|---|---|
| id | string(ULID) |
| kindergarten_id | string(ULID) |
| title | string |
| event_date | string(date) |
| created_at | string(datetime) |

## 2) 写真一括アップロード受付

**Method / Path**: `POST /staff/photos/upload-batch`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| album_id | body | string(ULID) or null | 任意 | 自園アルバム。未指定/`null` は未分類 |
| files | body/form-data | array<file> | 必須 | 最大10件、各ファイルは `jpg/jpeg/png/heic`、1ファイルあたり最大12MB |
| price | body | integer or null | 任意 | `null` または min:1, max:100000 |
| child_ids | body | array<string(ULID)> | 任意 | max:50件, 同一園児のみ。未指定時は空タグ |

### Output（202）

| フィールド | 型 |
|---|---|
| batch_id | string(ULID) |
| status | string | 
| accepted_count | integer |
| total_files | integer |
| queued_at | string(datetime) |

### ドメイン制約

- 初期実装では、1バッチ内の全写真に共通の `album_id` / `price` / `child_ids` を適用する。
- プレビュー生成・ストレージ保存・DB登録は非同期ジョブで実行する。
- 1バッチ内に不正ファイルが含まれる場合は、全体を `422` で拒否する。
- `child_ids` に他園園児が混入した場合は全体を `422` とする。
- 価格未設定（`price=null`）の写真は販売対象外とする。

## 3) 写真一覧取得

**Method / Path**: `GET /staff/photos`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| album_id | query | string(ULID) | 任意 | 自園アルバム |
| child_id | query | string(ULID) | 任意 | 自園園児 |
| keyword | query | string | 任意 | max:100 |
| price_status | query | string | 任意 | `set` / `unset` |
| price_min | query | integer | 任意 | min:1 |
| price_max | query | integer | 任意 | min:1, `price_min` 以上 |
| preview_status | query | string | 任意 | `queued` / `ready` / `failed` |
| created_from | query | string(date) | 任意 | `YYYY-MM-DD` |
| created_to | query | string(date) | 任意 | `YYYY-MM-DD` |
| page | query | integer | 任意 | min:1 |
| per_page | query | integer | 任意 | min:1, max:100 |

### Output（200）

| フィールド | 型 |
|---|---|
| data[].photo_id | string(ULID) |
| data[].album_id | string(ULID) |
| data[].price | integer or null |
| data[].is_sellable | boolean |
| data[].preview_status | string |
| data[].preview_url | string(url or null) |
| data[].created_at | string(datetime) |
| data[].tagged_child_ids | array<string(ULID)> |
| meta.current_page | integer |
| meta.per_page | integer |
| meta.total | integer |

## 4) 写真詳細取得

**Method / Path**: `GET /staff/photos/{photo_id}`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| photo_id | path | string(ULID) | 必須 | 自園写真 |

### Output（200）

| フィールド | 型 |
|---|---|
| photo_id | string(ULID) |
| album_id | string(ULID) or null |
| album_title | string or null |
| price | integer or null |
| is_sellable | boolean |
| preview_status | string |
| preview_url | string(url or null) |
| original_url | string(url or null) |
| tagged_children | array<object> |
| created_at | string(datetime) |
| updated_at | string(datetime) |

## 5) 写真情報更新（アルバム・価格・タグ）

**Method / Path**: `PATCH /staff/photos/{photo_id}`  
**Auth**: `auth:staff`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| photo_id | path | string(ULID) | 必須 | 自園写真 |
| album_id | body | string(ULID) or null | 任意 | 自園アルバム、`null`可 |
| price | body | integer or null | 任意 | `null` または min:1, max:100000 |
| child_ids | body | array<string(ULID)> | 任意 | max:50, 重複不可、同一園児のみ |

### Output（200）

| フィールド | 型 |
|---|---|
| photo_id | string(ULID) |
| album_id | string(ULID) or null |
| price | integer or null |
| child_ids | array<string(ULID)> |
| is_sellable | boolean |
| updated_at | string(datetime) |

### ドメイン制約

- `preview_status = ready` の写真のみ更新を受け付ける。
- `preview_status = queued/failed` は `409` を返し、更新不可とする。
- 販売可能条件は `preview_status=ready` かつ `price` 設定済み（`price != null`）かつ 園児タグ1件以上。

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | STAFF_AUTH_REQUIRED | 未認証 |
| 403 | TENANT_SCOPE_VIOLATION | 他園データ操作 |
| 404 | ALBUM_OR_PHOTO_NOT_FOUND | 対象不在 |
| 409 | PHOTO_PREVIEW_PROCESSING | 非同期処理中制約 |
| 409 | PHOTO_NOT_READY_FOR_UPDATE | `preview_status` が `ready` ではない |
| 422 | VALIDATION_ERROR | 入力不正 |
