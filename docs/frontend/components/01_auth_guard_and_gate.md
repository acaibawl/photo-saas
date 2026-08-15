# 共通設計: 認証ガードと権限ゲート

## 目的

- staff/guardian の未認証遷移制御と owner 専用制御を共通化する。

## 対象

- composable: useAuthGuard
- component: AuthGate

## 想定利用画面

- 01_staff_login.md
- 03_staff_dashboard.md
- 08_staff_member_management.md
- 09_staff_stripe_connect_settings.md
- 11_guardian_login.md
- 12_guardian_home_children.md

## useAuthGuard 仕様

### 引数

- audience: staff | guardian
- requireRole?: owner | staff
- redirectTo: 遷移先パス

### 振る舞い

1. store の token/state を確認
2. 未ログインなら redirectTo へ遷移
3. ログイン済みで requireRole がある場合 role を検査
4. role 不一致なら 403 相当画面または指定パスへ遷移

## AuthGate 仕様

### props

- ready: boolean
- denied: boolean
- loadingText?: string

### slots

- default: 表示許可時
- denied: 拒否時表示

## 実装メモ

- middleware で粗く弾き、AuthGate で画面内制御を行う二段構えにする。
- owner 限定 UI は表示しないだけでなく API 実行前にもガードする。
