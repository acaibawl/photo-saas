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

## 推奨実装順序

バックエンド実装（コントローラ・サービス層）は以下の順で進める。ファイル名の番号順を基本としつつ、実データの発生順に合わせて `04_staff_guardian_links` のみ `07_guardian_auth` の後ろに繰り下げている。

| 順 | ファイル | 依存・理由 |
|---|---|---|
| 1 | [00_kindergarten_onboarding.md](./00_kindergarten_onboarding.md) | 全APIの前提となる園・owner作成 |
| 2 | [01_staff_auth.md](./01_staff_auth.md) | staff系APIの認可基盤（`auth:staff`） |
| 3 | [01_staff_invitation_onboarding.md](./01_staff_invitation_onboarding.md) | 認証基盤の直後に着手可能 |
| 4 | [02_children_management.md](./02_children_management.md) | 招待発行・アルバム紐付けの前提データ |
| 5 | [03_staff_invitations.md](./03_staff_invitations.md) | `child_id` に依存するため02の後 |
| 6 | [07_guardian_auth.md](./07_guardian_auth.md) | 03で発行した招待トークンで保護者が新規登録 |
| 7 | [04_staff_guardian_links.md](./04_staff_guardian_links.md) | 実データ（guardian_child）は07の受諾で発生するため後ろ倒し |
| 8 | [08_guardian_linking.md](./08_guardian_linking.md) | 07のログイン機構に依存する追加紐づけ |
| 9 | [05_staff_albums_photos.md](./05_staff_albums_photos.md) | 02にのみ依存し、3〜8とは独立して並行実装も可能 |
| 10 | [06_staff_stripe_connect.md](./06_staff_stripe_connect.md) | owner権限のみに依存し独立性が高い |
| 11 | [09_guardian_photo_viewing.md](./09_guardian_photo_viewing.md) | 05（写真）と08（紐づけ）の両方が揃って意味を持つ |
| 12 | [10_guardian_purchase_download.md](./10_guardian_purchase_download.md) | 09（閲覧対象）と06（販売可否・Stripe）に依存する最終段 |

### ポイント

- **ファイル名の番号順とのズレは1箇所**: `04_staff_guardian_links` は名前上03の直後ですが、中身は「保護者が紐づいた後」の管理APIなので、`07_guardian_auth`（保護者の新規登録＝紐づけ発生源）より後に回した方が結合テストしやすいです。
- **05と06は並行可能**: `05_staff_albums_photos`（園児管理にのみ依存）と`06_staff_stripe_connect`（owner権限のみに依存）は、招待・保護者系の実装と依存関係がないため、手が空いていれば3〜8のフェーズと並行して進めても問題ありません。
- **最終2つは合流点**: `09_guardian_photo_viewing`と`10_guardian_purchase_download`は他の全機能の集大成なので、必ず最後に回してください。
