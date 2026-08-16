# フロントエンド画面設計書一覧

このディレクトリは Nuxt 4 実装用の画面設計書をまとめたもの。

- 1画面 = 1ファイル
- API パスは `backend/routes/api.php` を正本とする
- 本書の API パスは `/api` プレフィックスを省略して記載（実装時は `/api/...` を呼ぶ）

## 画面一覧

### 園スタッフ向け

1. `01_staff_login.md`
2. `02_staff_invitation_accept.md`
3. `03_staff_dashboard.md`
4. `04_staff_children_list.md`
5. `05_staff_child_detail.md`
6. `06_staff_child_classes.md`
7. `07_staff_photo_management.md`
8. `08_staff_member_management.md`
9. `09_staff_stripe_connect_settings.md`

### 保護者向け

10. `10_guardian_invite_landing.md`
11. `11_guardian_login.md`
12. `12_guardian_home_children.md`
13. `13_guardian_photo_gallery.md`
14. `14_guardian_photo_detail_purchase.md`
15. `15_guardian_orders.md`
16. `16_guardian_purchased_photos.md`
17. `17_guardian_email_verification.md`
18. `18_guardian_checkout_result.md`

## 共通実装ルール（フロント）

- 認証ヘッダ: `Authorization: Bearer {access_token}`（ログイン後の API）
- access token: Pinia（メモリ）保持
- refresh token: httpOnly Cookie（JSから参照しない）
- アプリ起動時は `GET /staff/auth/me` または `GET /guardian/auth/me` を使わず、`POST /staff/auth/refresh` / `POST /guardian/auth/refresh` を使って httpOnly Cookie からセッションを復元する
- 認証ガードは「セッション復元完了」後にのみ `access_token` の有無と role/guard を判定し、復元前にガードが失敗判定して再試行ループを起こさない
- 401 受信時: `single-flight` で 1 回だけ `POST /staff/auth/refresh` または `POST /guardian/auth/refresh` を実行し、同時に複数の 401 を受けても一度だけ再試行する
- refresh 失敗時は `access_token` を破棄し、ログイン画面へ遷移して `retry` フラグを止める
- 403 は権限不足として画面遷移または操作無効化
- 422 は項目エラーをフォームにマッピング
- API エラー形式は `error.code` で分岐し、文言を画面で統一

## 既知の注意点

- メール確認完了の実装は `GET /guardian/auth/email/verify/{id}/{hash}`（署名付きURL）を正とし、結果画面ルートと `status=success|expired|invalid` の解釈は `17_guardian_email_verification.md` に従う。
- 実装時は `backend/routes/api.php` を最優先に合わせる。

## 推奨実装順序（スムーズ実装向け）

依存関係が少ない順に進める。特に認証・共通基盤を先に固めると、後続画面で同じ実装を繰り返さずに済む。

### フェーズ0: 共通基盤（最初に実装）

対象画面:

- `01_staff_login.md`
- `11_guardian_login.md`

先に作る共通機能:

- API クライアント（Bearer 付与、401時 refresh 1回、リトライ）
- Auth Store（staff/guardian のトークンとユーザー状態）
- 共通エラーハンドリング（`error.code` ベース）
- 認証ガード（未ログイン時リダイレクト）

完了条件:

- staff/guardian ともログイン成功後に保護画面へ遷移できる。
- トークン期限切れ時に自動 refresh が1回動作する。

### フェーズ1: スタッフ最小導線

対象画面:

- `03_staff_dashboard.md`
- `06_staff_child_classes.md`
- `04_staff_children_list.md`
- `05_staff_child_detail.md`

理由:

- 園児は `child_class_id` で組に所属するため、園児の作成・編集より先に組マスタを管理できる必要がある。
- 組管理、園児管理、招待/紐づけ管理がこのプロダクトのコア運用。
- ここが動くと、園側の基本業務を先に検証できる。

完了条件:

- 組マスタの CRUD が動作する。
- 園児の作成・編集・状態更新ができる。
- 招待発行/一覧/失効/再発行、紐づけ解除/復元が画面から操作できる。

### フェーズ2: スタッフ補助機能

対象画面:

- `08_staff_member_management.md`

理由:

- owner 権限UIをこのフェーズで整備できる。

完了条件:

- スタッフ招待・ロール変更・有効/停止が動作する。

### フェーズ3: 写真販売の準備（スタッフ側）

対象画面:

- `07_staff_photo_management.md`
- `09_staff_stripe_connect_settings.md`

理由:

- 写真販売はアップロード基盤と Stripe 状態の両方が必要。
- この2画面は並行実装しやすい。

完了条件:

- 写真アップロード、一覧表示、価格/タグ更新ができる。
- Stripe オンボーディング導線と販売可否表示が動作する。

### フェーズ4: 保護者オンボーディング

対象画面:

- `10_guardian_invite_landing.md`
- `12_guardian_home_children.md`
- `17_guardian_email_verification.md`

理由:

- 招待受諾と園児紐づけが保護者導線の入口。
- ここを先に完成させると、閲覧/購入画面のテストデータが自然に作れる。

完了条件:

- 招待URLから新規登録/追加紐づけができる。
- 紐づけ園児一覧が表示される。
- メール確認再送と確認完了導線が動作する。

### フェーズ5: 保護者閲覧・購入

対象画面:

- `13_guardian_photo_gallery.md`
- `14_guardian_photo_detail_purchase.md`
- `18_guardian_checkout_result.md`
- `15_guardian_orders.md`
- `16_guardian_purchased_photos.md`

理由:

- 閲覧（gallery/detail）と購入（checkout）と購入後導線（orders/purchased）を一連で仕上げるフェーズ。

完了条件:

- 写真の絞り込み閲覧、購入、結果表示、注文履歴表示ができる。
- 購入済み写真のダウンロード導線が動作する。
- 紐づけ解除後でも購入済み写真が表示/ダウンロードできる。

## 並行実装してよい組み合わせ

- `07_staff_photo_management.md` と `09_staff_stripe_connect_settings.md`
- `15_guardian_orders.md` と `16_guardian_purchased_photos.md`

## 実装時のチェックポイント（各フェーズ共通）

- 画面実装後に、成功系だけでなく `401/403/409/422` の主要エラー表示を確認する。
- owner 専用画面は `staff` ロールでアクセスした場合のガードを確認する。
- 保護者向けは「有効紐づけベース閲覧」と「entitlementベース購入済み閲覧」を混同しない。