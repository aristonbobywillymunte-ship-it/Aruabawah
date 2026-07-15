# Deploy VPS

Alur deploy yang aman dan bisa diulang untuk VPS:

1. Pastikan `.env` di server sudah benar.
2. Pastikan folder app ada di `~/apps/proyek-baru`.
3. Jalankan:

```bash
cd ~/apps/proyek-baru
bash scripts/deploy-vps.sh
```

Catatan:

- Jika folder server belum berupa clone Git, script akan melewati langkah `git pull` dan tetap melanjutkan deploy dari source yang sudah ada.
- Port web default mengikuti `APP_PORT` di `.env`; jika tidak ada, fallback ke `80`.
- `docker-compose.yml` membaca `APP_KEY`, `APP_DEBUG`, host database, dan queue dari `.env`, jadi tidak ada lagi nilai sensitif yang ditanam langsung di compose.
- Image app dipakai ulang oleh service web, scheduler, dan worker supaya hasil build konsisten.
