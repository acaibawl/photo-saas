# 共通設計: 非同期フィードバック

## 目的

- API 実行中・成功・失敗・進捗表示を統一する。

## 対象

- composable: useAsyncAction
- composable: useApiErrorMessage
- component: InlineAlert
- component: LoadingOverlay
- component: ToastStack

## 想定利用画面

- 全画面
- 特に 07_staff_photo_management.md（アップロード進捗）

## useAsyncAction 仕様

### 返却

- run(action)
	- 戻り値: `Promise<{ ok: true; value: T } | { ok: false; errorCode: string | null; errorMessage: string | null }>`
	- 成功時は `value` に API レスポンスを返す（アップロード受付では `batch_id` を含むレスポンスをそのまま保持）
	- 失敗時は `ok=false` を返し、例外を親へ投げずに UI で扱える形へ正規化する
- pending: Ref<boolean>
- progress: Ref<number | null>
	- 0〜100（不明な処理は `null`）
- errorCode: Ref<string | null>
- errorMessage: Ref<string | null>
- isSuccess: Ref<boolean>
- completedAt: Ref<string | null>
- lastValue: Ref<T | null>
- notifyCompleted(result)
	- 完了通知をトーストへ流すための共通フック

### 進捗・成功状態の更新規約

- `run` 開始時: `pending=true`, `isSuccess=false`, `errorCode/errorMessage=null`, `progress=null`
- `run` 成功時: `pending=false`, `isSuccess=true`, `lastValue=value`, `completedAt` を更新
- `run` 失敗時: `pending=false`, `isSuccess=false`, `errorCode/errorMessage` を設定

### アップロード向けポーリング契約（07_staff_photo_management 用）

- `POST /staff/photos/upload-batch` の成功時、`lastValue.batch_id` を取得してポーリングを開始する
- ポーリングは `GET /staff/photos/upload-batch/{uploadRequestId}` を一定間隔で呼ぶ
- ポーリング状態は `polling: Ref<boolean>`, `pollError: Ref<string | null>`, `pollResult: Ref<object | null>` を別管理する
- 完了条件（例）:
	- `status=completed` または `accepted_count === total_files` の到達
- 完了時は `notifyCompleted` を呼び、写真一覧再取得へ進む

## useApiErrorMessage 仕様

- 入力: `errorCode: string | null`（`useAsyncAction.errorCode` をそのまま渡す）
- 出力: `string`（画面表示文言。未知コード/`null` は汎用メッセージへフォールバック）

### 利用契約

- `useAsyncAction` の失敗時に設定される `errorCode` を、`useApiErrorMessage(errorCode)` へ直接渡して文言化する。
- `run(action)` の戻り値 `{ ok: false, errorCode, errorMessage }` を使う場合も、同じ `errorCode` を文言マッピングの正本として扱う。

## 実装メモ

- エラー文言は1箇所で管理し、画面ごとにハードコードしない。
