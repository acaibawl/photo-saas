# 画面設計: 保護者ログイン

## 目的

- 保護者が既存アカウントでログインする。

## ルート

- `GET /guardian/login`

## 使用API

1. `POST /guardian/auth/login`
2. `GET /guardian/children`（ログイン後のホーム表示準備）

## UI構成

- メール、パスワード
- ログインボタン
- 招待URLを持っている方向けの案内

## 送信フロー

1. 入力バリデーション
2. `POST /guardian/auth/login`
3. token保存
4. `GET /guardian/children`
5. `/guardian` へ遷移

## エラー処理

- `401`: 認証失敗
- `429`: 試行過多
- `422`: 入力不正
