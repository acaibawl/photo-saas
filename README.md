# photo-saas

## Local HTTPS

This repository expects the local Nginx certificate at `containers/nginx/certs/photo-saas.local.crt` and the matching key at `containers/nginx/certs/photo-saas.local.key`.

On macOS, generate them with `mkcert` so the certificate chains to a locally trusted development CA instead of a self-signed leaf certificate.

Before any Docker build or run step, configure host name resolution so your browser can resolve local domains.

- Add `frontend.local` and `backend.local` to `/etc/hosts` (or your local DNS) and map both to `127.0.0.1`.
- Treat `photo-saas.local` as a separate domain. If you need to access it directly in a browser, add its own mapping separately.

```bash
brew install mkcert nss
mkcert -install
bash ./containers/nginx/certs/generate-cert.sh
```

Run `mkcert -install` once from an interactive terminal. It may ask for your macOS password to add the local development CA to Keychain Access.

`generate-cert.sh` then issues a certificate for `frontend.local` and `backend.local` using that trusted local CA.

If you have an older self-signed certificate in Keychain Access, remove it before retrying so the browser only sees the `mkcert`-signed certificate.
