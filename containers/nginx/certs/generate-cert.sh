#!/bin/bash

set -euo pipefail

script_dir="$(cd "$(dirname "$0")" && pwd)"
cert_file="$script_dir/photo-saas.local.crt"
key_file="$script_dir/photo-saas.local.key"
ca_bundle_file="$script_dir/photo-saas.local-ca-bundle.crt"
caroot="$(mkcert -CAROOT)"

if ! command -v mkcert >/dev/null 2>&1; then
  echo "mkcert is required. Install it with: brew install mkcert nss" >&2
  exit 1
fi

if [[ ! -f "$caroot/rootCA.pem" ]]; then
  echo "mkcert local CA is not initialized." >&2
  echo "Run 'mkcert -install' once in your terminal, then rerun this script." >&2
  exit 1
fi

# Generate a CA-signed server certificate trusted by the local mkcert root.
mkcert \
  -cert-file "$cert_file" \
  -key-file "$key_file" \
  frontend.local \
  backend.local \
  storage.local

# Keep the Nginx certificate as a leaf certificate. Node uses the separate CA bundle.
cp "$caroot/rootCA.pem" "$ca_bundle_file"
