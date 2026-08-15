# 画面設計: 組（クラス）管理

## 目的

- 園内の組マスタを作成・編集・削除する。

## ルート

- `GET /staff/child-classes`

## 使用API

1. `GET /staff/child-classes`
2. `POST /staff/child-classes`
3. `PATCH /staff/child-classes/{childClassId}`
4. `DELETE /staff/child-classes/{childClassId}`

## UI構成

- 組一覧テーブル
- 新規作成フォーム
- 編集モーダル
- 削除確認モーダル

## ルール

- 同名重複は不可
- 園児が紐づく組は削除不可

## エラー処理

- `409 CHILD_CLASS_NAME_ALREADY_EXISTS`: 名前重複
- `409 CHILD_CLASS_IN_USE`: 削除不可
- `422 VALIDATION_ERROR`: 入力不正

## 実装メモ

- 削除不可時は「所属園児を先に移動してください」と具体メッセージを表示する。
