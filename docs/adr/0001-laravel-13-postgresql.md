# ADR 0001 — Laravel 13 + PostgreSQL, aturan uang dijaga di database

- Status: Diterima (14 Okt 2026)
- Konteks: aplikasi keuangan <50 pengguna, dipelihara bertahun-tahun oleh tim Pemda/RSUD; aturan pagu harus tahan terhadap bug aplikasi dan akses bersamaan.
- Keputusan: monolit modular Laravel 13 (PHP 8.3+), PostgreSQL 16; skema inti ditulis sebagai SQL murni (`database/sql`) dengan constraint, trigger, dan fungsi ledger; uji SQL berjalan tanpa Laravel.
- Konsekuensi: perubahan skema berupa berkas SQL baru + migrasi baru; uji fitur wajib PostgreSQL nyata; tim perlu memahami PL/pgSQL dasar.
