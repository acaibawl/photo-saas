# 認証・認可設計

## 1. 認証方式

**JWT（`tymon/jwt-auth`）によるアクセストークン + リフレッシュトークン方式**を採用する。

- アクセストークンは**短命**（例: 15分）のステートレスなJWTとし、署名検証のみで
  認可判定を行う。DBアクセスなしで検証できるためAPIレスポンスが高速
- リフレッシュトークンは**長命**（例: 30日）だが、JWTとは異なり**DBでステートフルに
  管理**し、盗難時に個別・一括で失効させられるようにする（詳細は本章3節）
- Nuxt4とLaravelはAPIサーバーとして疎結合に運用し、Cookieセッション（Sanctum SPA
  モード）には依存しない。将来的なモバイルアプリ化・別オリジン展開に対しても
  同一の認証方式をそのまま利用できる

### 1.1 トークンの保存場所

| トークン | 保存場所 | 理由 |
|---|---|---|
| アクセストークン | フロント側メモリ（Piniaストア）。APIリクエストの `Authorization: Bearer {token}` ヘッダーに付与 | 有効期限が短いため漏洩時の被害が限定的。ページリロードで失われるが、リフレッシュトークンから再取得すればよい |
| リフレッシュトークン | **httpOnly + Secure + SameSite=Strict Cookie**（`Path` 属性を `/api/staff/refresh` / `/api/guardian/refresh` にそれぞれ限定） | JavaScriptから読み取れないためXSS経由の窃取を防止。Cookieの`Path`属性を各guardのrefreshエンドポイントに絞ることで、それ以外のURLへのリクエストにはブラウザが送信しないようにする（`Path`属性はワイルドカード非対応・前方一致のため、guardごとに個別のCookieとして発行する） |

アクセストークンをlocalStorage等に保存しない（XSSで即座に窃取されるため）。

## 2. Guardを分離する

`kindergarten_staff`（園側）と `guardians`（保護者側）は、
1つの `users` テーブル＋role列ではなく、**別テーブル・別JWT guard**
として明確に分離する。

理由:
- 両者はできること・見えるデータの範囲が根本的に異なり、role分岐のif文が
  増えるほど「権限チェック漏れ」のリスクが上がる
- テーブルレベルで分かれていれば、「保護者が誤って園スタッフ用APIを叩ける」
  という事故はルーティング/ミドルウェアの時点で構造的に防げる
- 個人開発の規模でも、写真という個人情報を扱うため認可まわりは
  多少冗長でも安全側に倒す価値が高い

```php
// config/auth.php （イメージ）
'guards' => [
    'staff' => ['driver' => 'jwt', 'provider' => 'staff'],
    'guardian' => ['driver' => 'jwt', 'provider' => 'guardians'],
],
```

ルーティングは `/api/staff/*` と `/api/guardian/*` でプレフィックスを分け、
それぞれ対応するミドルウェア（`auth:staff`, `auth:guardian`）を適用する。
`tymon/jwt-auth` はguardごとに異なる秘密鍵・クレームを持たせられるため、
万一片方のトークンが漏洩してももう片方のguardには使用できない。

## 3. リフレッシュトークンの管理（ローテーション・再利用検知）

素朴なリフレッシュトークン実装（有効期限内なら何度でも使い回せる）は、
盗難時に攻撃者が正規ユーザーと区別なくアクセストークンを再発行され続けて
しまう。これを防ぐため、以下の設計を採用する。テーブル定義は
[02_data_model.md](./02_data_model.md) の `REFRESH_TOKENS` を参照。

### 3.1 使い切り（ローテーション）

- リフレッシュトークンは**1回使い切り**とする
- `/api/{guard}/refresh` が呼ばれるたびに、使用済みトークンを
  `revoked_at` で無効化し、新しいリフレッシュトークンを発行する
- 新トークンは元のトークンと同じ `family_id`（ログインセッション単位の
  系統ID）を引き継ぐ

### 3.2 再利用検知

- すでに `revoked_at` が設定済み（＝使用済み）のリフレッシュトークンが
  再度使われた場合、**盗難発生とみなし**、同じ `family_id` を持つ
  現在有効なトークンをすべて失効させる
- これにより、正規ユーザー・攻撃者のいずれかが古いトークンを再送した
  時点で、そのログインセッション全体を強制ログアウトできる
- `family_id` 単位で失効させることで、他デバイスの正常なセッションを
  巻き込まない（`user_id` 単位で全失効させると無関係なセッションまで
  ログアウトしてしまうため採用しない）

### 3.3 検知に依存しない補完策

再利用検知は「窃取者と正規ユーザーが同じトークンを使うタイミングが
来て初めて発動する」ため、正規ユーザーが二度とアプリを操作しなければ
検知は働かない。この限界を補うため以下も併用する。

- `REFRESH_TOKENS` に `family_expires_at`（系統発行時に固定する絶対有効
  期限）を持たせ、再利用検知が働かなくても一定期間で強制的にセッションを
  終了させる
- リフレッシュ時のIPアドレス・User-Agentを記録し、大きく変化した場合は
  追加認証やアカウント所有者への通知を検討する（詳細は
  [06_open_questions.md](./06_open_questions.md)）

### 3.4 アカウント設定画面からの手動失効

保護者・スタッフとも、自分の `family_id`（＝ログインセッション）一覧を
確認し、身に覚えのないセッションを手動で失効できる機能を設ける
（詳細は [06_open_questions.md](./06_open_questions.md)）。

## 4. テナントスコープ（園側）

園スタッフのクエリには常に自分の所属する `kindergarten_id` の条件が
かかるようにする。個別クエリでの条件付け忘れを防ぐため、
Eloquent の Global Scope として一括適用する。

```php
// 例: Child, Album, Photo, ChildInvitation モデルに適用
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($staffId = auth('staff')->id()) {
            $builder->where(
                $model->getTable().'.kindergarten_id',
                auth('staff')->user()->kindergarten_id
            );
        }
    }
}
```

## 5. 写真の閲覧・購入認可（保護者側の核心ロジック）

`PhotoPolicy` で「その保護者が、その写真に写っている園児のいずれかと
有効に紐づいているか」を判定する。

```php
class PhotoPolicy
{
    public function view(Guardian $guardian, Photo $photo): bool
    {
        return DB::table('guardian_child')
            ->join('photo_child_tags', 'guardian_child.child_id', '=', 'photo_child_tags.child_id')
            ->where('photo_child_tags.photo_id', $photo->id)
            ->where('guardian_child.guardian_id', $guardian->id)
            ->whereNull('guardian_child.unlinked_at')
            ->exists();
    }

    public function purchase(Guardian $guardian, Photo $photo): bool
    {
        return $this->view($guardian, $photo);
    }

    public function download(Guardian $guardian, Photo $photo): bool
    {
        // 購入済み(entitlements)であることに加え、現時点で紐づけが有効か
        // どうかは問わない（購入済みコンテンツへのアクセス権は維持する方針。
        // 03_invitation_flow.md §5 参照）
        return Entitlement::where('guardian_id', $guardian->id)
            ->where('photo_id', $photo->id)
            ->exists();
    }
}
```

一覧APIについても、都度Policyを回すのではなく `guardian_child` を
`INNER JOIN` した時点で「見えるべき写真だけ」に絞り込んだクエリを組み、
「取得はできるが表示だけ制御する」という実装は避ける
（他人の子の顔写真が万一にもレスポンスに含まれることを構造的に防ぐ）。

## 6. プレビュー画像とオリジナル画像のアクセス制御

写真という個人情報の性質上、以下の2段階でアクセスを制御する。

| 種別 | 用途 | アクセス制御 |
|---|---|---|
| プレビュー（透かし入り・低解像度） | 購入前の一覧・カート画面表示 | 要ログイン。§5の一覧APIで絞り込み済みの写真のみ配信。直リンクでも推測不可なパス＋署名付きURL |
| オリジナル（高解像度・透かしなし） | 購入後のダウンロード | `entitlements` の存在確認後、有効期限付きの署名URL（S3 presigned URL / CloudFront signed URL）を都度発行 |

オリジナル画像を指すURLを永続的に発行しない（都度署名し直す）ことで、
URL漏洩時の被害を時間的に限定する。

## 7. パスワード・アカウントセキュリティ

- 保護者・スタッフともに Laravel標準のパスワードハッシュ（bcrypt/argon2）を使用
- メール確認（`email_verified_at`）を必須にし、なりすまし登録を抑止する
- 招待受諾（アカウント新規作成）時にメール確認を要求すると体験が悪化するため、
  「メール確認前でも紐づけ・プレビュー閲覧は可能、購入時のみメール確認必須」
  という段階的な制限も選択肢になる（[06_open_questions.md](./06_open_questions.md) に記載）
