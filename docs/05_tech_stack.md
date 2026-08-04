# 技術選定

## 1. フロントエンド

| 項目 | 選定 | 理由 |
|---|---|---|
| フレームワーク | Nuxt 4 | 指定済み。保護者向け画面はSPA寄り、園紹介LPが必要ならSSR/SSGを部分的に利用 |
| 状態管理 | Pinia | Nuxt標準の組み合わせ、Vuexより簡潔 |
| APIクライアント | `$fetch` (ofetch) + `Authorization: Bearer` ヘッダー（アクセストークンはPiniaで保持） | Nuxtに組み込み済み、追加ライブラリ不要。リフレッシュトークンはhttpOnly Cookieでブラウザが自動送信するため別途ライブラリ不要 |
| UIコンポーネント | 任意（例: shadcn-vue, Naive UI） | ポートフォリオとしての見た目重視なら自由度の高いshadcn-vue系が無難 |
| QRコード読み取り | ブラウザ側は不要（QRは園側で印刷するのみ、保護者はスマホ標準カメラで読む） | 招待URLを直接開くだけなのでフロント側にQRスキャナ実装は不要 |
| QRコード生成 | バックエンド側で生成（後述） | 生成ロジックをサーバー側に閉じることでトークンの取り扱いを一元化 |

## 2. バックエンド

| 項目 | 選定 | 理由 |
|---|---|---|
| フレームワーク | Laravel 13系 | 指定済み |
| 認証 | tymon/jwt-auth | JWTアクセストークン＋DB管理のリフレッシュトークンでstaff/guardianの2guardを構成。詳細は[04_auth_and_authorization.md](./04_auth_and_authorization.md) |
| 画像処理 | Intervention Image v3 | サムネイル生成・透かし合成をLaravel資産で完結できる |
| QRコード生成 | `simplesoftwareio/simple-qrcode` または `endroid/qr-code` | PHP側でSVG/PNG生成し印刷用PDFに埋め込みやすい |
| PDF生成（印刷用） | `barryvdh/laravel-dompdf` 等 | QRコードを園児名付きで一覧印刷するため |
| キュー | Laravel Queue（Redisドライバ） | 画像アップロード後のサムネイル/透かし生成を非同期化 |
| 決済 | Stripe Checkout + Connect（Standard）+ Webhook | PCI DSS対応が不要、実装コストが低く個人開発向き。Connectにより園ごとの入金先分離を実現（詳細は[02_data_model.md 2.7](./02_data_model.md#27-決済の入金先はテナント園ごとに分離するstripe-connect)） |
| テスト | Pest または PHPUnit | Laravel標準、ポリシー（認可ロジック）のテストを重点的に書く |

## 3. インフラ・ストレージ

| 項目 | 選定 | 理由 |
|---|---|---|
| DB | PostgreSQL | 個人開発でも本番運用を想定するなら無料枠のホスティングが充実（Supabase/Neon等） |
| オブジェクトストレージ | Cloudflare R2 | S3互換API、エグレス（下り転送）無料でポートフォリオ運用コストを抑えられる |
| CDN/署名付きURL | Cloudflareまたは各ストレージの署名付きURL機能 | プレビュー・オリジナル配信を04章の方針で制御 |
| ホスティング（Laravel） | AWS ECS on Fargate | コンテナオーケストレーションの実務経験をポートフォリオとしてアピールできる。詳細構成は本章4節参照 |
| ホスティング（Nuxt） | Vercel / Cloudflare Pages | Nuxtとの親和性が高くデプロイが容易 |
| CI | GitHub Actions | Lint/テスト/デプロイの自動化。ポートフォリオとして開発プロセスの整備をアピールできる |

## 4. AWS ECS Fargate 構成とコスト試算

バックエンド（Laravel）はAWS ECS on Fargateにデプロイする。個人開発のため
コストを最小限に抑える構成とし、最小スペック（0.25 vCPU / 0.5GB、1タスク常時
起動）を前提とする。

### 4.1 主な構成要素と月額目安（東京リージョン、1USD≈¥150換算）

| 項目 | 月額目安 | 備考 |
|---|---|---|
| Fargate compute（0.25vCPU/0.5GB×730h） | 約¥1,700 | Fargateで選択可能な最小スペック |
| ALB（HTTPS終端・ヘルスチェック用） | 約¥2,700〜3,600 | 基本料金は稼働させる限り固定でかかる |
| NAT Gateway | 約¥6,800〜（**採用しない方針**） | 固定費がFargate本体より高額なため、下記4.2の方式で回避する |
| ECR/CloudWatch Logs等 | 約¥150〜450 | イメージ保存・ログ量が少なければ誤差程度 |
| **合計目安（NAT回避構成）** | **約¥4,500〜6,000/月** | |

上記は概算値であり、実際の請求前に[AWS Pricing Calculator](https://calculator.aws)
で最新料金を確認すること。またPostgreSQL（RDS等）を自前運用する場合は
別途月$15〜20（約¥2,000〜3,000）が加算される（本章3節のSupabase/Neon
無料枠案を採用する場合はこの限りではない）。

### 4.2 コストを抑えるための構成方針

- **NAT Gatewayを使わない**: FargateタスクをパブリックサブネットIに配置し
  パブリックIPを直接割り当てることで、NAT Gateway（固定費$45/月〜）なしで
  ECRイメージのpull等インターネットアクセスを確保する。セキュリティグループで
  受信をALBからのみに制限すれば実質的な露出は抑えられる
- **Fargate Spotの活用（検討中）**: compute費用を最大70%削減できるが、
  2分前通知でタスクが中断され得るため、常時可用性が求められない
  ポートフォリオ用途向けの選択肢として検討する（[06_open_questions.md](./06_open_questions.md)参照）

## 5. 意図的に採用しないもの（過剰設計の回避）

- **顔認識による自動タグ付け**: 精度・プライバシー影響ともに大きく、
  MVPでは園スタッフによる手動タグ付けで十分。将来拡張として
  [06_open_questions.md](./06_open_questions.md) に記載するに留める
- **マイクロサービス分割**: 個人開発の規模では単一Laravelアプリで十分。
  将来的に画像処理負荷が支配的になった場合のみワーカーを別プロセス/別ホストに
  切り出す
- **独自の決済処理**: Stripeに完全委譲し、カード情報を自前で扱わない
