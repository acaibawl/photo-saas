# API設計書一覧

本ディレクトリは [../08_features.md](../08_features.md) に記載された機能のAPI設計をまとめたもの。
フォーマットは全ファイルで統一し、各APIで Input/Output と型・バリデーションを明示する。

## 設計方針（共通）

| 項目 | 方針 |
|---|---|
| 認証 | 園側は `auth:staff`、保護者側は `auth:guardian`。招待確認・受諾の一部は公開API |
| 認可 | 園側は `kindergarten_id` でテナント分離。保護者側は `guardian_child` と `photo_child_tags` でアクセス判定 |
| トークン | 招待/リフレッシュトークンは平文保存しない（SHA-256ハッシュ保存） |
| 日時 | RFC3339 (`YYYY-MM-DDTHH:mm:ssZ`) |
| 金額 | `integer`（最小通貨単位、JPYなら円） |
| ID | `string`（ULID, `^[0-9A-HJKMNP-TV-Z]{26}$`） |
| エラー形式 | `{"error":{"code":"...","message":"...","details":{...}}}` |

## ファイル一覧

| ファイル | 対象機能（08_features） |
|---|---|
| [00_kindergarten_onboarding.md](./00_kindergarten_onboarding.md) | 運営者による園開設コマンド、owner/staff権限設計 |
| [01_staff_auth.md](./01_staff_auth.md) | 1.1 ログイン、テナントスコープ |
| [01_staff_invitation_onboarding.md](./01_staff_invitation_onboarding.md) | 1.1 staff招待、初回パスワード設定、ownerによるstaff管理 |
| [02_children_management.md](./02_children_management.md) | 1.2 園児登録・編集、在籍状況管理 |
| [03_staff_invitations.md](./03_staff_invitations.md) | 1.3 招待QR発行、印刷出力、失効、再発行 |
| [04_staff_guardian_links.md](./04_staff_guardian_links.md) | 1.3 紐づけ済み保護者一覧、紐づけ解除、紐づけ復元 |
| [05_staff_albums_photos.md](./05_staff_albums_photos.md) | 1.4 アルバム作成、写真アップロード、園児タグ付け、価格設定 |
| [06_staff_stripe_connect.md](./06_staff_stripe_connect.md) | 1.5 Stripe Connectオンボーディング、販売制御 |
| [07_guardian_auth.md](./07_guardian_auth.md) | 2.1 招待QRからの新規登録、ログイン、メール確認 |
| [08_guardian_linking.md](./08_guardian_linking.md) | 2.2 兄弟姉妹追加紐づけ、複数園児一元管理 |
| [09_guardian_photo_viewing.md](./09_guardian_photo_viewing.md) | 2.3 写真一覧・プレビュー閲覧、アクセス制御、紐づくアルバム一覧 |
| [10_guardian_purchase_download.md](./10_guardian_purchase_download.md) | 2.4 写真購入、購入済みダウンロード、解除後アクセス継続 |