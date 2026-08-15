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

## ログアウト時の挙動

- ログアウト操作では `auth store` の access token とユーザー状態をクリアする
- `refresh_token` Cookie を削除し、ブラウザ側の認証情報を無効化する
- 実際のサーバー側では `POST /guardian/auth/logout` を呼び、refresh token を失効させて Cookie を server-side で期限切れ扱いにする

## エラー処理

- `401`: 認証失敗
- `429`: 試行過多
- `422`: 入力不正
