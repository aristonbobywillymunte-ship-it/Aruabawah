# Project Memory

## Konteks Fitur Client Project Assignment
- **Alasan & Root Cause**: Client memerlukan penunjukan proyek manual oleh akun internal (Admin) tanpa menghapus database asal proyek saat dilepas (`detach`). Integrasi antar-halaman berbasis Livewire SPA (`wire:navigate`) menyebabkan komponen JavaScript dari pihak ketiga (Select2) gagal diinisialisasi ulang karena `livewire:initialized` hanya jalan sekali di awal. 
- **Solusi**: Menggunakan fitur bawaan Livewire 3 yaitu `@assets` dan `@script` untuk melampirkan *dependencies* CDN secara *lazy-load* dan aman, dipadukan dengan pengumpulan ID dalam bentuk *array* (`selectedProjectIds`) agar mendukung interaksi multi-select/pillbox. Batas maksimal proyek klien (`max_projects`) selalu mengacu pada metode turunan `getEffectiveMaxProjects()` dan diverifikasi ketat di back-end.
