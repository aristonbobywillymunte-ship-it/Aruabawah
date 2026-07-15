#!/usr/bin/env bash

set -euo pipefail

SSH_KEY="${SSH_KEY:-/Users/unity/Downloads/imrc 3.pem}"
SERVER="${SERVER:-ubuntu@3.27.115.35}"
REMOTE_APP_DIR="${REMOTE_APP_DIR:-/home/ubuntu/apps/proyek-baru}"
ARCHIVE_PATH="${ARCHIVE_PATH:-/tmp/arusbawah-public-build.tgz}"

cd "$(dirname "$0")/.."

if [[ ! -f public/build/manifest.json ]]; then
  echo "public/build/manifest.json tidak ditemukan di lokal."
  echo "Build asset dulu di lokal sebelum deploy ringan."
  exit 1
fi

echo "[1/4] Pack public/build"
tar -C public -czf "$ARCHIVE_PATH" build

echo "[2/4] Upload build archive"
scp -i "$SSH_KEY" -o StrictHostKeyChecking=no "$ARCHIVE_PATH" "$SERVER:$ARCHIVE_PATH"

echo "[3/4] Sync deploy script and run remote deploy"
scp -i "$SSH_KEY" -o StrictHostKeyChecking=no scripts/deploy-vps.sh "$SERVER:$REMOTE_APP_DIR/scripts/deploy-vps.sh"
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$SERVER" \
  "cd '$REMOTE_APP_DIR' && BUILD_ARCHIVE='$ARCHIVE_PATH' bash scripts/deploy-vps.sh"

echo "[4/4] Cleanup local temp archive"
rm -f "$ARCHIVE_PATH"

echo
echo "Deploy ringan selesai."
