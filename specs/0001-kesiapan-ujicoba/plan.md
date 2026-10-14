# Plan — 0001 Kesiapan ujicoba

1. Perbaiki fixture & uji fitur agar tidak bergantung pada nilai identity.
2. Perbaikan bug: sinkron row_version saat kunci rincian; jam aplikasi pada validasi ulang; model memuat ulang kolom default DB setelah insert.
3. Fortify: halaman login Inertia, login email/NIP, rate limit.
4. `PersetujuanRbaService`, `KotakMasukService`, `PersetujuanController`, halaman Beranda, Persetujuan (mobile-first), Pergeseran, Standar Harga, RBA.
5. `DemoSeeder` + `docker-compose.yml`.
6. Verifikasi: Pest, uji SQL, uji domain, build Vite, alur browser Chromium (desktop & ponsel).
