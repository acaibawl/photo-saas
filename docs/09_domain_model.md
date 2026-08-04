# ドメインモデル設計

## 1. 目的とスコープ

[02_data_model.md](./02_data_model.md) は永続化スキーマ（テーブル定義）、
[04_auth_and_authorization.md](./04_auth_and_authorization.md) は認証・認可ロジックを
扱っている。本ドキュメントはそれらとは異なる観点として、[08_features.md](./08_features.md)
で列挙した機能を実現するために、

- **どの単位でデータの整合性（トランザクション境界）を保証するか（集約境界）**
- **各集約が常に満たすべき不変条件**
- **集約をまたぐ操作をどう扱うか（ドメインサービス）**

を明文化する。

DDDの用語（集約・エンティティ・値オブジェクト）は借りるが、リポジトリパターンや
ドメインイベントバス等のフル実装は個人開発SaaSの規模には過剰と判断し採用しない
（[05_tech_stack.md §5](./05_tech_stack.md) の「過剰設計の回避」方針に準拠）。
Eloquentモデル + Policyクラス + DBトランザクションで表現できる範囲に留める。

## 2. 集約の全体像

| 集約 | ルートエンティティ | 含まれる子エンティティ | 他集約はIDでのみ参照 |
|---|---|---|---|
| Kindergarten | Kindergarten | KindergartenStaff | — |
| Child | Child | — | kindergarten_id |
| ChildInvitation | ChildInvitation | — | kindergarten_id, child_id, created_by_staff_id |
| GuardianChildLink | GuardianChildLink | — | guardian_id, child_id, kindergarten_id |
| Guardian | Guardian | — | — |
| Photo | Photo | PhotoChildTag | album_id, kindergarten_id, child_id（タグ経由） |
| Album | Album | — | kindergarten_id |
| Order | Order | OrderItem | guardian_id, kindergarten_id, photo_id |
| Entitlement | Entitlement | — | order_item_id, guardian_id, photo_id |
| StaffRefreshToken | StaffRefreshToken | — | kindergarten_staff_id |
| GuardianRefreshToken | GuardianRefreshToken | — | guardian_id |

集約間の参照は原則ID参照のみとし、他集約のプロパティをまたいだ不変条件は
持たせない（例: Photo集約はGuardianChildLinkの状態を直接検証しない。閲覧可否の
判定は4.3節のPhotoAccessPolicyという読み取り専用のクエリに切り出す）。

## 3. 集約ごとの設計

### 3.1 Kindergarten集約

**ルート:** `Kindergarten`　**子エンティティ:** `KindergartenStaff`

- 責務: テナント（園）そのものの管理と、所属スタッフアカウントの管理
- 不変条件:
  - `stripe_onboarding_completed_at` が未設定の間は、この園に紐づく `Photo` の
    購入導線を無効化する（[02_data_model.md §2.7](./02_data_model.md) 参照）
  - `KindergartenStaff.email` は全体で一意（テナントをまたいだメール重複登録を防止）
- 対応する08_features.md: 「1.5 決済・入金設定」「1.1 アカウント・認証」

### 3.2 Child集約

**ルート:** `Child`

- 責務: 園児そのものの属性（氏名・クラス・在籍状況）の管理
- 不変条件:
  - `status` の遷移は `enrolled → graduated` / `enrolled → withdrawn` のみ許可し、
    卒園・退園からの復帰は行わない（誤操作時は園側に個別対応してもらう運用とし、
    ドメインルールとしては不可逆とする）
- 対応する08_features.md: 「1.2 園児管理」

### 3.3 ChildInvitation集約

**ルート:** `ChildInvitation`

- 責務: 招待トークン1件のライフサイクル（発行・使用・失効・期限切れ）を管理する
- 不変条件:
  - `used_at`、`revoked_at` はどちらか一方でも設定されたら以後 `accept` 不可
    （二重使用の防止）
  - `token_hash` にはSHA-256ハッシュのみを保持し、生トークンは保持しない
    （[02_data_model.md §2.3](./02_data_model.md) 参照）
  - 検証〜`used_at` 更新までは単一トランザクション＋行ロック（`SELECT ... FOR UPDATE`）
    で行い、同時アクセスによる二重登録（TOCTOU）を防ぐ
    （[03_invitation_flow.md §6](./03_invitation_flow.md) 参照）
  - 失効・期限切れの `ChildInvitation` は再利用せず、同一 `Child` に対して
    新しい行を作って再発行する（古い行は失効のまま履歴として残す）
- 対応する08_features.md: 「1.3 保護者招待・紐づけ管理」の招待発行・失効・再発行

### 3.4 GuardianChildLink集約

**ルート:** `GuardianChildLink`

`Guardian` 集約にも `Child` 集約にもネストさせず、**独立した集約**として扱う。

- 理由:
  - `Guardian` 集約にネストすると、保護者1人分の紐づけ全体が1トランザクション
    単位になり、園側が単一の紐づけだけを解除する操作と競合しやすくなる
  - `Child` 集約にネストすると、複数保護者が同時に同じ園児へ紐づく操作
    （父用QR・母用QRの同時受諾等）が同一ロックを奪い合い、ボトルネックになる
  - 紐づけ1件を独立したトランザクション境界にすることで、双方向（園側からの解除
    操作／保護者側からの新規登録操作）を素直に表現できる
- 不変条件:
  - 論理削除のみ許可（`unlinked_at` を立てる）。物理削除は行わない
    （[02_data_model.md §2.2](./02_data_model.md) 参照）
  - 同一 `(guardian_id, child_id)` の組み合わせで、有効な行（`unlinked_at IS NULL`）
    は同時に1件までとする（重複紐づけの防止。再紐づけは既存行の `unlinked_at` を
    クリアすることで表現し、新規行は作らない）
- 対応する08_features.md: 「1.3 紐づけ解除・再紐づけ」「2.2 園児との紐づけ」

### 3.5 Guardian集約

**ルート:** `Guardian`

- 責務: 保護者アカウントそのものの管理
- 不変条件:
  - `email` は一意
  - `kindergarten_id` を持たない。どの園と関わりがあるかは常に
    `GuardianChildLink` を経由して判定する（[02_data_model.md §2.1](./02_data_model.md) 参照）
- 対応する08_features.md: 「2.1 アカウント登録・認証」

### 3.6 Album集約

**ルート:** `Album`

- 責務: 撮影イベント単位のグルーピング情報（タイトル・開催日）のみを保持する
  軽量な集約。`Photo` の実体やタグ付けは持たない
- 不変条件: 特になし（他集約からはID参照のみされる）
- 対応する08_features.md: 「1.4 アルバム作成」

### 3.7 Photo集約

**ルート:** `Photo`　**子エンティティ:** `PhotoChildTag`

- 責務: 1枚の写真の実体（ストレージパス・プレビュー・価格）と、その写真に
  写っている園児のタグ付けを管理する。タグはPhotoのライフサイクルに従属するため
  子エンティティとして同一集約に含める（アルバムには従属させない）
- 不変条件:
  - `price` は0より大きい整数
  - `PhotoChildTag.child_id` は当該 `Photo.kindergarten_id` と同じテナントに
    属する `Child` のみタグ付け可能（テナント越境タグ付けの防止）
- 対応する08_features.md: 「1.4 写真アップロード」「1.4 写真への園児タグ付け」「1.4 価格設定」

### 3.8 Order集約

**ルート:** `Order`　**子エンティティ:** `OrderItem`

- 責務: 1回の購入操作（決済単位）の管理
- 不変条件:
  - `total_amount` は自身の `OrderItem.price` の合計と常に一致する
  - `status` の遷移は `pending → paid | failed`、`paid → refunded` のみ許可（逆行しない）
  - `status = paid` になった後は `OrderItem` の追加・削除・価格変更を禁止する
    （決済確定後の内容改変を防ぐ）
- 対応する08_features.md: 「2.4 写真購入」

### 3.9 Entitlement集約

**ルート:** `Entitlement`

`Order` 集約の子エンティティにせず、**独立した集約**として分離する。

- 理由: 返金時に購入明細（`OrderItem`）はそのまま履歴として残しつつ、
  ダウンロード可能な権利だけを個別に失効させたい、また将来の贈与・再発行機能に
  対応しやすくするため（[02_data_model.md §2.5](./02_data_model.md) 参照）
- 不変条件:
  - 1つの `order_item_id` につき生成される `Entitlement` は1件のみ
  - `GuardianChildLink` が解除（`unlinked_at` 設定）されても、既に発行済みの
    `Entitlement` は失効しない（[03_invitation_flow.md §5](./03_invitation_flow.md)、
    [04_auth_and_authorization.md §5](./04_auth_and_authorization.md) 参照）
- 対応する08_features.md: 「2.4 購入済み写真のダウンロード」「解除後の購入済みコンテンツへのアクセス継続」

### 3.10 StaffRefreshToken集約 / GuardianRefreshToken集約

**ルート:** `StaffRefreshToken` / `GuardianRefreshToken`（guardごとに別集約・別テーブル）

- 責務: リフレッシュトークン1件のライフサイクル（発行・ローテーション・失効）を管理する
- 不変条件:
  - `token_hash` にはSHA-256ハッシュのみを保持する
  - `revoked_at` が設定済みのトークンは以後の `/refresh` で再利用不可
  - 同一 `family_id` を持つ行の集合として見たとき、有効（`revoked_at IS NULL`）な
    行は常に高々1件（ローテーションのたびに旧行を失効させ新行を1件だけ発行する）
- 対応する08_features.md: 「1.1 / 2.1 ログイン」

## 4. 集約をまたぐドメインサービス

集約単体では表現しきれない、複数集約にまたがる操作を整理する。Eloquentでは
各サービスをApplication層のAction/Serviceクラスとして実装するイメージ。

### 4.1 InvitationAcceptanceService（招待受諾）

関与する集約: `ChildInvitation`, `Guardian`, `GuardianChildLink`

```
1. ChildInvitation を行ロックして有効性検証（未使用・未失効・未期限切れ）
2. Guardian を新規作成、またはログイン中の Guardian を特定
3. GuardianChildLink を作成（同一 (guardian_id, child_id) の有効な行がないことを確認）
4. ChildInvitation を used 状態に更新（used_at, used_by_guardian_id）
```

1〜4をDBトランザクションで一括コミットする（[03_invitation_flow.md §2, §3](./03_invitation_flow.md) 参照）。

### 4.2 RefreshTokenRotationService（トークンローテーション・再利用検知）

関与する集約: `StaffRefreshToken` または `GuardianRefreshToken`（guard単位で独立）

```
1. 提示されたトークンを token_hash で検索
2. 見つからない、または expires_at / family_expires_at を超過 → 401
3. revoked_at が設定済み（再利用） → 同一 family_id の有効な行を全て失効させ、
   盗難対応として再認証を要求する
4. 有効なトークン → 当該行を revoked_at で失効させ、同じ family_id で
   新しいアクセストークン・リフレッシュトークンを発行する
```

詳細は [04_auth_and_authorization.md §3](./04_auth_and_authorization.md) 参照。

### 4.3 PhotoAccessPolicy（写真閲覧・購入・ダウンロード認可）

関与する集約（読み取りのみ、更新は行わない）: `GuardianChildLink`, `Photo`（`PhotoChildTag`）, `Entitlement`

ドメインサービスというより、集約をまたいだ**読み取り専用のクエリロジック**である。
実装は [04_auth_and_authorization.md §5](./04_auth_and_authorization.md) の `PhotoPolicy` に相当する。

- 閲覧・購入可否: `GuardianChildLink`（有効なもののみ）と `PhotoChildTag` の突合
- ダウンロード可否: `Entitlement` の存在有無のみで判定し、`GuardianChildLink` の
  現在の状態（解除済みか）は問わない（3.9節の不変条件と対応）

### 4.4 OrderFulfillmentService（注文確定・購入権利の付与）

関与する集約: `Order`（`OrderItem`）, `Entitlement`

```
1. Stripe Webhook（決済完了）を受信
2. Order.status を paid に更新
3. 各 OrderItem について Entitlement を1件ずつ生成する
```

`Order` の更新と `Entitlement` の生成は別集約への書き込みのため、Webhook処理の
冪等性（同一イベントの再送に備え、既に `paid` な `Order` への再処理をスキップする）
を持たせる。

## 5. 機能一覧との対応表

[08_features.md](./08_features.md) の各機能が、どの集約・サービスに対応するかの
トレーサビリティを示す。

| 08_features.mdの機能 | 対応する集約/サービス |
|---|---|
| 園児登録・編集、在籍状況管理 | Child集約 |
| 招待QRコード発行・失効・再発行 | ChildInvitation集約 |
| 紐づけ済み保護者一覧確認、紐づけ解除、再紐づけ | GuardianChildLink集約 |
| アルバム作成 | Album集約 |
| 写真アップロード、園児タグ付け、価格設定 | Photo集約 |
| Stripe Connectオンボーディング、販売制御 | Kindergarten集約 |
| 招待QRからの新規登録、兄弟姉妹の追加紐づけ | InvitationAcceptanceService |
| ログイン（園側・保護者側） | StaffRefreshToken / GuardianRefreshToken集約、RefreshTokenRotationService |
| 写真一覧・プレビュー閲覧 | PhotoAccessPolicy |
| 写真購入 | Order集約 |
| 購入済み写真のダウンロード、解除後のアクセス継続 | Entitlement集約、PhotoAccessPolicy |
