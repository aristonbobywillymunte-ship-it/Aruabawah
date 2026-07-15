#!/usr/bin/env bash

set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/apps/proyek-baru}"

cd "$APP_DIR"

echo "[1/6] Pull latest code"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  git pull --ff-only
else
  echo "Folder ini belum terhubung ke Git. Lanjut pakai source yang sudah ada di server."
fi

echo "[2/6] Build app image"
sudo docker compose build media-intelligent

echo "[3/6] Start database and redis"
sudo docker compose up -d postgres redis

echo "[4/6] Install PHP dependencies on mounted app volume"
sudo docker compose run --rm media-intelligent composer install --no-interaction --optimize-autoloader

echo "[5/6] Run migrations and seeders"
sudo docker compose run --rm media-intelligent php artisan migrate --force --seed

echo "[6/6] Start application and workers"
sudo docker compose up -d

echo
echo "Runtime status:"
sudo docker compose ps
