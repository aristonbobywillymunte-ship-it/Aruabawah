# Deploy VPS

Alur deploy rutin yang aman untuk VPS kecil:

1. Pastikan `.env` di server sudah benar.
2. Pastikan folder app ada di `~/apps/proyek-baru`.
3. Jalankan dari lokal:

```bash
cd /Users/unity/Documents/proyek\ baru
bash scripts/deploy-vps-from-local.sh
```

Alternatif jika build artifact sudah tersedia di server:

```bash
cd ~/apps/proyek-baru
bash scripts/deploy-vps.sh
```

Perilaku default script:

- `git pull --ff-only` jika folder server memang repo Git.
- Mengekstrak `public/build` dari archive yang dikirim lokal jika `BUILD_ARCHIVE` diset.
- Tidak melakukan `docker compose build`.
- Tidak melakukan `composer install`.
- Tidak menjalankan migration atau seeder kecuali diminta eksplisit.
- Menyalakan service bertahap: `postgres` + `redis`, lalu `app`, lalu scheduler dan worker.

Mode opsional:

```bash
SSH_KEY="/path/key.pem" SERVER="ubuntu@host" bash scripts/deploy-vps-from-local.sh
ALLOW_BUILD=1 bash scripts/deploy-vps.sh
RUN_MIGRATE=1 bash scripts/deploy-vps.sh
RUN_MIGRATE=1 RUN_SEED=1 bash scripts/deploy-vps.sh
```

Catatan penting:

- Deploy rutin di VPS 1 core sebaiknya tidak build image besar.
- `scripts/deploy-vps-from-local.sh` adalah jalur utama yang aman karena ikut mengirim `public/build` tanpa build di VPS.
- Host app harus sudah punya `vendor/autoload.php`. Jika belum ada, lakukan bootstrap terpisah, jangan dipaksa saat deploy rutin.
- Port web default mengikuti `APP_PORT` di `.env`; jika tidak ada, fallback ke `80`.
- `docker-compose.yml` membaca `APP_KEY`, `APP_DEBUG`, host database, dan queue dari `.env`.
- Image app dipakai ulang oleh service web, scheduler, dan worker supaya hasil runtime konsisten.
