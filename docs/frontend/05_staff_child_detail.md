# 画面設計: 園児詳細

## 目的

- 園児情報編集、在籍状態変更、保護者招待管理、紐づけ管理を1画面で行う。

## ルート

- `GET /staff/children/:childId`

## 使用API

1. `GET /staff/children/{childId}`
2. `PATCH /staff/children/{childId}`
3. `PATCH /staff/children/{childId}/status`
4. `GET /staff/children/{childId}/invitations`
5. `POST /staff/children/{childId}/invitations`
6. `GET /staff/invitations/{invitationId}/print`
7. `POST /staff/invitations/{invitationId}/revoke`
8. `POST /staff/invitations/{invitationId}/reissue`
9. `GET /staff/children/{childId}/guardian-links`
10. `POST /staff/guardian-links/{linkId}/unlink`
11. `POST /staff/guardian-links/{linkId}/restore`

## UI構成

- 基本情報カード（氏名、組、状態）
- 招待タブ（発行、一覧、印刷、失効、再発行）
- 紐づけタブ（保護者一覧、解除、復元）

## 重要フロー

1. 画面表示時に詳細・招待一覧・紐づけ一覧を並列取得
2. 招待発行成功時は `invite_url` を使って QR 表示と印刷導線を出す
3. 紐づけ解除は確認モーダルで `confirm_text=UNLINK` を入力必須

## エラー処理

- `409 INVITATION_ALREADY_USED`, `INVITATION_ALREADY_REVOKED`: 再発行不可として行を再読込
- `409 GUARDIAN_LINK_ALREADY_UNLINKED`: 状態差分として再読込
- `422`: 各タブ内フォームに表示

## 実装メモ

- 子要素コンポーネントを `InvitationPanel` と `GuardianLinkPanel` に分離し、ページ肥大化を防ぐ。
