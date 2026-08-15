# 09. 保護者 写真閲覧API

## 対象機能

- 2.3 写真一覧・プレビュー閲覧
- 2.3 アクセス制御

## API一覧

| 機能 | Method | Path |
|---|---|---|
| 紐づくアルバム一覧取得 | GET | `/guardian/albums` |
| 写真一覧取得 | GET | `/guardian/photos` |
| 写真詳細取得 | GET | `/guardian/photos/{photo_id}` |
| プレビューURL再発行 | POST | `/guardian/photos/{photo_id}/preview-url` |

本ドキュメントの一覧APIは「現在可視な写真（有効な紐づけに基づく）」を対象とする。
購入済み写真の一覧（解除後アクセス継続を含む）は、`10_guardian_purchase_download.md` の `GET /guardian/purchased-photos` を利用する。

## 1) 紐づくアルバム一覧取得

**Method / Path**: `GET /guardian/albums`  
**Auth**: `auth:guardian`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_id | query | string(ULID) | 任意 | 自分に有効紐づけされた園児のみ |

### Output（200）

| フィールド | 型 |
|---|---|
| data[].album_id | string(ULID) |
| data[].title | string |
| data[].event_date | string(date) |

フロントでは `album_id` と `title` をプルダウンの選択肢として利用する。

### 認可要件

- デフォルト動作では、自分に有効紐づけされたすべての園児に紐づく写真を持つアルバムをまとめて返す。
- `child_id` を指定した場合のみ、その園児に紐づく写真を持つアルバムに絞り込む。
- 同じアルバムに複数園児の写真が含まれる場合でも、1件として重複排除する。

## 2) 写真一覧取得

**Method / Path**: `GET /guardian/photos`  
**Auth**: `auth:guardian`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| child_id | query | string(ULID) | 任意 | 自分に有効紐づけされた園児のみ |
| album_id | query | string(ULID) | 任意 | 自分に閲覧可能なアルバムのみ |
| event_date_from | query | string(date) | 任意 | `YYYY-MM-DD` |
| event_date_to | query | string(date) | 任意 | `YYYY-MM-DD`, from以上 |
| page | query | integer | 任意 | min:1 |
| per_page | query | integer | 任意 | min:1, max:100 |

### Output（200）

| フィールド | 型 |
|---|---|
| data[].photo_id | string(ULID) |
| data[].album_id | string(ULID) |
| data[].price | integer |
| data[].preview_url | string(url) |
| data[].event_date | string(date) |
| data[].tagged_child_ids | array<string(ULID)> |
| meta.current_page | integer |
| meta.total | integer |

### 認可要件

- クエリ時点で `guardian_child.unlinked_at IS NULL` と `photo_child_tags` をJOINし、可視写真のみ返す。
- 画面側では前段の `/guardian/albums` で取得した `album_id` を使って絞り込みを行う。
- 購入済み写真を再表示する導線には本APIを使わず、entitlement基準の `GET /guardian/purchased-photos` を使う。

## 3) 写真詳細取得

**Method / Path**: `GET /guardian/photos/{photo_id}`  
**Auth**: `auth:guardian`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| photo_id | path | string(ULID) | 必須 | 可視範囲内写真のみ |

### Output（200）

| フィールド | 型 |
|---|---|
| photo_id | string(ULID) |
| album.title | string |
| album.event_date | string(date) |
| price | integer |
| preview_url | string(url) |
| tagged_children | array<object> |

## 4) プレビューURL再発行

**Method / Path**: `POST /guardian/photos/{photo_id}/preview-url`  
**Auth**: `auth:guardian`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| photo_id | path | string(ULID) | 必須 | 可視範囲内写真のみ |

### Output（200）

| フィールド | 型 |
|---|---|
| preview_url | string(url) |
| expires_at | string(datetime) |

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | GUARDIAN_AUTH_REQUIRED | 未認証 |
| 403 | PHOTO_ACCESS_DENIED | 他園児のみ写真 |
| 404 | PHOTO_NOT_FOUND | 対象不在 |
| 422 | VALIDATION_ERROR | 入力不正 |
