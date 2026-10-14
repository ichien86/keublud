# Aplikasi Keuangan BLUD RSUD — Inti Fase 1

Implementasi awal berdasarkan **PRD v1.9** dan **TDD v1.5** (Laravel 13 + PostgreSQL 16, metodologi SDLC 2.0).

## Cakupan tahap ini

| Modul | Isi | Status |
|---|---|---|
| Skema database (`database/sql`) | Master, kodefikasi & pemetaan banyak-ke-satu, standar harga, RBA & rincian, pagu jenis + ledger, revisi, pergeseran & pasangan, workflow, audit berantai hash, dokumen formal | ✅ 42 uji SQL |
| Domain murni | Uang (sen), kalender hari kerja, skor kecocokan, kalkulator revisi, validator pasangan | ✅ 34 uji unit |
| Layanan | PaguLedger, penetapan & persetujuan RBA, revisi rincian, pergeseran & pasangan, persetujuan otomatis, workflow + delegasi + pemisahan tugas, standar harga, kotak masuk | ✅ 63 uji Pest (unit, arsitektur, fitur) |
| Antarmuka | Login (email/NIP), Beranda pagu, Edit Rincian, **Kotak masuk persetujuan mobile-first** (PIN), Pergeseran, Standar Harga, RBA | ✅ diuji di Chromium (desktop & ponsel) |
| **Impor RBA format lama** | Baca Lampiran Rincian Anggaran Belanja (kolom dinamis, sheet & kelompok nilai Penetapan/Perubahan), staging, lengkapi kodefikasi dari file, petakan label unit, rincikan ke item standar harga atau ajukan sebagai usulan item, rekonsiliasi per rekening, bentuk RBA draf | ✅ diuji dengan struktur file RBA asli (27 sheet cocok rekonsiliasinya) |

**Belum termasuk:** dokumen formal & cetakan Berkurang/Bertambah, perubahan RBA & ambang batas, WebAuthn/passkey dan 2FA, halaman administrasi (pengguna, unit, kodefikasi, pemetaan), impor Excel usulan standar dari layar (endpoint sudah ada), serta seluruh Fase 2 (penatausahaan, ketersediaan dana, pajak, utang/piutang, SP3B).

## Ujicoba cepat (Windows) — Docker Desktop

1. Pasang **Docker Desktop** (WSL 2), lalu buka PowerShell di folder ini (`C:\Users\HP\Apl\keuangan-blud`).
2. Jalankan:
   ```powershell
   docker compose up --build
   ```
   Start pertama ±5–10 menit (unduh image, `composer install`, `npm ci`, migrasi, data demo).
3. Buka **http://localhost:8000**. Hentikan dengan `Ctrl+C`; data tersimpan di volume `pgdata`.
   Mengulang dari nol: `docker compose down -v` lalu `docker compose up`.

Uji otomatis di dalam container: `docker compose exec app php artisan test`.

## Ujicoba tanpa Docker (Laragon / instalasi manual)

Butuh PHP ≥ 8.3 (ekstensi `pdo_pgsql`, `intl`, `zip`, `gd`), Composer 2, Node.js 22, PostgreSQL 16.

```powershell
composer install
copy .env.example .env      # sesuaikan DB_USERNAME / DB_PASSWORD
php artisan key:generate
# buat database keuangan_blud (pemiliknya = DB_USERNAME) lewat pgAdmin atau psql
php artisan migrate
php artisan db:seed --class=DemoSeeder
npm ci
npm run build
php artisan serve           # jendela 1
php artisan schedule:work   # jendela 2 (tolak/setujui otomatis setelah 5 hari kerja)
```

## Mencoba impor RBA format lama

1. Masuk sebagai `admin@demo.test` → menu **Impor RBA** → unggah file Excel RBA (mis. *ANGGARAN 2026 235M.xlsx*).
2. Pilih sheet (mis. *anggaran 2026 10122025*) dan kelompok nilai (*Penetapan*, atau *Perubahan* pada sheet pembanding). Periksa rekonsiliasi per rekening Pemda, isi tahun tujuan, lalu **Mulai impor**.
   Data demo sudah memiliki RBA 2026 definitif, jadi untuk ujicoba pakai tahun tujuan lain (mis. 2028).
3. Di halaman staging: **Lengkapi dari file** (membuat rekening Pemda/BLUD dan pemetaannya dari kode di file), petakan label penanggung jawab (UMUM, Keu, Binpro, …) ke unit, dan beri unit untuk baris tanpa label.
4. Masuk sebagai `penyusun@demo.test` → buka impor yang sama → rincikan baris ke item standar harga, atau **Ajukan baris belum dirinci sebagai usulan item** (baris "1 tahun" menjadi item bersatuan paket).
5. Masuk sebagai `perencana@demo.test` → **Standar Harga → Menunggu keputusan** → *Sahkan semua yang tidak mirip item lain*; usulan yang mirip diputuskan satu per satu.
6. Kembali sebagai `penyusun@demo.test` → staging → semua baris *Siap* → **Bentuk RBA** → halaman RBA → **Ajukan** (verifikator → perencana → Pimpinan BLUD).

File ±25 MB butuh `upload_max_filesize`/`post_max_size` ≥ 64M dan `memory_limit` ≥ 1024M (sudah diatur di image Docker; atur sendiri di php.ini bila tanpa Docker).
Setelah memperbarui kode: `docker compose up --build` (menjalankan migrasi baru dan memperbarui peran).

## Akun demo (kata sandi semua: `demo12345`)

| Masuk sebagai | Peran | Yang bisa dicoba |
|---|---|---|
| `farmasi@demo.test` (atau NIP `199407072019031007`) | Pengusul Farmasi | Edit rincian: geser volume antar-item (seimbang → langsung berlaku); naikkan total → dialog *kirim sebagai pergeseran* |
| `kafarmasi@demo.test`, `kalab@demo.test` | Kepala unit | Kotak masuk: setujui/tolak pasangan pergeseran 0001/PGS |
| `perencana@demo.test` | Perencana | Sahkan pasangan, setujui revisi Belanja Modal IGD, putuskan usulan standar harga, pasangkan permintaan Radiologi |
| `pimpinan@demo.test` — **PIN `123456`** | Pimpinan BLUD | Buka di ponsel/mode ponsel browser → Persetujuan → **Tetapkan RBA 2027** |
| `verifikator@demo.test`, `penyusun@demo.test`, `lab@`, `radiologi@`, `igd@`, `admin@` | Lainnya | Alur RBA, revisi unit lain |

Data demo adalah contoh — **bukan** kodefikasi, standar harga, atau unit riil RSUD. Jangan jalankan `DemoSeeder` di server produksi.

## Pengujian

| Perintah | Isi | Butuh |
|---|---|---|
| `composer test:domain` | Uji unit domain murni dengan runner mandiri | PHP saja |
| `composer test:sql` | Skema + 42 aturan yang dijaga database (constraint, trigger, ledger, pasangan, audit) | PostgreSQL (`PGHOST`, `PGUSER`, …) |
| `php artisan test` | Pest: unit, arsitektur antarmodul, fitur (revisi → pergeseran → pengesahan/persetujuan otomatis) | `composer install` + PostgreSQL `keuangan_blud_test` |

Uji fitur **wajib** memakai PostgreSQL nyata (bukan SQLite) karena aturan pagu dijaga constraint dan trigger.

## Peta kode

```
database/sql/            Skema inti (SQL murni, diuji langsung)
app/Modules/
  Shared/                Uang, KalenderKerja, Konfigurasi, Kunci (lock berurutan), Penomoran, Audit, Notifikasi
  Kodefikasi/            Rekening, pemetaan BLUD -> Pemda
  StandarHarga/          Pencocokan (pg_trgm + skor), usulan, impor Excel, keputusan batch
  Anggaran/              RBA, rincian, pagu jenis, PaguLedger, penetapan definitif
  Revisi/                Paket revisi rincian + halaman edit
  Pergeseran/            Permintaan, pasangan, pencadangan, job batas waktu & persetujuan otomatis
  Workflow/              Mesin persetujuan, delegasi, pemisahan tugas
resources/js/Pages/Revisi/Edit.vue   Halaman edit rincian
tests/sql/  tests/Unit/  tests/Feature/  tests/Arch/
docs/adr/  specs/        Artefak SDLC 2.0
```

## Berkas GitHub

Pipeline CI, template PR, dan CODEOWNERS ada di folder `.github/`. Ganti `@tech-lead` di `CODEOWNERS` dengan akun GitHub peninjau yang sebenarnya.

## Aturan yang tidak boleh dilanggar

Lihat **AGENTS.md** — berlaku untuk pengembang dan asisten AI.

## Asumsi yang masih perlu dikonfirmasi

- **T-1** Batas N hari kerja dihitung mulai hari kerja setelah pengajuan, berakhir 23:59:59 WIB hari kerja ke-N.
- **T-2** Bila pasangan ditolak, permintaan kembali TERBUKA dengan batas waktu awal.
- Pengusul pada alur persetujuan pasangan = pengusul permintaan TAMBAH (agar perencana yang memasangkan tetap dapat mengesahkan).
- Setiap permintaan pergeseran wajib berasal dari paket revisi, sehingga rincian dan pagu selalu bergerak bersama.
- Persetujuan otomatis berlaku untuk semua jenis belanja (PRD K-25); dapat dibatasi lewat konfigurasi `pergeseran.jenis_boleh_otomatis`.
