#!/usr/bin/env bash

set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/apps/proyek-baru}"
ALLOW_BUILD="${ALLOW_BUILD:-0}"
RUN_MIGRATE="${RUN_MIGRATE:-0}"
RUN_SEED="${RUN_SEED:-0}"

cd "$APP_DIR"

echo "[1/7] Pull latest code"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  git pull --ff-only
else
  echo "Folder ini belum terhubung ke Git. Lanjut pakai source yang sudah ada di server."
fi

echo "[2/7] Validate host dependencies"
if [[ ! -f vendor/autoload.php ]]; then
  echo "vendor/autoload.php tidak ditemukan di host app."
  echo "Deploy rutin di VPS kecil tidak melakukan composer install otomatis."
  echo "Pulihkan vendor lebih dulu atau jalankan bootstrap terpisah."
  exit 1
fi

if [[ "$ALLOW_BUILD" == "1" ]]; then
  echo "[3/7] Build app image (explicitly allowed)"
  sudo docker compose build media-intelligent
else
  echo "[3/7] Skip image build on VPS"
  if ! sudo docker image inspect media_intelligent_app:latest >/dev/null 2>&1; then
    echo "Image media_intelligent_app:latest belum ada."
    echo "Jalankan sekali dengan ALLOW_BUILD=1 jika memang butuh bootstrap image baru."
    exit 1
  fi
fi

echo "[4/7] Start database and redis"
sudo docker compose up -d postgres redis

echo "[5/7] Start application"
sudo docker compose up -d media-intelligent

if [[ "$RUN_MIGRATE" == "1" ]]; then
  echo "[6/7] Run database migrations"
  sudo docker compose exec -T media-intelligent php artisan migrate --force

  if [[ "$RUN_SEED" == "1" ]]; then
    sudo docker compose exec -T media-intelligent php artisan db:seed --force
  fi
else
  echo "[6/7] Skip migrations"
fi

echo "[7/7] Start scheduler and workers"
sudo docker compose up -d \
  media-intelligent-scheduler \
  media-intelligent-worker \
  media-intelligent-apify-worker \
  media-intelligent-ai-worker \
  media-intelligent-notification-worker

echo
echo "Runtime status:"
sudo docker compose ps
