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
- pending: Ref<boolean>
- errorCode: Ref<string | null>
- errorMessage: Ref<string | null>

## useApiErrorMessage 仕様

- 入力: error.code
- 出力: 画面表示文言

## 実装メモ

- エラー文言は1箇所で管理し、画面ごとにハードコードしない。
