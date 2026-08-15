# 画面設計: 園スタッフ ログイン

## 目的

- 園スタッフ（owner/staff）が管理画面へログインする。

## ルート

- `GET /staff/login`

## アクセス制御

- 未ログインのみ表示
- 既ログイン時は `/staff` へリダイレクト

## 使用API

1. `POST /staff/auth/login`
2. `GET /staff/auth/me`（ログイン成功後のプロフィール同期）

## 初期表示

- メール、パスワード入力欄
- 「ログイン」ボタン
- エラーメッセージ領域

## 送信フロー

1. フォームバリデーション（空チェック、email形式）
2. `POST /staff/auth/login`
3. 成功時に `access_token` を Pinia に保存
4. `GET /staff/auth/me` で `role` と `kindergarten_id` を取得
5. `/staff` へ遷移

## エラー処理

- `401 STAFF_AUTH_INVALID_CREDENTIALS`: 「メールアドレスまたはパスワードが正しくありません」
- `429 STAFF_AUTH_RATE_LIMITED`: 「試行回数が多すぎます。時間をおいて再試行してください」
- `422 VALIDATION_ERROR`: 項目ごとに表示

## 実装メモ

- `useStaffAuth()` composable を用意し、`login`, `refresh`, `logout`, `fetchMe` を集約する。
