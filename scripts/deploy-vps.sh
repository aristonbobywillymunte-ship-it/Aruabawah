#!/usr/bin/env bash

set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/apps/proyek-baru}"
ALLOW_BUILD="${ALLOW_BUILD:-0}"
RUN_MIGRATE="${RUN_MIGRATE:-0}"
RUN_SEED="${RUN_SEED:-0}"
BUILD_ARCHIVE="${BUILD_ARCHIVE:-}"

cd "$APP_DIR"

echo "[1/8] Pull latest code"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  git pull --ff-only
else
  echo "Folder ini belum terhubung ke Git. Lanjut pakai source yang sudah ada di server."
fi

echo "[2/8] Validate host dependencies"
if [[ ! -f vendor/autoload.php ]]; then
  echo "vendor/autoload.php tidak ditemukan di host app."
  echo "Deploy rutin di VPS kecil tidak melakukan composer install otomatis."
  echo "Pulihkan vendor lebih dulu atau jalankan bootstrap terpisah."
  exit 1
fi

echo "[3/8] Sync public build artifact if provided"
if [[ -n "$BUILD_ARCHIVE" ]]; then
  if [[ ! -f "$BUILD_ARCHIVE" ]]; then
    echo "Build archive tidak ditemukan: $BUILD_ARCHIVE"
    exit 1
  fi

  mkdir -p public
  rm -rf public/build
  tar -C public -xzf "$BUILD_ARCHIVE"
  find public/build -name '._*' -delete

  if [[ ! -f public/build/manifest.json ]]; then
    echo "manifest.json tidak ditemukan setelah extract build artifact."
    exit 1
  fi
else
  echo "Tidak ada build archive yang dikirim. Pakai public/build yang sudah ada di server."
fi

if [[ "$ALLOW_BUILD" == "1" ]]; then
  echo "[4/8] Build app image (explicitly allowed)"
  sudo docker compose build media-intelligent
else
  echo "[4/8] Skip image build on VPS"
  if ! sudo docker image inspect media_intelligent_app:latest >/dev/null 2>&1; then
    echo "Image media_intelligent_app:latest belum ada."
    echo "Jalankan sekali dengan ALLOW_BUILD=1 jika memang butuh bootstrap image baru."
    exit 1
  fi
fi

echo "[5/8] Start database and redis"
sudo docker compose up -d postgres redis

echo "[6/8] Start application"
sudo docker compose up -d media-intelligent

if [[ "$RUN_MIGRATE" == "1" ]]; then
  echo "[7/8] Run database migrations"
  sudo docker compose exec -T media-intelligent php artisan migrate --force

  if [[ "$RUN_SEED" == "1" ]]; then
    sudo docker compose exec -T media-intelligent php artisan db:seed --force
  fi
else
  echo "[7/8] Skip migrations"
fi

echo "[8/8] Start scheduler and workers"
sudo docker compose up -d \
  media-intelligent-scheduler \
  media-intelligent-worker \
  media-intelligent-apify-worker \
  media-intelligent-ai-worker \
  media-intelligent-notification-worker

echo
echo "Runtime status:"
sudo docker compose ps
