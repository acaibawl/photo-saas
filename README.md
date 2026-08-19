# photo-saas

## Local HTTPS

This repository expects the local Nginx certificate at `containers/nginx/certs/photo-saas.local.crt` and the matching key at `containers/nginx/certs/photo-saas.local.key`.

On macOS, generate them with `mkcert` so the certificate chains to a locally trusted development CA instead of a self-signed leaf certificate.

Before any Docker build or run step, configure host name resolution so your browser can resolve local domains.

- Add `frontend.local`, `backend.local`, and `storage.local` to `/etc/hosts` (or your local DNS) and map them to `127.0.0.1`.
- Treat `photo-saas.local` as a separate domain. If you need to access it directly in a browser, add its own mapping separately.

```bash
brew install mkcert nss
mkcert -install
bash ./containers/nginx/certs/generate-cert.sh
```

Run `mkcert -install` once from an interactive terminal. It may ask for your macOS password to add the local development CA to Keychain Access.

`generate-cert.sh` then issues a certificate for `frontend.local`, `backend.local`, and `storage.local` using that trusted local CA.

## Local object storage

Development uses MinIO as an S3-compatible object store. Start the stack with:

```bash
docker compose up -d
```

The `minio-init` service creates the `photo-saas` bucket and applies the local CORS policy automatically.

- MinIO Console: `http://localhost:9001`
- MinIO credentials: `minio` / `minio-password`
- Browser object endpoint: `https://storage.local`

If you have an older self-signed certificate in Keychain Access, remove it before retrying so the browser only sees the `mkcert`-signed certificate.

## 証明書の再生成が必要な場合は下記を実行

```bash
mkcert -install
bash ./containers/nginx/certs/generate-cert.sh
docker compose restart nginx
```

## DB migration

```bash
docker compose run --rm backend php artisan migrate:fresh --seed
```

テスト用

```bash
docker compose run --rm backend php artisan migrate:fresh --seed --env=testing
```

## ide-helper用意

```bash
docker compose run --rm backend php artisan ide-helper:generate
docker compose run --rm backend php artisan ide-helper:meta
```

### モデルの更新

```bash
docker compose run --rm backend php artisan ide-helper:models --write --reset
```

## テスト実行

```bash
docker compose run --rm backend php artisan test
```

## Laravel Boost実行

phpコンテナで実行

```bash
php artisan boost:mcp
```

## Stripe Webhookのローカル受信（Stripe CLI）

Stripe Connectのオンボーディング状況（`account.updated`）や決済完了（`checkout.session.completed`）はWebhook経由でDBに反映される。ローカルで動作確認するには [Stripe CLI](https://docs.stripe.com/stripe-cli) を使ってイベントを転送する。

1. Stripe CLIをインストールし、Stripeアカウントでログインする。

   ```bash
   brew install stripe/stripe-cli/stripe
   stripe login
   ```

2. `backend/.env` の `STRIPE_SECRET` にStripeのテスト用シークレットキー（`sk_test_...`）を設定する。

3. Webhookをbackendコンテナへ転送する。このコマンドは起動したままにしておく。

   ```bash
   stripe listen --forward-to https://backend.local/public/stripe/webhook
   ```

   起動すると `Ready! ... Your webhook signing secret is whsec_...` と表示される。この値を `backend/.env` の `STRIPE_WEBHOOK_SECRET` に設定し、設定キャッシュをクリアする。

   ```bash
   docker compose exec php php artisan config:clear
   ```

4. 別ターミナルで疎通確認用のイベントを発火できる。

   ```bash
   stripe trigger checkout.session.completed
   stripe trigger account.updated
   ```

   `stripe trigger` は架空のテスト用オブジェクトに対してイベントを発火するため、実際にアプリ内で作成した注文やConnectアカウントとはIDが一致しない。そのため、`stripe listen` 側で200が返ってもDBの状態（`orders.status`や`kindergartens.stripe_onboarding_completed_at`）が更新されないことがある。実データで確認したい場合は、アプリの購入導線やStripeダッシュボード上の実アカウント操作を通じて実イベントを発生させる。

5. ルートパスに `/api` プレフィックスは付かない（`backend/bootstrap/app.php` の `apiPrefix: ''` による）。`--forward-to` のURLを `https://backend.local/api/public/stripe/webhook` のように書くと404になるので注意する。
