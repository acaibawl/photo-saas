このドキュメントは、リフレッシュトークンの理解を深めるにあたってのAIとの会話ログです。


 LaravelでJWT認証を採用したいと考えています。リフレッシュトークンを自分が正しく理解できていない気がするので教えていただきたいのですが、リフレッシュトークンが盗まれたらその後は認証トークンとリフレッシュトーク
  ン発行され放題になってしまいませんか？

● 鋭い指摘です。結論から言うと、素のJWT+リフレッシュトークンの設計をそのまま使うと、まさにご懸念の通り「盗まれたら延々と再発行され続ける」問題が起きます。これを防ぐには追加の仕組みが必須です。

  なぜ危険なのか

  - アクセストークンは検証だけで発行者を確認しないステートレスな仕組み
  - リフレッシュトークンも「有効期限内かどうか」だけをチェックする単純な実装だと、盗んだ攻撃者は正規ユーザーと区別がつかない
  - 結果、攻撃者はリフレッシュトークンを使ってアクセストークンを取り続け、さらに新しいリフレッシュトークンも受け取り続けられる

  対策1: リフレッシュトークンローテーション + 再利用検知(最重要)

  仕組み:
  1. リフレッシュトークンは1回使い切りにする
  2. /refresh が呼ばれるたびに、古いリフレッシュトークンを無効化し、新しいものを発行する
  3. すでに無効化(使用済み)されたリフレッシュトークンが再度使われたら「盗難発生」とみなし、そのユーザーの全トークン(トークンファミリー全体)を強制失効させる

  このステップ3が肝です。正規ユーザーと攻撃者が同じリフレッシュトークンを使い回すタイミングが必ず発生するため、そこで検知できます。

  正規ユーザー: RT1で更新 → RT2発行、RT1は使用済みにマーク
  攻撃者がRT1を盗んで使用 → RT1はすでに使用済み → 異常検知 → RT1〜RT2系統を全部失効

  対策2: リフレッシュトークンをステートフルに管理する

  JWTは本来ステートレスですが、リフレッシュトークンだけはDBで管理するのが現実的です(アクセストークンはステートレスのまま短命にする)。

  // refresh_tokens テーブル例
  Schema::create('refresh_tokens', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained();
      $table->string('token_hash'); // 平文保存しない、hash()して保存
      $table->string('family_id');  // ローテーション系統をまとめるID
      $table->timestamp('expires_at');
      $table->timestamp('revoked_at')->nullable();
      $table->timestamps();
  });

  これで「特定のリフレッシュトークンを即座に失効させる」「ユーザーの全セッションを強制ログアウトさせる」ことが可能になります。

  対策3: 保存場所を守る(そもそも盗まれにくくする)

  - httpOnly + Secure + SameSite=Strict の Cookie に保存する(JS からアクセスできないので XSS 経由の窃取を防げる)
  - localStorage/sessionStorage への保存は避ける(XSSで一発アウト)

  対策4: 異常検知の補強(任意)

  - リフレッシュ時のIPアドレス・User-Agentを記録し、大きく変化したら追加認証や通知を挟む
  - アクセストークンの寿命を短く(5〜15分程度)し、被害の窓を狭める

  Laravelでの実装選択肢

  - 自前実装するなら tymon/jwt-auth + 上記の refresh_tokens テーブルでローテーション管理
  - 車輪の再発明を避けたいなら Laravel Sanctum(SPA/モバイル向けでほぼ同じ課題をライブラリ側で解決済み)や Laravel Passport(OAuth2、refresh token rotation を標準サポート)も検討候補になります

  ---
  まとめると、盗難対策の核心は「リフレッシュトークンの使い切り化 + 再利用検知」です。これがないJWTリフレッシュ実装は、ご指摘の通り実用上かなり危険です。実装方針(自前 vs Sanctum vs
  Passport)について詳しく知りたい場合は教えてください。

✻ Cogitated for 33s

❯ 提示していただいたrefresh_tokensテーブル例について質問です。constrainedってなんでしたっけ？ あと、family_idの用途について詳しく教えてください

● constrained() について

  foreignId() と組み合わせて使う 外部キー制約を貼るためのショートカットメソッドです。

  $table->foreignId('user_id')->constrained();

  これは以下を自動で行います:

  1. user_id というカラム名から、規約に従って参照先テーブルを推測(user_id → users テーブル)
  2. 参照先の主キー(通常 id)に対して外部キー制約を張る

  つまり素朴に書くとこうなる処理を1行に圧縮したものです:

  $table->unsignedBigInteger('user_id');
  $table->foreign('user_id')->references('id')->on('users');

  テーブル名が規約と異なる場合は明示できます:

  $table->foreignId('author_id')->constrained('users'); // authors ではなく users を参照させたい場合

  カスケード削除などもチェーンできます:

  $table->foreignId('user_id')->constrained()->onDelete('cascade');
  // ユーザーが削除されたら紐づく refresh_tokens も自動削除

  リフレッシュトークン管理では、退会処理時にトークンの掃除漏れを防げるので onDelete('cascade') を付けておくと安全です。

  family_id の用途

  これはトークンローテーションの「系統」をまとめて追跡・失効させるためのIDです。1回のログインから始まる一連のリフレッシュトークンの連鎖に、共通のIDを振っておきます。

  具体的な流れ

  ① ログイン
     RT1 発行  (family_id = F1, revoked_at = null)

  ② RT1 で /refresh
     RT1 を revoked_at = now() にする
     RT2 発行  (family_id = F1, revoked_at = null)  ← 同じ family_id を引き継ぐ

  ③ RT2 で /refresh
     RT2 を revoked_at = now() にする
     RT3 発行  (family_id = F1, revoked_at = null)

  同じセッションから生まれたトークンは全部 family_id = F1 で紐づいています。

  盗難検知にどう使うか

  もし攻撃者が すでに使用済み(revoked済み)の RT1 やRT2 を使って /refresh を叩いてきたら、次のロジックが発動します。

  $token = RefreshToken::where('token_hash', hash('sha256', $incomingToken))->first();

  if ($token && $token->revoked_at !== null) {
      // 使用済みトークンの再利用 = 盗難の可能性
      RefreshToken::where('family_id', $token->family_id)
          ->whereNull('revoked_at')
          ->update(['revoked_at' => now()]);
      // → 同じ family_id を持つ「現在有効な」トークンも含め全滅させる
      // つまり RT3 も無効化され、正規ユーザーも再ログインが必要になる
  }

  なぜ family_id が要るのか(user_id だけではダメな理由)

  user_id だけで判定すると、ユーザーが複数デバイス(PC・スマホなど)でログインしている場合に問題が起きます。

  - user_id 単位で「全部失効」してしまうと、盗難と無関係な別デバイスのセッションまで巻き込んで強制ログアウトさせてしまう
  - family_id 単位で失効させれば、盗まれたセッション(の系統)だけをピンポイントで無効化でき、他デバイスのログインは無傷のまま維持できる

  つまり family_id は「ログインセッションごとの血統書」のようなもので、「どのRTの子孫がどこまで有効か」「どこで異常が起きたらどの範囲を巻き込むべきか」を管理するためのキーです。

  新しいログイン(別デバイスからのログインなど)のたびに family_id を新規発行(UUIDなど)すれば、デバイスごとに独立したローテーション系統を維持できます。