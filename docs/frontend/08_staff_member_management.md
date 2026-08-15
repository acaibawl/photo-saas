# 画面設計: スタッフ管理（owner専用）

## 目的

- スタッフ招待、一覧確認、ロール変更、有効/停止を行う。

## ルート

- `GET /staff/members`

## アクセス制御

- `role=owner` のみ
- `staff` がアクセスした場合は 403 ページまたはダッシュボードへ遷移

## 使用API

1. `GET /staff/staff-members`
2. `GET /staff/staff-members/{staffId}`
3. `PATCH /staff/staff-members/{staffId}/role`
4. `POST /staff/staff-members/{staffId}/deactivate`
5. `POST /staff/staff-members/{staffId}/reactivate`
6. `POST /staff/staff-invitations`
7. `GET /staff/staff-invitations`
8. `POST /staff/staff-invitations/{invitationId}/revoke`

## UI構成

- スタッフ一覧タブ
- 招待一覧タブ
- 招待作成モーダル

## 操作上の注意

- 自分自身のロール変更・停止はUIで禁止
- owner最小人数制約違反時のエラーを明示

## エラー処理

- `403 STAFF_ROLE_FORBIDDEN`: owner専用
- `409`: 自己変更不可、owner最小人数違反、有効招待重複
- `422`: フォームエラー
