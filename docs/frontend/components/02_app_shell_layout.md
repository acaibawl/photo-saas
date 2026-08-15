# 共通設計: アプリ共通レイアウト

## 目的

- staff/guardian のナビゲーション、ヘッダ、ログアウト導線を統一する。

## 対象

- component: StaffAppShell
- component: GuardianAppShell
- component: GlobalHeader

## 想定利用画面

- staff 系全画面（03〜09）
- guardian 系ログイン後画面（12〜18）

## props

- title: string
- subtitle?: string
- navItems: Array<{ label, to, activeMatch }>
- userName: string
- userRole?: owner | staff

## events

- onLogout
- onNavigate

## 必須要件

- モバイルでドロワーナビを提供
- PC ではサイドナビ常時表示
- ログアウト時は先にサーバー側ログアウト API（staff: `POST /staff/auth/logout`、guardian: `POST /guardian/auth/logout`）を呼び出し、refresh token の失効または httpOnly Cookie の削除を行う
- API 完了後に auth store を破棄し、ログイン画面へ遷移する（順序: `logout API` → `auth store clear` → `redirect`）

## 実装メモ

- レイアウト配下で useAuthGuard を実行し、個別ページで重複しないようにする。
