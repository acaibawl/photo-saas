# 画面設計: 園児一覧

## 目的

- 園児の検索・作成・詳細遷移を行う。

## ルート

- `GET /staff/children`

## 使用API

1. `GET /staff/children`
2. `POST /staff/children`
3. `GET /staff/child-classes`（作成フォームの組選択候補）

## UI構成

- フィルタ: 在籍状態、組、キーワード
- 一覧テーブル: 氏名、組、状態、作成日
- 「園児を追加」モーダル

## 一覧取得

- 初期表示時に `GET /staff/children?page=1&per_page=20`
- フィルタ変更時はクエリ再取得

## 園児作成

- 入力: `name`, `class_name`, `status`
- 送信: `POST /staff/children`
- 成功後: モーダルを閉じて一覧再取得

## エラー処理

- `422 VALIDATION_ERROR`: モーダル内の項目エラー
- `401`: ログイン画面へ
- `403 TENANT_SCOPE_VIOLATION`: 通常発生しないが、共通エラートースト表示

## 実装メモ

- フィルタ状態は URL クエリと同期し、戻る操作で再現できるようにする。
