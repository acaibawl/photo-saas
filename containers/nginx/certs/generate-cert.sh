#!/bin/bash

set -euo pipefail

script_dir="$(cd "$(dirname "$0")" && pwd)"
cert_file="$script_dir/photo-saas.local.crt"
key_file="$script_dir/photo-saas.local.key"
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
  backend.local

# Append mkcert root CA so this single file can also be reused as Node's extra CA bundle.
cat "$caroot/rootCA.pem" >> "$cert_file"
