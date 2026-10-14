# Technical Design Document (TDD)
## Aplikasi Keuangan BLUD RSUD Berbasis Web

**Versi:** 1.5 (Draf desain teknis)  
**Tanggal:** 14 Oktober 2026  
**Rujukan:** PRD Aplikasi Keuangan BLUD RSUD v1.9  
**Stack:** Laravel 13 (PHP 8.3+) + PostgreSQL  
**Metodologi:** SDLC 2.0 (spec-driven, AI-assisted, human-gated)  
**Status:** Draf untuk telaah tim pengembang

---

## Riwayat Perubahan

| Versi | Tanggal | Ringkasan |
|---|---|---|
| 1.0 | 14 Okt 2026 | Draf awal desain teknis berdasarkan PRD v1.5 |
| 1.1 | 14 Okt 2026 | Stack ditetapkan Laravel 13 dengan fitur ekosistem terbaru (PHP Attributes, Precognition, Inertia 2, Reverb, Pennant, Pulse, Boost); ditambahkan bagian 16 Metodologi Pengembangan SDLC 2.0 beserta struktur repositori, gerbang persetujuan, pipeline CI/CD, dan aturan penggunaan asisten AI |
| 1.2 | 14 Okt 2026 | Menyesuaikan PRD v1.6: penambahan item standar harga di tengah tahun, impor Excel usulan standar internal dengan keputusan batch, revisi rincian berlaku langsung hanya untuk Belanja Barang dan Jasa, dan kotak masuk persetujuan *mobile-first* untuk Pimpinan BLUD (PWA, Web Push, WebAuthn) |
| 1.3 | 14 Okt 2026 | Menyesuaikan PRD v1.7 berdasarkan contoh dokumen RBA BLUD: pemetaan rekening banyak-ke-satu ke rekening agregat Pemda, unit wajib per rincian, dokumen anggaran formal dan cetakan Berkurang/Bertambah (4.14), anggaran kas triwulan (4.15), parser dan staging impor RBA format lama (8.4) |
| 1.4 | 14 Okt 2026 | Menyesuaikan PRD v1.8: anggaran kas dihapus dan diganti pengendalian ketersediaan dana berbasis realisasi pendapatan (`posisi_dana`, `dana_mutasi`); jadwal perubahan RBA (`jadwal_perubahan`); Pergeseran ke-n sewaktu-waktu |
| 1.5 | 14 Okt 2026 | Menyesuaikan PRD v1.9: pengaturan cadangan kas minimum dengan rekening wajib (`cadangan_kas_aturan`, `cadangan_kas_rekening`); piutang tidak dihitung sebagai dana tersedia; rencana penarikan dana tidak mengikat untuk lampiran DPA/DPPA (4.16) |

---

## 1. Pendahuluan

### 1.1 Tujuan

Dokumen ini menerjemahkan kebutuhan PRD v1.5 menjadi rancangan teknis yang dapat langsung dipakai tim pengembang: arsitektur, struktur modul, skema basis data, algoritma aturan bisnis kritis, alur status, API, keamanan, operasional, dan strategi pengujian.

### 1.2 Prinsip desain

1. **Basis data sebagai penjaga terakhir.** Aturan yang menyangkut uang (sisa pagu tidak negatif, keseimbangan pergeseran, kunci periode) dijaga di aplikasi **dan** dengan constraint/trigger PostgreSQL, sehingga bug di aplikasi tidak dapat merusak data.
2. **Ledger, bukan timpa nilai.** Setiap perubahan pagu dicatat sebagai baris mutasi yang tidak dapat diubah; saldo pagu adalah agregat yang diperbarui dalam transaksi yang sama.
3. **Atomik dan terkunci berurutan.** Operasi yang menyentuh lebih dari satu pagu mengunci baris dalam urutan ID yang tetap untuk mencegah *deadlock*.
4. **Tidak ada hapus fisik untuk data keuangan.** Pembatalan dilakukan dengan status atau reversal.
5. **Monolit modular.** Satu aplikasi Laravel dengan batas modul yang tegas; cukup untuk <50 pengguna dan mudah dipecah jika kelak diperlukan.
6. **Konfigurasi di data, bukan di kode.** Matriks kewenangan, tarif pajak, aturan jurnal, format nomor, batas waktu, dan kalender kerja dikelola lewat tabel.
7. **Satu sumber aturan validasi.** Aturan validasi ditulis sekali di Form Request server dan dipakai ulang oleh klien melalui Precognition, sehingga perhitungan di browser tidak pernah menyimpang dari server.
8. **Dikembangkan dengan SDLC 2.0.** Setiap perubahan berangkat dari spec tertulis, dibantu AI, dan melewati gerbang persetujuan manusia (bagian 16).

### 1.3 Istilah

| Istilah | Arti dalam dokumen ini |
|---|---|
| Pagu jenis | Pagu per unit × jenis belanja (rekening Pemda tingkat Jenis) × sumber dana × tahun |
| Rincian | Baris RBA (item standar, volume, harga) di bawah pagu jenis |
| Paket revisi | Kumpulan perubahan rincian dalam satu sesi edit |
| Permintaan tambah/kurang | Permintaan pergeseran antarunit dari sisi unit yang naik/turun pagunya |
| Pasangan | Gabungan permintaan tambah dan kurang dengan total sama |
| Pencadangan | Nilai yang diblokir pada pagu jenis karena permintaan kurang yang belum disahkan |
| Hari kerja | Hari selain akhir pekan, libur nasional, dan cuti bersama menurut tabel kalender |

---

## 2. Arsitektur

### 2.1 Komponen

```
                 ┌──────────────────────────────────────────┐
  Browser ──────▶│ Nginx (TLS)                              │
 (Vue 3 via      └──────────────┬───────────────────────────┘
  Inertia 2)  ◀── WebSocket ────┼── Reverb (notifikasi real-time)
                 ┌──────────────▼───────────────────────────┐
                 │ Laravel 13 (PHP-FPM, PHP 8.3+)           │
                 │  ├─ Http (Inertia pages + JSON endpoints)│
                 │  ├─ Modules (domain services)            │
                 │  └─ Policies / Gates (RBAC + scope unit) │
                 └───┬──────────────┬──────────────┬────────┘
                     │              │              │
          ┌──────────▼───┐   ┌──────▼─────┐  ┌─────▼──────────┐
          │ PostgreSQL   │   │ Redis      │  │ Object storage │
          │ (data utama, │   │ (queue,    │  │ (lampiran,     │
          │  audit,      │   │  cache,    │  │  file impor/   │
          │  ledger)     │   │  session)  │  │  ekspor)       │
          └──────────────┘   └──────┬─────┘  └────────────────┘
                                    │
                     ┌──────────────▼───────────────┐
                     │ Queue worker (Horizon)       │
                     │ Scheduler (artisan schedule) │
                     └──────────────────────────────┘
```

### 2.2 Pilihan teknologi

| Lapisan | Pilihan | Alasan |
|---|---|---|
| Bahasa/framework | PHP ≥ 8.3 (disarankan 8.4), **Laravel 13** | Rilis Q1 2026; perbaikan keamanan hingga Q1 2028; tenaga PHP mudah dicari di lingkungan Pemda |
| Basis data | PostgreSQL ≥ 16 dengan ekstensi `pg_trgm`, `unaccent`, `btree_gist` | Transaksi kuat, constraint `CHECK`/`EXCLUDE`, pencarian kemiripan teks |
| Frontend | **Inertia 2** + Vue 3 (TypeScript) + Tailwind CSS | Interaksi kaya tanpa API publik terpisah; *deferred props*, *polling*, dan *prefetch* untuk indikator pagu dan hitung mundur batas waktu |
| Validasi langsung | **Laravel Precognition** | Validasi baris rincian saat diketik memakai Form Request server yang sama |
| Real-time | **Laravel Reverb** + Echo | Notifikasi permintaan pergeseran, persetujuan, dan perubahan status pasangan tanpa muat ulang |
| Feature flag | **Laravel Pennant** | Aktivasi modul per fase dan sakelar persetujuan otomatis tanpa deploy ulang |
| Antrian/cache | Redis + Laravel Horizon | Impor besar, notifikasi, job batas waktu |
| Penyimpanan file | S3-compatible (MinIO on-premise) atau disk lokal terenkripsi | Lampiran dan berkas impor |
| Autentikasi | Laravel Fortify + TOTP MFA | Login, reset sandi, MFA untuk pengesah dan bendahara |
| Otorisasi | `spatie/laravel-permission` + Policy dengan scope unit | RBAC yang dapat dikonfigurasi |
| Excel | `maatwebsite/excel` (impor bertahap via queue) | Impor standar harga, klaim, kodefikasi |
| PDF | Gotenberg (container) dari template Blade | Cetakan dokumen resmi yang presisi |
| Pengujian | Pest (termasuk *architecture testing*), Playwright | Unit, fitur, arsitektur antarmodul, dan UI |
| Kualitas kode | Larastan (PHPStan) level ≥ 8, Laravel Pint, Rector | Analisis statis, format, dan pemutakhiran sintaks otomatis |
| Asisten AI | **Laravel Boost** (MCP server) | Memberi asisten AI konteks skema DB, route, log, dan dokumentasi versi yang tepat (bagian 16) |
| Observabilitas | **Laravel Pulse** (produksi), Telescope (non-produksi), log terstruktur JSON | Kinerja, query lambat, antrian, job terjadwal |

### 2.3 Struktur modul

```
app/
  Modules/
    Shared/          # Money, Kalender, Penomoran, Lampiran, Audit context
    Master/          # Unit, sumber dana, pengguna, peran, matriks kewenangan, delegasi
    Kodefikasi/      # Rekening Pemda/BLUD, nomenklatur, pemetaan
    StandarHarga/    # Versi impor, item, usulan, pencocokan
    Anggaran/        # RBA, rincian, pagu jenis, ledger pagu
    Revisi/          # Paket revisi rincian
    Pergeseran/      # Permintaan, pasangan, pencadangan, batas waktu
    PerubahanRba/    # Perubahan RBA, ambang batas
    Workflow/        # Mesin persetujuan generik
    Penatausahaan/   # Transaksi belanja, dokumen pembayaran
    Bendahara/       # BKU, buku pembantu, SPJ, kas/bank
    Pajak/
    Utang/
    Piutang/         # Pendapatan, tagihan, klaim
    Sp3b/
    Akuntansi/       # Jurnal, aturan jurnal, periode, buku besar (Fase 3)
    Pelaporan/       # Laporan, ekspor SIPD
    Notifikasi/
```

Setiap modul berisi `Models/`, `Services/` (logika domain), `Actions/` (satu kasus penggunaan per kelas), `Http/` (controller, request), `Policies/`, `Jobs/`, dan `Database/`. Antarmodul berkomunikasi lewat Service publik atau Event Laravel, bukan dengan mengakses tabel modul lain secara langsung (kecuali baca untuk laporan). Batas ini ditegakkan otomatis dengan **Pest architecture test**, misalnya: kelas di `Modules\Pergeseran` tidak boleh memakai model `Modules\Penatausahaan`, dan hanya `PaguLedger` yang boleh menulis ke `pagu_mutasi`.

### 2.4 Pemanfaatan fitur Laravel 13 dan ekosistem

| Fitur | Penerapan |
|---|---|
| **PHP Attributes** untuk konfigurasi model, job, dan command | Konfigurasi job batas waktu (antrian, percobaan ulang, timeout) dan model ditulis sebagai attribute pada kelas, sehingga lebih ringkas dan mudah dibaca. Gaya lama berbasis properti tetap didukung |
| `Cache::touch()` | Memperpanjang umur cache daftar standar harga aktif tanpa membaca ulang |
| Precognition | Halaman edit rincian, formulir usulan standar harga, formulir transaksi |
| Inertia 2 deferred props | Memuat ringkasan pagu dan daftar kecocokan setelah halaman tampil |
| Inertia 2 polling | Hitung mundur sisa hari kerja pada daftar permintaan terbuka |
| Layout Inertia terpisah + PWA | Kotak masuk persetujuan *mobile-first* untuk Pimpinan BLUD (6.5) |
| Reverb | Saluran privat per pengguna dan per unit (`unit.{id}`) untuk notifikasi |
| Pennant | Flag `modul.penatausahaan`, `modul.akuntansi`, `pergeseran.persetujuan_otomatis` |
| Pulse | Kartu khusus: keterlambatan job batas waktu, jumlah persetujuan otomatis harian |
| Scheduler `withoutOverlapping()` + `onOneServer()` | Job batas waktu aman bila kelak server aplikasi lebih dari satu |
| Concurrency facade | Menyusun beberapa laporan berat secara paralel |

Contoh ilustratif job dengan attribute (nama attribute final mengikuti dokumentasi resmi Laravel 13):

```php
#[Queue('batas-waktu'), Tries(3), Timeout(120)]
final class ProsesBatasPengesahan implements ShouldQueue, ShouldBeUnique
{
    public function handle(PasanganService $service, KalenderKerja $kalender): void
    {
        PasanganPergeseran::query()
            ->where('status', 'MENUNGGU_PERENCANA')
            ->where('batas_pengesahan', '<', now())
            ->orderBy('id')
            ->each(fn ($p) => $p->memuat_di_atas_standar
                ? $service->kirimPengingatLewatBatas($p)
                : $service->sahkan($p, otomatis: true));
    }
}
```

---

## 3. Desain Basis Data

### 3.1 Konvensi

- Nama tabel dan kolom: `snake_case`, bahasa Indonesia sesuai istilah domain.
- Kunci utama: `bigint` identity. Kolom `uuid` tambahan untuk objek yang tampil di URL.
- Uang: `numeric(18,2)`. Volume: `numeric(18,4)`. Tidak memakai `float`.
- Waktu: `timestamptz`, zona tampilan `Asia/Jakarta`.
- Setiap tabel transaksional memiliki `dibuat_oleh`, `dibuat_pada`, `diubah_oleh`, `diubah_pada`, dan `row_version integer` untuk *optimistic locking*.
- Status disimpan sebagai `text` dengan `CHECK` terhadap daftar nilai (lebih mudah dimigrasikan daripada tipe `ENUM`).
- Tidak ada `ON DELETE CASCADE` pada data keuangan.

### 3.2 Ekstensi

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
CREATE EXTENSION IF NOT EXISTS btree_gist;
```

### 3.3 Peta tabel per modul

| Modul | Tabel utama |
|---|---|
| Master | `tahun_anggaran`, `unit_kerja`, `sumber_dana`, `users`, `roles`, `permissions`, `user_unit`, `matriks_kewenangan`, `delegasi`, `kalender_libur`, `konfigurasi` |
| Kodefikasi | `kodefikasi_versi`, `rekening`, `nomenklatur`, `pemetaan_rekening` |
| Standar harga | `standar_harga_impor`, `standar_harga_item`, `usulan_standar_harga`, `usulan_kecocokan` |
| Anggaran | `rba`, `rba_rincian`, `pagu_jenis`, `pagu_mutasi`, `posisi_dana`, `dana_mutasi`, `cadangan_kas_aturan`, `cadangan_kas_rekening`, `rencana_penarikan`, `dokumen_anggaran_rencana_penarikan`, `jadwal_perubahan`, `dokumen_anggaran`, `dokumen_anggaran_rincian`, `konfigurasi_dokumen` |
| Migrasi | `staging_rba`, `staging_rba_item`, `pemetaan_label_unit` |
| Revisi | `revisi_paket`, `revisi_detail` |
| Pergeseran | `permintaan_pergeseran`, `pasangan_pergeseran`, `pasangan_anggota` |
| Perubahan RBA | `perubahan_rba`, `perubahan_rba_detail`, `penggunaan_ambang_batas` |
| Workflow | `workflow_definisi`, `workflow_langkah`, `persetujuan`, `persetujuan_aksi` |
| Penatausahaan | `transaksi_belanja`, `transaksi_belanja_detail`, `dokumen_pembayaran`, `uang_muka` |
| Bendahara | `rekening_kas`, `bku`, `buku_pembantu`, `spj`, `mutasi_bank`, `rekonsiliasi_bank` |
| Pajak | `jenis_pajak`, `pajak_transaksi`, `setoran_pajak` |
| Utang | `utang`, `utang_pembayaran` |
| Piutang | `debitur`, `tagihan`, `klaim`, `penerimaan`, `penerimaan_alokasi` |
| SP3B | `sp3b`, `sp3b_detail` |
| Akuntansi | `akun`, `aturan_jurnal`, `jurnal`, `jurnal_detail`, `periode` |
| Umum | `lampiran`, `nomor_dokumen`, `audit_log`, `notifications`, `impor_batch`, `impor_baris_gagal` |

### 3.4 Kodefikasi dan pemetaan

```sql
CREATE TABLE kodefikasi_versi (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun         smallint NOT NULL,
  bagan         text NOT NULL CHECK (bagan IN ('PEMDA','BLUD','NOMENKLATUR')),
  keterangan    text,
  status        text NOT NULL DEFAULT 'DRAF' CHECK (status IN ('DRAF','AKTIF','ARSIP')),
  UNIQUE (tahun, bagan)
);

CREATE TABLE rekening (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  versi_id      bigint NOT NULL REFERENCES kodefikasi_versi(id),
  bagan         text NOT NULL CHECK (bagan IN ('PEMDA','BLUD')),
  kode          text NOT NULL,              -- mis. 5.1.02.01.01.0024
  uraian        text NOT NULL,
  tingkat       smallint NOT NULL CHECK (tingkat BETWEEN 1 AND 6),
                -- 1 Akun, 2 Kelompok, 3 Jenis, 4 Objek, 5 Rincian Objek, 6 Sub Rincian Objek
  induk_id      bigint REFERENCES rekening(id),
  jenis_id      bigint REFERENCES rekening(id), -- leluhur tingkat 3, diisi trigger
  dapat_dianggarkan boolean NOT NULL DEFAULT false,
  aktif         boolean NOT NULL DEFAULT true,
  UNIQUE (versi_id, kode)
);
CREATE INDEX ON rekening (versi_id, tingkat);
CREATE INDEX ON rekening (jenis_id);

CREATE TABLE nomenklatur (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  versi_id      bigint NOT NULL REFERENCES kodefikasi_versi(id),
  kode          text NOT NULL,
  uraian        text NOT NULL,
  tingkat       smallint NOT NULL CHECK (tingkat BETWEEN 1 AND 5),
                -- 1 Urusan, 2 Bidang Urusan, 3 Program, 4 Kegiatan, 5 Sub Kegiatan
  induk_id      bigint REFERENCES nomenklatur(id),
  UNIQUE (versi_id, kode)
);

CREATE TABLE pemetaan_rekening (
  id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  rekening_blud_id    bigint NOT NULL REFERENCES rekening(id),
  rekening_pemda_id   bigint NOT NULL REFERENCES rekening(id),
  berlaku             daterange NOT NULL,
  versi_pemetaan      integer NOT NULL,
  EXCLUDE USING gist (rekening_blud_id WITH =, berlaku WITH &&)
);
```

Aturan yang dijaga trigger `trg_pemetaan_validasi`:

- `rekening_blud_id` harus ber-`bagan='BLUD'`, `rekening_pemda_id` ber-`bagan='PEMDA'`.
- `tingkat` keduanya harus sama (pemetaan setara tingkat — UAT-36).
- `EXCLUDE` mencegah satu rekening BLUD punya dua pemetaan yang berlaku bersamaan.
- Pemetaan **banyak-ke-satu** diizinkan dan menjadi pola utama: banyak rekening rinci BLUD (mis. `5.1.02.01.01.0024`, `5.1.02.01.01.0037`) dipetakan ke satu rekening agregat Pemda (mis. `5.1.02.99.99.9999`). Karena rekening agregat berada di bawah jenis yang sama, `jenis_id` hasil pemetaan tetap benar (mis. `5.1.02`).
- Trigger tambahan memastikan rekening BLUD dan rekening Pemda hasil pemetaan memiliki **jenis yang sama**, sehingga pemetaan tidak dapat memindahkan belanja antarjenis.

Kolom `rekening.jenis_id` diisi trigger saat insert/ubah induk dengan menelusuri leluhur sampai tingkat 3, sehingga "jenis belanja" selalu tersedia tanpa query rekursif.

### 3.5 Standar harga

```sql
CREATE TABLE standar_harga_item (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun           smallint NOT NULL,
  sumber          text NOT NULL CHECK (sumber IN ('PEMDA','BLUD')),
  impor_id        bigint REFERENCES standar_harga_impor(id),  -- untuk PEMDA
  usulan_id       bigint REFERENCES usulan_standar_harga(id), -- untuk BLUD
  kode_barang     text,
  uraian          text NOT NULL,
  spesifikasi     text,
  satuan          text NOT NULL,
  harga           numeric(18,2) NOT NULL CHECK (harga > 0),
  rekening_ids    bigint[],          -- rekening yang boleh memakai item (opsional)
  uraian_norm     text GENERATED ALWAYS AS (lower(unaccent_immutable(uraian || ' ' || coalesce(spesifikasi,'')))) STORED,
  status          text NOT NULL DEFAULT 'AKTIF' CHECK (status IN ('AKTIF','NONAKTIF')),
  berlaku_mulai   date NOT NULL,
  tambahan_tengah_tahun boolean NOT NULL DEFAULT false,  -- disahkan setelah RBA tahun tsb DEFINITIF
  UNIQUE (tahun, sumber, kode_barang, satuan)
);
CREATE INDEX standar_uraian_trgm ON standar_harga_item USING gin (uraian_norm gin_trgm_ops);
```

`unaccent_immutable` adalah fungsi pembungkus `IMMUTABLE` atas `unaccent` agar dapat dipakai pada kolom generated.

**Penguncian harga (FR-020):** trigger `BEFORE UPDATE` menolak perubahan `harga`, `satuan`, dan `spesifikasi` pada item yang `tahun`-nya sudah memiliki RBA berstatus `DEFINITIF`.

**Penambahan di tengah tahun (FR-103):** `INSERT` item baru tetap diizinkan kapan saja melalui pengesahan usulan. Saat disahkan, `berlaku_mulai = tanggal pengesahan` dan `tambahan_tengah_tahun = true` bila RBA tahun tersebut sudah definitif. Validasi rincian menolak item yang `berlaku_mulai`-nya setelah tanggal revisi/transaksi.

```sql
CREATE TABLE usulan_standar_batch (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid            uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  impor_batch_id  bigint NOT NULL REFERENCES impor_batch(id),
  tahun           smallint NOT NULL,
  diajukan_oleh   bigint NOT NULL,
  jumlah_baris    integer NOT NULL,
  jumlah_tanpa_cocok integer NOT NULL,
  jumlah_cocok_sedang integer NOT NULL,
  jumlah_cocok_tinggi integer NOT NULL,
  status          text NOT NULL CHECK (status IN ('DRAF','DIAJUKAN','SEBAGIAN_DIPUTUSKAN','SELESAI')),
  bukti_lampiran_id bigint REFERENCES lampiran(id)    -- bukti untuk seluruh batch (opsional)
);
-- usulan_standar_harga.batch_id merujuk tabel ini; satu baris Excel = satu usulan
```

### 3.6 RBA, rincian, dan pagu jenis

```sql
CREATE TABLE rba (
  id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun             smallint NOT NULL,
  versi             integer NOT NULL,
  jenis_versi       text NOT NULL CHECK (jenis_versi IN ('AWAL','DEFINITIF','PERUBAHAN')),
  status            text NOT NULL CHECK (status IN ('DRAF','DIAJUKAN','DIKEMBALIKAN','DIVERIFIKASI',
                                                   'DISETUJUI_INTERNAL','MENUNGGU_PENETAPAN','DEFINITIF','DIGANTIKAN')),
  target_pendapatan numeric(18,2),
  persen_ambang_batas numeric(5,2),
  nomor_pengesahan  text,
  tanggal_pengesahan date,
  UNIQUE (tahun, versi)
);
CREATE UNIQUE INDEX rba_satu_berlaku ON rba (tahun) WHERE status = 'DEFINITIF';

CREATE TABLE rba_rincian (
  id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  rba_id            bigint NOT NULL REFERENCES rba(id),
  unit_id           bigint NOT NULL REFERENCES unit_kerja(id),   -- wajib (PRD 7.16)
  sumber_dana_id    bigint NOT NULL REFERENCES sumber_dana(id),
  sub_kegiatan_id   bigint REFERENCES nomenklatur(id),
  rekening_blud_id  bigint NOT NULL REFERENCES rekening(id),
  rekening_pemda_id bigint NOT NULL REFERENCES rekening(id),   -- snapshot hasil pemetaan
  jenis_id          bigint NOT NULL REFERENCES rekening(id),   -- snapshot rekening Pemda tingkat 3
  item_id           bigint NOT NULL REFERENCES standar_harga_item(id),
  uraian            text NOT NULL,
  volume            numeric(18,4) NOT NULL CHECK (volume > 0),
  satuan            text NOT NULL,
  harga_standar     numeric(18,2) NOT NULL,
  harga             numeric(18,2) NOT NULL CHECK (harga > 0),
  nilai             numeric(18,2) GENERATED ALWAYS AS (round(volume * harga, 2)) STORED,
  di_atas_standar   boolean GENERATED ALWAYS AS (harga > harga_standar) STORED,
  dasar_harga       text NOT NULL DEFAULT 'STANDAR' CHECK (dasar_harga IN ('STANDAR','DISETUJUI_DI_ATAS_STANDAR')),
  realisasi         numeric(18,2) NOT NULL DEFAULT 0,
  komitmen          numeric(18,2) NOT NULL DEFAULT 0,
  terkunci_paket_id bigint,   -- diisi saat masuk paket revisi menunggu
  terkunci_transaksi boolean NOT NULL DEFAULT false,
  aktif             boolean NOT NULL DEFAULT true,
  row_version       integer NOT NULL DEFAULT 1,
  CONSTRAINT rincian_harga_sah CHECK (NOT di_atas_standar OR dasar_harga = 'DISETUJUI_DI_ATAS_STANDAR'),
  CONSTRAINT rincian_tidak_di_bawah_pemakaian CHECK (nilai >= realisasi + komitmen)
);
CREATE INDEX ON rba_rincian (rba_id, unit_id, jenis_id, sumber_dana_id) WHERE aktif;
```

Constraint `rincian_harga_sah` memastikan rincian dengan harga di atas standar hanya bisa ada jika telah melalui persetujuan perencana. Constraint `rincian_tidak_di_bawah_pemakaian` adalah penjaga FR-036 dan UAT-13.

```sql
CREATE TABLE pagu_jenis (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun           smallint NOT NULL,
  unit_id         bigint NOT NULL REFERENCES unit_kerja(id),
  jenis_id        bigint NOT NULL REFERENCES rekening(id),
  sumber_dana_id  bigint NOT NULL REFERENCES sumber_dana(id),
  pagu_baseline   numeric(18,2) NOT NULL,
  pagu_berlaku    numeric(18,2) NOT NULL,
  realisasi       numeric(18,2) NOT NULL DEFAULT 0,
  komitmen        numeric(18,2) NOT NULL DEFAULT 0,
  tercadang       numeric(18,2) NOT NULL DEFAULT 0,
  sisa            numeric(18,2) GENERATED ALWAYS AS (pagu_berlaku - realisasi - komitmen - tercadang) STORED,
  row_version     integer NOT NULL DEFAULT 1,
  UNIQUE (tahun, unit_id, jenis_id, sumber_dana_id),
  CONSTRAINT pagu_sisa_tidak_negatif CHECK (pagu_berlaku - realisasi - komitmen - tercadang >= 0),
  CONSTRAINT pagu_nilai_positif CHECK (realisasi >= 0 AND komitmen >= 0 AND tercadang >= 0)
);

CREATE TABLE pagu_mutasi (                     -- ledger, append-only
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  pagu_jenis_id   bigint NOT NULL REFERENCES pagu_jenis(id),
  komponen        text NOT NULL CHECK (komponen IN ('PAGU','REALISASI','KOMITMEN','TERCADANG')),
  nilai           numeric(18,2) NOT NULL,       -- positif menambah, negatif mengurangi
  sumber_tipe     text NOT NULL,                -- 'RBA_DEFINITIF','PASANGAN','PERUBAHAN_RBA','AMBANG_BATAS',
                                                -- 'TRANSAKSI','UTANG','PERMINTAAN_KURANG', ...
  sumber_id       bigint NOT NULL,
  keterangan      text,
  dibuat_oleh     bigint,                       -- NULL = sistem
  dibuat_pada     timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON pagu_mutasi (pagu_jenis_id, dibuat_pada);
```

Aturan:

- Kolom agregat di `pagu_jenis` **hanya** diubah oleh service `PaguLedger::catat()`, yang dalam satu transaksi menulis baris `pagu_mutasi` dan memperbarui agregat. Hak `UPDATE` atas kolom agregat dicabut dari peran DB aplikasi kecuali melalui fungsi `SECURITY DEFINER` `fn_pagu_catat(...)`.
- Rekonsiliasi harian (job) memastikan agregat = jumlah mutasi per komponen.
- `pagu_sisa_tidak_negatif` adalah penjaga terakhir UAT-15 dan UAT-21: jika dua transaksi bersamaan lolos validasi aplikasi, yang kedua akan gagal di DB.

**Kesamaan total rincian dengan pagu:** total `rba_rincian.nilai` aktif per pagu jenis tidak dipaksa sama dengan `pagu_berlaku` di tingkat DB (karena paket revisi menunggu dan pembulatan), tetapi diperiksa oleh `PaguService::periksaKonsistensi()` setelah setiap penerapan revisi/pasangan dan oleh job rekonsiliasi harian.

### 3.7 Revisi rincian

```sql
CREATE TABLE revisi_paket (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid            uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  nomor           text UNIQUE,
  pagu_jenis_id   bigint NOT NULL REFERENCES pagu_jenis(id),
  status          text NOT NULL CHECK (status IN ('DRAF','BERLAKU','MENUNGGU_PERENCANA',
                                                   'MENUNGGU_PERGESERAN','DITOLAK','GUGUR','DIBATALKAN')),
  total_sebelum   numeric(18,2) NOT NULL,
  total_sesudah   numeric(18,2) NOT NULL,
  selisih         numeric(18,2) GENERATED ALWAYS AS (total_sesudah - total_sebelum) STORED,
  memuat_di_atas_standar boolean NOT NULL DEFAULT false,
  permintaan_id   bigint,             -- FK ke permintaan_pergeseran bila MENUNGGU_PERGESERAN
  alasan          text,
  dibuat_oleh     bigint NOT NULL,
  dibuat_pada     timestamptz NOT NULL DEFAULT now(),
  diterapkan_pada timestamptz
);
-- satu draf aktif per pengguna per pagu jenis
CREATE UNIQUE INDEX revisi_satu_draf ON revisi_paket (pagu_jenis_id, dibuat_oleh) WHERE status = 'DRAF';

CREATE TABLE revisi_detail (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  paket_id        bigint NOT NULL REFERENCES revisi_paket(id),
  aksi            text NOT NULL CHECK (aksi IN ('TAMBAH','UBAH','HAPUS')),
  rincian_id      bigint REFERENCES rba_rincian(id),      -- NULL untuk TAMBAH
  rincian_row_version integer,                             -- versi saat diambil ke draf
  sebelum         jsonb,
  sesudah         jsonb,                                   -- item_id, volume, harga, uraian, sub_kegiatan, rekening_blud
  di_atas_standar boolean NOT NULL DEFAULT false
);
```

### 3.8 Pergeseran antarunit

```sql
CREATE TABLE permintaan_pergeseran (
  id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid              uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  nomor             text UNIQUE,
  arah              text NOT NULL CHECK (arah IN ('TAMBAH','KURANG')),
  pagu_jenis_id     bigint NOT NULL REFERENCES pagu_jenis(id),
  nilai             numeric(18,2) NOT NULL CHECK (nilai > 0),
  revisi_paket_id   bigint REFERENCES revisi_paket(id),
  memuat_di_atas_standar boolean NOT NULL DEFAULT false,
  status            text NOT NULL CHECK (status IN ('TERBUKA','BERPASANGAN','DISAHKAN','DITOLAK',
                                                     'DITOLAK_OTOMATIS','DIBATALKAN')),
  diajukan_pada     timestamptz NOT NULL,
  batas_pasangan    timestamptz NOT NULL,     -- akhir hari kerja ke-5
  pengingat_terkirim boolean NOT NULL DEFAULT false,
  alasan_tolak      text
);
CREATE INDEX ON permintaan_pergeseran (status, batas_pasangan);

CREATE TABLE pasangan_pergeseran (
  id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid              uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  nomor             text UNIQUE,
  tahun             smallint NOT NULL,
  jenis_id          bigint NOT NULL,
  sumber_dana_id    bigint NOT NULL,
  total_tambah      numeric(18,2) NOT NULL,
  total_kurang      numeric(18,2) NOT NULL,
  memuat_di_atas_standar boolean NOT NULL,
  status            text NOT NULL CHECK (status IN ('MENUNGGU_UNIT','MENUNGGU_PERENCANA','DISAHKAN',
                                                     'DISETUJUI_OTOMATIS','DITOLAK','DITOLAK_VALIDASI')),
  dibentuk_oleh     bigint NOT NULL,
  lengkap_pada      timestamptz,              -- saat semua unit setuju
  batas_pengesahan  timestamptz,              -- akhir hari kerja ke-5 sejak lengkap_pada
  CONSTRAINT pasangan_seimbang CHECK (total_tambah = total_kurang)
);

CREATE TABLE pasangan_anggota (
  pasangan_id       bigint NOT NULL REFERENCES pasangan_pergeseran(id),
  permintaan_id     bigint NOT NULL REFERENCES permintaan_pergeseran(id),
  persetujuan_unit  text NOT NULL DEFAULT 'MENUNGGU' CHECK (persetujuan_unit IN ('MENUNGGU','SETUJU','TOLAK')),
  PRIMARY KEY (pasangan_id, permintaan_id)
);
-- satu permintaan hanya boleh berada di satu pasangan yang masih hidup
CREATE UNIQUE INDEX anggota_unik_aktif ON pasangan_anggota (permintaan_id)
  WHERE persetujuan_unit <> 'TOLAK';
```

Trigger `trg_pasangan_konsisten` (deferred, dijalankan saat commit) memastikan seluruh anggota sebuah pasangan memiliki `jenis_id`, `sumber_dana_id`, dan `tahun` yang sama, berasal dari unit berbeda untuk arah berlawanan, dan `total_tambah`/`total_kurang` sama dengan jumlah `nilai` anggota per arah.

### 3.9 Tabel pendukung lain (ringkas)

| Tabel | Kolom kunci |
|---|---|
| `usulan_standar_harga` | data item, bukti (lampiran), status (`DRAF`,`DIAJUKAN`,`DIKEMBALIKAN`,`DITOLAK_PAKAI_ITEM`,`DISAHKAN`), `item_rujukan_id`, `alasan_keputusan`, `batch_id`, `kategori_cocok` (`TANPA`,`SEDANG`,`TINGGI`), `asal` (`FORMULIR`,`IMPOR`), `konteks` (`PENYUSUNAN`,`REVISI`,`TRANSAKSI`) |
| `push_langganan` | pengguna, endpoint, kunci, perangkat, terakhir dipakai |
| `webauthn_kredensial` | pengguna, credential id, public key, perangkat, terakhir dipakai |
| `usulan_kecocokan` | `usulan_id`, `item_id`, `skor`, rincian skor (jsonb) — snapshot saat diajukan dan saat diputuskan |
| `perubahan_rba` | jenis, nilai, `rba_asal_id`, `rba_hasil_id`, status, persetujuan |
| `penggunaan_ambang_batas` | `pagu_jenis_id`, nilai, dasar realisasi pendapatan, status, persetujuan |
| `transaksi_belanja` | nomor, tanggal, `pagu_jenis_id`, pihak, status (`DRAF`,`DIAJUKAN`,`DISETUJUI`,`DIBAYAR`,`DIBATALKAN`,`DIREVERSAL`), `reversal_dari_id` |
| `transaksi_belanja_detail` | `rincian_id`, volume, harga, nilai; `CHECK` harga = harga rincian dipastikan di service + trigger |
| `utang` | pemasok, dokumen, nilai, jatuh tempo, sisa, `pagu_jenis_id`, `rincian_id` |
| `tagihan`, `klaim` | debitur/penjamin, nilai diajukan/disetujui, status klaim, selisih |
| `pajak_transaksi` | transaksi, jenis pajak, DPP, tarif, nilai, status setor, NTPN, nomor bukti potong |
| `sp3b`, `sp3b_detail` | periode, rekening Pemda, pendapatan, belanja, status, nomor/tanggal SP2B |
| `jurnal`, `jurnal_detail` | sumber transaksi, periode, akun, debit, kredit; `CHECK` seimbang per jurnal (trigger deferred) |
| `periode` | tahun, bulan, status (`BUKA`,`TUTUP`,`TERKUNCI_SP2B`) |
| `unit_kerja` | kode, nama, `induk_id`, jenis (bagian/instalasi/poli/ruang), aktif |
| `pemetaan_label_unit` | label teks (dinormalisasi), `unit_id` — dipakai impor RBA lama |
| `konfigurasi_dokumen` | tahun, kepala dokumen (urusan, bidang, unit organisasi, sub unit, program, kegiatan, sub kegiatan, waktu, lokasi), penanda tangan (nama, NIP, jabatan) |
| `kalender_libur` | tanggal, jenis (`LIBUR_NASIONAL`,`CUTI_BERSAMA`,`LIBUR_LOKAL`), keterangan |
| `delegasi` | pejabat_id, pelaksana_id, kewenangan (array permission), `berlaku tstzrange`, `EXCLUDE` tumpang tindih per pejabat |
| `nomor_dokumen` | jenis dokumen, tahun, format, `nomor_terakhir` |
| `lampiran` | polymorphic (`lampiranable_type`, `lampiranable_id`), path, mime, ukuran, sha256, jenis dokumen |

**Kunci periode:** trigger pada tabel transaksional menolak insert/update bila tanggal transaksi jatuh pada `periode.status IN ('TUTUP','TERKUNCI_SP2B')` (UAT-26, UAT-29).

### 3.10 Audit log

```sql
CREATE TABLE audit_log (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  waktu         timestamptz NOT NULL DEFAULT clock_timestamp(),
  pengguna_id   bigint,                 -- NULL = sistem
  aktor_jenis   text NOT NULL CHECK (aktor_jenis IN ('PENGGUNA','SISTEM')),
  atas_nama_id  bigint,                 -- pejabat definitif bila bertindak sebagai delegasi
  tindakan      text NOT NULL,          -- INSERT/UPDATE/DELETE atau tindakan domain (AJUKAN, SETUJUI, ...)
  tabel         text,
  objek_id      bigint,
  nilai_lama    jsonb,
  nilai_baru    jsonb,
  alasan        text,
  ip            inet,
  request_id    uuid,
  hash_sebelum  bytea,
  hash          bytea NOT NULL
);
```

- Trigger generik `fn_audit()` dipasang pada tabel penting dan membaca konteks pengguna dari `current_setting('app.user_id', true)`, `app.aktor_jenis`, `app.atas_nama_id`, `app.request_id` yang diset middleware dengan `SET LOCAL` di awal setiap transaksi.
- Tindakan domain (ajukan, setujui, tolak, setujui otomatis) ditulis oleh `AuditService` dengan alasan.
- **Append-only:** peran DB aplikasi hanya punya hak `INSERT` dan `SELECT` pada `audit_log`.
- **Rantai hash:** `hash = sha256(hash_sebelum || baris)`; job harian memverifikasi rantai dan melapor jika terputus (FR-088).

---

## 4. Aturan Bisnis Kritis dan Algoritma

### 4.1 Perhitungan dan penguncian pagu

Semua operasi yang mengubah pagu mengikuti pola berikut:

```php
DB::transaction(function () use ($paguIds, $operasi) {
    // 1. Kunci baris dalam urutan id yang tetap -> mencegah deadlock
    $pagu = PaguJenis::whereIn('id', $paguIds)->orderBy('id')->lockForUpdate()->get();

    // 2. Validasi ulang aturan domain dengan data yang sudah terkunci
    $operasi->validasi($pagu);

    // 3. Tulis ledger + agregat (fungsi DB SECURITY DEFINER)
    foreach ($operasi->mutasi() as $m) {
        PaguLedger::catat($m->paguId, $m->komponen, $m->nilai, $m->sumberTipe, $m->sumberId);
    }

    // 4. Perbarui objek domain (status, rincian), audit, event
    $operasi->terapkan();
}, attempts: 3);   // ulang otomatis bila terjadi serialization failure / deadlock
```

Tingkat isolasi default `READ COMMITTED` cukup karena semua keputusan diambil setelah `SELECT ... FOR UPDATE`.

### 4.2 Validasi harga

| Konteks | Aturan | Lokasi penegakan |
|---|---|---|
| Penyusunan RBA (status < DEFINITIF) | `harga <= harga_standar`, tanpa pengecualian | `RincianRequest`, `RbaService`, constraint `rincian_harga_sah` (dasar harga tidak dapat diset saat penyusunan) |
| Revisi rincian | `harga > harga_standar` hanya jika paket berstatus disetujui perencana | `RevisiService`, constraint `rincian_harga_sah` |
| Transaksi belanja | `harga_transaksi = rincian.harga` dan `nilai <= rincian.nilai - realisasi - komitmen` | `TransaksiService`, trigger `trg_transaksi_harga` |

Item yang dipilih harus `standar_harga_item.status='AKTIF'` dan `tahun` sama dengan RBA. Untuk item yang ada di sumber PEMDA dan BLUD, prioritas pembanding mengikuti konfigurasi (PRD K-3); default sementara: standar Pemda.

### 4.3 Pengecekan kecocokan standar harga

Dijalankan saat pengusul mengetik usulan (debounce 400 ms), saat usulan diajukan (snapshot), saat perencana membuka usulan (dihitung ulang), dan saat impor Pemda (terhadap standar BLUD).

**Normalisasi** (`StandarHargaNormalizer`): huruf kecil, hapus aksen dan tanda baca, sinonim satuan (`gr|gram|g`, `ltr|liter|l`, `bh|buah|pcs`), angka + satuan disatukan (`70 gr` → `70gr`), hapus kata umum (`dan`, `untuk`, `ukuran`).

**Kandidat:** maksimal 50 item dengan `similarity(uraian_norm, :q) > 0.25` memakai indeks GIN trigram, ditambah item dengan `kode_barang` sama.

**Skor gabungan (0–1):**

```
skor = 0.55 × similarity(uraian_norm)
     + 0.15 × (kode_barang sama ? 1 : 0)
     + 0.15 × (satuan ternormalisasi sama ? 1 : 0)
     + 0.15 × kedekatan_harga          -- 1 - min(1, |h1-h2| / max(h1,h2))
```

Kategori: **tinggi** ≥ 0,70; **sedang** 0,45–0,69; **rendah** 0,30–0,44; di bawah itu tidak ditampilkan. Bobot dan ambang disimpan di tabel `konfigurasi` agar dapat dikalibrasi dengan data nyata.

**Penegakan keputusan:** jika ada kecocokan tinggi, aksi "Sahkan sebagai item baru" mewajibkan `alasan_keputusan` minimal 20 karakter (UAT-04).

**Impor Excel usulan standar internal (FR-104, FR-105):**

1. Unggah → `impor_batch` → job validasi per chunk 500 baris (format, kolom wajib, satuan ternormalisasi, harga > 0, duplikat di dalam file berdasarkan `uraian_norm` + satuan).
2. Job pencocokan per baris memakai `StandarHargaMatcher` yang sama; hasil disimpan ke `usulan_kecocokan` dan `kategori_cocok` per baris. Untuk 1.000 baris, target selesai < 2 menit.
3. Pratinjau menampilkan tab *Tanpa kecocokan*, *Kecocokan sedang*, *Kecocokan tinggi*, *Gagal*; pengusul dapat menghapus baris atau menggantinya dengan item yang ada.
4. **Ajukan** membuat `usulan_standar_batch` berstatus `DIAJUKAN` dan satu `usulan_standar_harga` per baris valid.
5. Perencana: **Sahkan semua yang memenuhi syarat** hanya memproses baris dengan `kategori_cocok IN ('TANPA','SEDANG')` dalam satu transaksi; baris `TINGGI` wajib diputuskan per baris (aturan alasan berlaku). Kecocokan dihitung ulang saat keputusan untuk menangkap item yang disahkan sejak batch diajukan.
6. Status batch menjadi `SEBAGIAN_DIPUTUSKAN` hingga semua baris diputuskan, lalu `SELESAI`.

### 4.4 Paket revisi rincian

**Siklus hidup**

```
            simpan seimbang & ≤ standar
  DRAF ───────────────────────────────────────▶ BERLAKU
   │  simpan seimbang & ada > standar
   ├─────────────────────────────▶ MENUNGGU_PERENCANA ──setuju──▶ BERLAKU
   │                                         └──tolak───▶ DITOLAK
   │  simpan dengan selisih ≠ 0 (pilih "kirim")
   ├─────────────────────────────▶ MENUNGGU_PERGESERAN ──pasangan disahkan──▶ BERLAKU
   │                                         ├──ditolak/kedaluwarsa──▶ GUGUR
   │  batalkan
   └─────────────────────────────▶ DIBATALKAN
```

**Draf di server.** Perubahan pada halaman edit disimpan sebagai draf (`revisi_paket` status `DRAF`) secara otomatis setiap kali baris berubah, sehingga tidak hilang saat browser tertutup. Saat rincian diambil ke draf, `rincian_row_version` dicatat.

**Indikator status pagu (FR-091).** Dihitung di klien dari data draf, dan dikonfirmasi server lewat endpoint `GET /revisi/{paket}/ringkasan`:

```
total_sesudah = Σ nilai rincian aktif yang tidak terlibat draf
              + Σ nilai baris draf (TAMBAH/UBAH), tidak termasuk HAPUS
selisih       = total_sesudah - pagu_berlaku
status        = selisih == 0 ? 'SEIMBANG' : (selisih > 0 ? 'BERTAMBAH' : 'BERKURANG')
```

**Algoritma simpan (`RevisiService::simpan`)**

1. Kunci `pagu_jenis` dan semua `rba_rincian` yang disentuh (urut id).
2. Untuk setiap detail `UBAH/HAPUS`: tolak jika `row_version` rincian berbeda dari versi saat diambil (*edit konflik*) atau rincian terkunci paket lain/transaksi berjalan.
3. Validasi setiap baris: item aktif dan setahun, jenis rekening = jenis pagu, sumber dana sama, nilai baru ≥ realisasi + komitmen.
4. Hitung `selisih`.
5. Keputusan:
   - `selisih == 0`, tidak ada di atas standar, **dan** `jenis_id` pagu termasuk konfigurasi `revisi.jenis_berlaku_langsung` (default: rekening Pemda tingkat Jenis *Belanja Barang dan Jasa*) → terapkan ke `rba_rincian` (update/insert/nonaktifkan), status `BERLAKU`.
   - `selisih == 0` dan jenis **tidak** termasuk konfigurasi → status `MENUNGGU_PERENCANA`, kunci rincian, buat instance workflow `REVISI_PERLU_PERSETUJUAN` (FR-106).
   - `selisih == 0` dan ada di atas standar (jenis apa pun) → status `MENUNGGU_PERENCANA`, kunci rincian, buat instance workflow `REVISI_DI_ATAS_STANDAR`.
   - `selisih != 0` → kembalikan respons `409 PERLU_PERSETUJUAN` berisi selisih; klien menampilkan dialog. Jika pengguna memilih **kirim**, klien memanggil `POST /revisi/{paket}/kirim-pergeseran`, yang membuat `permintaan_pergeseran` (arah `TAMBAH` bila selisih > 0, `KURANG` bila < 0, nilai = |selisih|), mengunci rincian, dan mengubah status paket menjadi `MENUNGGU_PERGESERAN`.
6. Tulis audit dan event `RevisiDisimpan`.

**Melanjutkan edit (FR-094).** Setelah paket menunggu, pengguna dapat membuat draf baru. Rincian dengan `terkunci_paket_id` tampil read-only beserta tautan ke paket yang menguncinya. Perhitungan memakai `pagu_berlaku` saat ini.

**Validasi langsung.** Setiap baris divalidasi saat diketik melalui Precognition terhadap `SimpanBarisRevisiRequest` (item aktif, jenis rekening, harga vs standar, nilai ≥ realisasi + komitmen), sehingga pesan kesalahan muncul sebelum pengguna menekan Simpan.

**Peringatan keluar halaman (FR-095).** Di klien: `router.on('before', ...)` milik Inertia untuk navigasi internal dan `window.onbeforeunload` untuk tutup tab, aktif bila ada perubahan belum tersimpan **dan** status ≠ SEIMBANG. Dialog internal menawarkan kirim/kembali/buang; untuk tutup tab hanya dialog bawaan browser yang tersedia, tetapi draf tetap tersimpan di server.

### 4.5 Permintaan dan pasangan pergeseran

**Status permintaan**

```
TERBUKA ──masuk pasangan──▶ BERPASANGAN ──pasangan disahkan/disetujui otomatis──▶ DISAHKAN
   │                          │
   │                          └──pasangan ditolak──▶ TERBUKA (bila masih dalam batas) / DITOLAK
   ├──batas_pasangan lewat──▶ DITOLAK_OTOMATIS
   └──dibatalkan pengusul──▶ DIBATALKAN
```

**Saat permintaan dibuat:**

- `KURANG`: validasi `sisa >= nilai` pada pagu terkunci, lalu `PaguLedger::catat(TERCADANG, +nilai)`.
- `TAMBAH`: tidak ada pencadangan.
- `batas_pasangan = KalenderKerja::akhirHariKerjaKe(diajukan_pada, 5)`.
- Notifikasi ke pengusul anggaran di unit lain yang memiliki pagu dengan jenis dan sumber dana sama, serta ke perencana.

**Pembentukan pasangan (`PasanganService::bentuk`)**

1. Kunci semua permintaan anggota (urut id). Semua harus `TERBUKA` dan belum lewat batas.
2. Validasi: jenis, sumber dana, dan tahun sama; unit pada sisi `TAMBAH` dan `KURANG` tidak saling beririsan; `Σ TAMBAH == Σ KURANG`; tidak ada pasangan sebagian.
3. Simpan pasangan berstatus `MENUNGGU_UNIT`; permintaan → `BERPASANGAN`.
4. Minta persetujuan setiap unit anggota yang bukan pembentuk pasangan (sesuai matriks kewenangan).
5. Jika ada unit yang menolak: pasangan `DITOLAK`, permintaan kembali `TERBUKA` (batas waktu tetap berjalan sejak pengajuan awal).
6. Jika semua setuju: `lengkap_pada = now()`, `batas_pengesahan = akhirHariKerjaKe(lengkap_pada, 5)`, status `MENUNGGU_PERENCANA`, notifikasi perencana.

**Pengesahan (`PasanganService::sahkan`)** — manual oleh perencana atau otomatis oleh job:

1. Dalam satu transaksi, kunci semua `pagu_jenis` anggota dan semua rincian dalam paket revisi terkait (urut id).
2. Validasi ulang: sisa pagu sisi kurang (memperhitungkan pencadangannya sendiri), konsistensi jenis/sumber, item standar masih aktif, rincian tidak berubah sejak dikunci, unit pasangan semua `SETUJU`.
3. Mutasi ledger:
   - sisi `KURANG`: `TERCADANG −nilai` lalu `PAGU −nilai` (urutan ini wajib agar `pagu_sisa_tidak_negatif` tidak gagal di antara dua langkah);
   - sisi `TAMBAH`: `PAGU +nilai`.
4. Terapkan setiap paket revisi terkait ke `rba_rincian`, buka kunci rincian, paket → `BERLAKU`.
5. Pasangan → `DISAHKAN` atau `DISETUJUI_OTOMATIS`; permintaan → `DISAHKAN`.
6. Periksa invarian: Σ perubahan `PAGU` dalam transaksi = 0. Jika tidak, `throw` dan rollback.
7. Audit dan notifikasi.

Jika validasi ulang gagal pada pengesahan otomatis: pasangan `DITOLAK_VALIDASI`, semua permintaan anggota → `DITOLAK`, paket revisi → `GUGUR`, pencadangan dilepas, alasan dicatat (UAT-44).

**Pembatalan/kedaluwarsa permintaan `KURANG`:** `TERCADANG −nilai`. Paket revisi terkait → `GUGUR`, kunci rincian dilepas.

### 4.6 Kalender kerja dan batas waktu

```php
final class KalenderKerja
{
    /** Akhir (23:59:59 WIB) hari kerja ke-$n, dihitung mulai hari kerja setelah tanggal pengajuan. */
    public function akhirHariKerjaKe(CarbonImmutable $mulai, int $n): CarbonImmutable
    {
        $tgl = $mulai->setTimezone('Asia/Jakarta')->startOfDay();
        $hitung = 0;
        while ($hitung < $n) {
            $tgl = $tgl->addDay();
            if ($this->hariKerja($tgl)) $hitung++;
        }
        return $tgl->endOfDay();
    }

    public function hariKerja(CarbonImmutable $tgl): bool
    {
        return ! $tgl->isWeekend() && ! $this->libur->contains($tgl->toDateString());
    }
}
```

- **Asumsi:** hitungan dimulai dari hari kerja **setelah** hari pengajuan; batas adalah akhir hari kerja ke-5. (Contoh: diajukan Senin → batas Senin berikutnya 23:59, jika tidak ada libur.) Perlu dikonfirmasi pemilik proses.
- Batas waktu dihitung dan **disimpan** saat objek dibuat, sehingga perubahan kalender setelahnya tidak menggeser batas yang sudah berjalan. Perubahan nilai konfigurasi batas hanya berlaku untuk objek baru.
- Data `kalender_libur` dikelola administrator; disediakan impor Excel untuk SKB libur nasional dan cuti bersama tiap tahun. Job awal Desember mengingatkan admin jika kalender tahun berikutnya kosong.

### 4.7 Job terjadwal

| Job | Jadwal | Fungsi |
|---|---|---|
| `ProsesBatasPasangan` | tiap 15 menit | Permintaan `TERBUKA` dengan `batas_pasangan < now()` → `DITOLAK_OTOMATIS`, lepas pencadangan, gugurkan paket, notifikasi (UAT-40, UAT-41) |
| `ProsesBatasPengesahan` | tiap 15 menit | Pasangan `MENUNGGU_PERENCANA` lewat batas: jika `memuat_di_atas_standar = false` → `sahkan(otomatis)`; jika `true` → tetap menunggu, kirim pengingat harian (UAT-43–45) |
| `KirimPengingatBatasWaktu` | hari kerja 08:00 | Pengingat hari kerja ke-3 dan ke-4 (pengesahan), hari ke-4 (pasangan) |
| `HitungUlangCadangan` | awal bulan 00:30 + event | Hitung ulang cadangan metode kebutuhan N bulan |
| `RekonsiliasiPagu` | harian 01:00 | Agregat `pagu_jenis` vs Σ `pagu_mutasi`; Σ rincian aktif vs `pagu_berlaku`; lapor selisih |
| `VerifikasiRantaiAudit` | harian 02:00 | Verifikasi hash `audit_log` |
| `RekonsiliasiPajak` | harian | Pajak dipotong belum disetor > N hari → notifikasi bendahara |
| `UmurUtangPiutang` | harian | Hitung ulang umur, notifikasi jatuh tempo |

Setiap job **idempoten** (memeriksa status di dalam transaksi terkunci) dan memakai `withoutOverlapping()`, sehingga aman dijalankan ulang.

### 4.8 Persetujuan otomatis

- Aktor audit: `aktor_jenis='SISTEM'`, tindakan `SETUJUI_OTOMATIS`, alasan `"batas waktu pengesahan terlampaui"`, beserta daftar notifikasi yang telah dikirim ke perencana (id, waktu).
- Konfigurasi: `pergeseran.batas_pasangan_hari_kerja = 5`, `pergeseran.batas_pengesahan_hari_kerja = 5`, `pergeseran.persetujuan_otomatis_aktif = true`.
- Pasangan dengan `memuat_di_atas_standar = true` **tidak pernah** masuk jalur otomatis; pengecekan ini dilakukan di query job **dan** di `PasanganService::sahkan(otomatis: true)`.
- Paket revisi berstatus `MENUNGGU_PERENCANA` (jenis belanja selain Belanja Barang dan Jasa, atau harga di atas standar) **tidak memiliki** jalur persetujuan otomatis; job hanya mengirim pengingat.
- Apakah persetujuan otomatis pasangan antarunit juga dibatasi untuk Belanja Barang dan Jasa menunggu PRD K-25; disiapkan konfigurasi `pergeseran.jenis_boleh_otomatis` (default: semua jenis) agar dapat diubah tanpa rilis.
- Laporan khusus daftar persetujuan otomatis per perencana dan periode.

### 4.9 Transaksi belanja, komitmen, dan utang

| Peristiwa | Mutasi pagu jenis | Mutasi rincian |
|---|---|---|
| Transaksi disetujui (belum dibayar) / utang diakui | `KOMITMEN +nilai` (juga pada `posisi_dana`, 4.15) | `komitmen +nilai` |
| Pembayaran (sebagian/penuh) | `KOMITMEN −bayar`, `REALISASI +bayar` | sama |
| Transaksi dibatalkan sebelum bayar | `KOMITMEN −nilai` | sama |
| Reversal setelah bayar | `REALISASI −nilai` (transaksi reversal baru) | sama |

Validasi pengajuan transaksi: rincian aktif dan tidak terkunci paket, `harga = rincian.harga`, `nilai <= rincian.nilai − rincian.realisasi − rincian.komitmen`, `nilai <= pagu.sisa`, `nilai <= posisi_dana.tersedia` (4.15). Jika harga berbeda, respons `422` dengan kode `HARGA_BERBEDA` dan tautan ke halaman revisi rincian (UAT-20).

### 4.10 Ambang batas

Karena rumus final menunggu PRD K-6, rumus diimplementasikan sebagai *strategy* yang dapat diganti:

```
kapasitas_tersedia(tahun, sumber_dana BLUD)
  = min( persen_ambang_batas × Σ pagu_berlaku belanja bersumber pendapatan BLUD,
         max(0, realisasi_pendapatan_BLUD − target_pendapatan) )
    − Σ penggunaan_ambang_batas yang disahkan
```

Penggunaan disahkan melalui workflow, lalu dicatat sebagai mutasi `PAGU +nilai` dengan `sumber_tipe='AMBANG_BATAS'`.

### 4.11 Penomoran dokumen

```sql
UPDATE nomor_dokumen SET nomor_terakhir = nomor_terakhir + 1
WHERE jenis = :jenis AND tahun = :tahun
RETURNING nomor_terakhir, format;
```

Dijalankan di dalam transaksi bisnis yang sama sehingga nomor tidak loncat jika transaksi gagal. Format memakai token `{NO:4}`, `{KODE}`, `{UNIT}`, `{BULAN_ROMAWI}`, `{TAHUN}`; contoh `{NO:4}/PGS/{UNIT}/{BULAN_ROMAWI}/{TAHUN}`.

### 4.12 SP3B

1. Ambil transaksi pendapatan dan belanja berstatus terposting dalam periode, sumber dana pendapatan BLUD.
2. Petakan `rekening_blud_id` → rekening Pemda memakai `pemetaan_rekening` yang `berlaku @> tanggal_transaksi`.
3. Agregasi per rekening Pemda (tingkat sesuai format SP3B), simpan ke `sp3b_detail` sebagai snapshot.
4. Setelah SP2B dicatat: `periode.status = 'TERKUNCI_SP2B'` untuk bulan-bulan dalam periode.

### 4.13 Jurnal otomatis (Fase 3)

Tabel `aturan_jurnal(jenis_transaksi, kondisi jsonb, akun_debit, akun_kredit, basis)` dievaluasi oleh `JurnalGenerator` saat event `TransaksiDiposting`. Jurnal dibuat berstatus `DRAF` untuk ditinjau petugas akuntansi sebelum diposting; trigger deferred menjamin Σ debit = Σ kredit per jurnal.


### 4.14 Dokumen anggaran formal (Penetapan, Pergeseran ke-n, Perubahan)

```sql
CREATE TABLE dokumen_anggaran (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid            uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  tahun           smallint NOT NULL,
  jenis           text NOT NULL CHECK (jenis IN ('PENETAPAN','PERGESERAN','PERUBAHAN')),
  urutan          smallint NOT NULL,              -- Pergeseran I = 1, II = 2, ...
  nomor           text UNIQUE,
  pembanding_id   bigint REFERENCES dokumen_anggaran(id),
  status          text NOT NULL CHECK (status IN ('DRAF','DIAJUKAN','DISAHKAN','DIBATALKAN')),
  diambil_pada    timestamptz NOT NULL,           -- waktu snapshot
  disahkan_pada   timestamptz,
  total           numeric(18,2) NOT NULL,
  UNIQUE (tahun, jenis, urutan)
);

CREATE TABLE dokumen_anggaran_rincian (            -- snapshot, tidak pernah diubah
  dokumen_id        bigint NOT NULL REFERENCES dokumen_anggaran(id),
  rincian_id        bigint NOT NULL,               -- identitas stabil lintas versi
  unit_id           bigint NOT NULL,
  sumber_dana_id    bigint NOT NULL,
  rekening_blud_id  bigint NOT NULL,
  rekening_pemda_id bigint NOT NULL,
  item_id           bigint NOT NULL,
  uraian            text NOT NULL,
  volume            numeric(18,4) NOT NULL,
  satuan            text NOT NULL,
  harga             numeric(18,2) NOT NULL,
  nilai             numeric(18,2) NOT NULL,
  PRIMARY KEY (dokumen_id, rincian_id)
);
```

**Pembentukan Pergeseran ke-n (`DokumenAnggaranService::bentukPergeseran`)** — dapat dijalankan sewaktu-waktu oleh perencana; tidak terikat jadwal.

1. Pastikan tidak ada paket revisi atau pasangan yang sedang dalam proses penerapan (kunci advisori per tahun anggaran).
2. Salin seluruh `rba_rincian` aktif ke tabel snapshot dalam satu transaksi (`REPEATABLE READ`), dengan `pembanding_id` = dokumen formal terakhir yang disahkan.
3. Hitung total dan pastikan sama dengan Σ `pagu_berlaku`.
4. Buat instance workflow `DOKUMEN_ANGGARAN` (langkah akhir: Pimpinan BLUD, dapat dari kotak masuk mobile).
5. Revisi dan pergeseran berikutnya tetap dapat berjalan; perubahan setelah `diambil_pada` masuk ke dokumen berikutnya.

**Perbandingan Berkurang/Bertambah:** `FULL OUTER JOIN` snapshot dokumen ini dengan snapshot pembanding pada `rincian_id`. Rincian baru tampil dengan nilai sebelumnya 0, rincian yang dinonaktifkan tampil dengan nilai sesudah 0, dan `berkurang_bertambah = nilai_sesudah − nilai_sebelum`. Subtotal dihitung bertingkat dengan `GROUP BY ROLLUP` pada segmen kode rekening.

**Cetakan:** template Blade → PDF (Gotenberg) dan XLSX. Dua mode: `rinci` (rekening BLUD → item) dan `agregat` (rekening Pemda, menjumlahkan rekening BLUD hasil pemetaan banyak-ke-satu). Kepala dokumen (urusan, bidang, unit organisasi, sub unit, program, kegiatan, sub kegiatan, waktu, lokasi) dan penanda tangan diambil dari tabel `konfigurasi_dokumen` per tahun.

### 4.15 Pengendalian ketersediaan dana (berbasis realisasi pendapatan)

Anggaran kas triwulan tidak dipakai. Belanja dibatasi dua lapis: **sisa pagu** (`pagu_jenis`, 4.1) dan **dana tersedia** per sumber dana untuk seluruh RSUD.

```sql
CREATE TABLE posisi_dana (
  id                 bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun              smallint NOT NULL,
  sumber_dana_id     bigint NOT NULL REFERENCES sumber_dana(id),
  saldo_awal         numeric(18,2) NOT NULL DEFAULT 0,   -- saldo kas 1 Januari, termasuk SiLPA
  pendapatan_diterima numeric(18,2) NOT NULL DEFAULT 0,   -- kas diterima saja; piutang tidak dihitung
  belanja_dibayar    numeric(18,2) NOT NULL DEFAULT 0,
  komitmen           numeric(18,2) NOT NULL DEFAULT 0,   -- transaksi disetujui belum bayar + utang
  cadangan_minimum   numeric(18,2) NOT NULL DEFAULT 0,
  tersedia           numeric(18,2) GENERATED ALWAYS AS
                       (saldo_awal + pendapatan_diterima - belanja_dibayar - komitmen - cadangan_minimum) STORED,
  row_version        integer NOT NULL DEFAULT 1,
  UNIQUE (tahun, sumber_dana_id)
);

CREATE TABLE dana_mutasi (                            -- ledger append-only, pola sama dengan pagu_mutasi
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  posisi_dana_id  bigint NOT NULL REFERENCES posisi_dana(id),
  komponen        text NOT NULL CHECK (komponen IN ('SALDO_AWAL','PENDAPATAN','BELANJA','KOMITMEN','CADANGAN')),
  nilai           numeric(18,2) NOT NULL,
  sumber_tipe     text NOT NULL,                      -- 'PENERIMAAN','TRANSAKSI','UTANG','PEMBUKAAN_TAHUN','REVERSAL', ...
  sumber_id       bigint NOT NULL,
  dibuat_oleh     bigint,
  dibuat_pada     timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE jadwal_perubahan (
  id        bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun     smallint NOT NULL,
  berlaku   tstzrange NOT NULL,                        -- tanggal buka s.d. tutup (WIB)
  dasar     text NOT NULL,                             -- nomor surat/jadwal Pemda
  EXCLUDE USING gist (tahun WITH =, berlaku WITH &&)
);
```

**Mutasi per peristiwa**

| Peristiwa | `posisi_dana` | `pagu_jenis` (4.9) |
|---|---|---|
| Pembukaan tahun | `SALDO_AWAL +saldo` | — |
| Penerimaan pendapatan diposting | `PENDAPATAN +nilai` | — |
| Reversal penerimaan | `PENDAPATAN −nilai` | — |
| Transaksi belanja disetujui / utang diakui | `KOMITMEN +nilai` | `KOMITMEN +nilai` |
| Pembayaran | `KOMITMEN −bayar`, `BELANJA +bayar` | `KOMITMEN −bayar`, `REALISASI +bayar` |
| Transaksi dibatalkan sebelum bayar | `KOMITMEN −nilai` | `KOMITMEN −nilai` |
| Penetapan cadangan minimum | `CADANGAN ±selisih` | — |

**Pemeriksaan transaksi**

1. Saat **diajukan**: `nilai <= pagu.sisa` **dan** `nilai <= batas` (lihat *Cadangan kas minimum* di bawah; untuk rekening non-wajib `batas = posisi_dana.tersedia`). Jika gagal → `422 DANA_TIDAK_CUKUP` dengan rincian posisi dana.
2. Saat **disetujui** (langkah terakhir workflow): validasi ulang di dalam transaksi DB dengan urutan kunci tetap — `posisi_dana` lebih dahulu, lalu `pagu_jenis` (urut id) — kemudian mutasi `KOMITMEN` ditulis ke kedua ledger. Karena baris `posisi_dana` dikunci `FOR UPDATE`, dua persetujuan bersamaan diproses berurutan dan yang kedua melihat dana yang sudah berkurang (UAT-56). Sengaja **tidak** dipasang `CHECK (tersedia >= 0)` di database, karena reversal penerimaan atau penetapan cadangan dapat secara sah membuat dana tersedia negatif; dalam kondisi itu semua persetujuan belanja baru ditolak dan perencana serta pejabat keuangan menerima peringatan.
3. Pembayaran tidak memeriksa ulang dana tersedia (komitmen sudah dihitung), tetapi memeriksa saldo rekening kas/bank riil.
4. Satu baris `posisi_dana` per sumber dana menjadi titik serialisasi persetujuan belanja; untuk <50 pengguna ini tidak menjadi hambatan kinerja.
5. Pembentukan dan pengesahan pasangan pergeseran, revisi, perubahan RBA, dan ambang batas **tidak** menyentuh `posisi_dana`.

**Cadangan kas minimum (PRD 7.18a)**

```sql
CREATE TABLE cadangan_kas_aturan (
  id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun            smallint NOT NULL,
  sumber_dana_id   bigint NOT NULL REFERENCES sumber_dana(id),
  metode           text NOT NULL CHECK (metode IN ('NILAI_TETAP','KEBUTUHAN_BULAN')),
  nilai_tetap      numeric(18,2),
  jumlah_bulan     numeric(4,1),
  basis_bulanan    text CHECK (basis_bulanan IN ('PAGU_DIBAGI_12','RATA_REALISASI_3_BULAN')),
  status           text NOT NULL CHECK (status IN ('DRAF','DIAJUKAN','BERLAKU','DIGANTIKAN','DITOLAK')),
  berlaku_sejak    timestamptz,
  CHECK ((metode = 'NILAI_TETAP' AND nilai_tetap IS NOT NULL)
      OR (metode = 'KEBUTUHAN_BULAN' AND jumlah_bulan > 0 AND basis_bulanan IS NOT NULL))
);
CREATE UNIQUE INDEX cadangan_satu_berlaku ON cadangan_kas_aturan (tahun, sumber_dana_id) WHERE status = 'BERLAKU';

CREATE TABLE cadangan_kas_rekening (               -- daftar rekening belanja wajib
  aturan_id        bigint NOT NULL REFERENCES cadangan_kas_aturan(id),
  rekening_blud_id bigint NOT NULL REFERENCES rekening(id),   -- boleh tingkat kelompok/objek; berlaku ke turunannya
  PRIMARY KEY (aturan_id, rekening_blud_id)
);
```

- Aturan diajukan pejabat keuangan/perencana dan disetujui Pimpinan BLUD lewat workflow `CADANGAN_KAS` (dapat dari kotak masuk mobile, 6.5). Saat disetujui, aturan lama → `DIGANTIKAN`, aturan baru → `BERLAKU`, dan `posisi_dana.cadangan_minimum` diperbarui dengan mutasi `CADANGAN ±selisih`.
- Untuk metode `KEBUTUHAN_BULAN`, job `HitungUlangCadangan` (awal bulan pukul 00:30 dan saat event `PaguRekeningWajibBerubah`) menghitung `rata_bulanan × jumlah_bulan` lalu mencatat mutasi `CADANGAN` bila nilainya berubah, tanpa persetujuan ulang.
- Keanggotaan rekening wajib dievaluasi lewat leluhur rekening (rekening transaksi termasuk wajib bila dirinya atau salah satu induknya terdaftar); hasilnya di-cache per tahun dan dibersihkan saat aturan berubah.
- **Batas transaksi:**

  ```
  batas = posisi_dana.tersedia + (rekening_wajib ? posisi_dana.cadangan_minimum : 0)
  ```

  `tersedia` sudah dikurangi cadangan, sehingga belanja non-wajib tidak dapat memakai cadangan, sedangkan belanja wajib dapat memakainya.
- Dashboard menampilkan nilai cadangan, pemakaian cadangan oleh belanja wajib (Σ komitmen + belanja rekening wajib saat `tersedia < 0`), dan sisa cadangan.

**Jadwal perubahan:** `PerubahanRbaService::ajukan()` menolak dengan `422 DI_LUAR_JADWAL_PERUBAHAN` bila `now()` tidak berada dalam `jadwal_perubahan.berlaku` tahun tersebut. Draf tetap dapat disimpan. Pembentukan dokumen Pergeseran ke-n (4.14) dan penggunaan ambang batas tidak terikat jadwal.

**Dashboard posisi dana:** materialized view harian per sumber dana dan bulan (pendapatan diterima, belanja dibayar, komitmen akhir bulan), ditambah nilai *real-time* dari `posisi_dana`; daftar transaksi yang ditolak/tertahan dengan kode `DANA_TIDAK_CUKUP`. Job `RekonsiliasiPagu` diperluas untuk mencocokkan agregat `posisi_dana` dengan Σ `dana_mutasi`.


### 4.16 Rencana penarikan dana (lampiran DPA/DPPA, tidak mengikat)

```sql
CREATE TABLE rencana_penarikan (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  pagu_jenis_id   bigint NOT NULL REFERENCES pagu_jenis(id),
  triwulan        smallint NOT NULL CHECK (triwulan BETWEEN 1 AND 4),
  nilai           numeric(18,2) NOT NULL CHECK (nilai >= 0),
  UNIQUE (pagu_jenis_id, triwulan)
);

CREATE TABLE dokumen_anggaran_rencana_penarikan (  -- snapshot per dokumen formal
  dokumen_id      bigint NOT NULL REFERENCES dokumen_anggaran(id),
  pagu_jenis_id   bigint NOT NULL,
  triwulan        smallint NOT NULL,
  nilai           numeric(18,2) NOT NULL,
  PRIMARY KEY (dokumen_id, pagu_jenis_id, triwulan)
);
```

- **Tidak ada** constraint yang mengikat Σ rencana dengan pagu, dan **tidak ada** pemeriksaan transaksi terhadap rencana penarikan.
- `DokumenAnggaranService::bentuk*()` menjalankan `RencanaPenarikanValidator` sebelum dokumen dapat diajukan: setiap pagu wajib memiliki Σ rencana = `pagu_berlaku`. Pagu yang tidak sesuai dikembalikan sebagai daftar periksa (`422 RENCANA_PENARIKAN_BELUM_SESUAI`).
- **Isi otomatis:** `RATA` (pagu ÷ 4, sisa pembulatan di TW IV) atau `POLA_TAHUN_LALU` (proporsi realisasi per triwulan tahun sebelumnya untuk pagu yang sama; fallback ke `RATA`). Untuk triwulan yang sudah lewat, isi otomatis memakai nilai realisasi aktual.
- Snapshot disalin ke `dokumen_anggaran_rencana_penarikan` saat dokumen formal dibentuk dan dicetak sebagai lampiran (rinci dan agregat rekening Pemda).

---

## 5. Mesin Persetujuan (Workflow)

### 5.1 Model

- `workflow_definisi`: satu definisi per jenis objek (`RBA`, `REVISI_DI_ATAS_STANDAR`, `USULAN_STANDAR`, `PASANGAN_PERGESERAN`, `PERUBAHAN_RBA`, `AMBANG_BATAS`, `TRANSAKSI_BELANJA`, `DOKUMEN_PEMBAYARAN`, `SP3B`), berversi.
- `workflow_langkah`: urutan, nama langkah, permission yang dibutuhkan, cakupan (`UNIT_PENGUSUL`, `UNIT_ANGGOTA`, `GLOBAL`), batas nilai minimum/maksimum, `boleh_otomatis` (hanya `true` untuk langkah pengesahan perencana pada `PASANGAN_PERGESERAN`).
- `persetujuan`: instance per objek (`objek_tipe`, `objek_id`, `definisi_versi`, `langkah_aktif`, status).
- `persetujuan_aksi`: riwayat aksi (`AJUKAN`, `SETUJUI`, `TOLAK`, `KEMBALIKAN`, `SETUJUI_OTOMATIS`), pelaku, `atas_nama_id`, catatan, waktu.

Instance menyimpan versi definisi yang dipakai saat dibuat; perubahan matriks kewenangan tidak memengaruhi proses yang sedang berjalan.

### 5.2 Penentuan pemberi persetujuan

```
kandidat(langkah, objek) =
    pengguna dengan permission langkah.permission
    AND (cakupan GLOBAL OR unit pengguna ∈ unit objek sesuai cakupan)
  ∪ pelaksana delegasi aktif (now() ∈ delegasi.berlaku) dari pejabat yang memenuhi syarat di atas
  − pengguna yang telah mengajukan / menyetujui langkah sebelumnya pada objek yang sama   -- pemisahan tugas
```

Pemeriksaan pemisahan tugas dilakukan di `WorkflowService::setujui()` dan di Policy, bukan hanya dengan menyembunyikan tombol (UAT-27). Jika pelaku bertindak sebagai delegasi, `persetujuan_aksi.atas_nama_id` dan `audit_log.atas_nama_id` diisi pejabat definitif (UAT-28).

---

## 6. Antarmuka Aplikasi

### 6.1 Pola endpoint

- Halaman: route Inertia (`GET`) merender komponen Vue dengan props.
- Aksi: `POST/PUT/DELETE` mengembalikan redirect Inertia atau JSON untuk interaksi parsial.
- Permintaan dengan header `Precognition: true` hanya menjalankan validasi tanpa efek samping.
- Notifikasi real-time dikirim lewat Reverb pada saluran privat `user.{id}` dan `unit.{id}`; notifikasi tetap tersimpan di tabel `notifications` dan dikirim email sebagai cadangan.
- Semua aksi mutasi menerima header/field `row_version` objek utama; jika berbeda → `409 KONFLIK_VERSI`.
- Kesalahan domain memakai format:

```json
{
  "kode": "SISA_PAGU_TIDAK_CUKUP",
  "pesan": "Sisa pagu Belanja Barang dan Jasa unit Farmasi Rp 12.500.000, kurang dari nilai pengajuan Rp 15.000.000.",
  "tindakan": { "label": "Lihat rincian pagu", "url": "/anggaran/pagu/123" },
  "detail": { "sisa": "12500000.00", "diminta": "15000000.00" }
}
```

Pesan selalu menyebut masalah dan arah perbaikan (NFR-009).

### 6.2 Endpoint utama

| Metode | Path | Fungsi | FR |
|---|---|---|---|
| POST | `/kodefikasi/impor` | Impor struktur rekening/nomenklatur dari Excel | FR-090 |
| GET | `/kodefikasi/pemetaan/belum` | Rekening BLUD yang belum terpetakan | FR-003 |
| POST | `/kodefikasi/pemetaan/impor` | Pemetaan massal | FR-003 |
| POST | `/standar-harga/impor` | Unggah impor standar Pemda → validasi → pratinjau | FR-013–015 |
| POST | `/standar-harga/impor/{id}/konfirmasi` | Aktifkan hasil impor | FR-013 |
| GET | `/standar-harga/cocok?q=&satuan=&harga=` | Pengecekan kecocokan langsung | FR-017 |
| POST | `/standar-harga/usulan` | Ajukan usulan standar (formulir; mendukung konteks `TRANSAKSI`/`REVISI` untuk kembali ke halaman asal setelah disahkan) | FR-016, FR-103 |
| GET | `/standar-harga/usulan/template` | Unduh template Excel | FR-104 |
| POST | `/standar-harga/usulan/impor` | Unggah Excel → validasi + pencocokan → pratinjau | FR-104 |
| POST | `/standar-harga/usulan/batch/{uuid}/ajukan` | Ajukan batch | FR-104 |
| POST | `/standar-harga/usulan/batch/{uuid}/sahkan-memenuhi-syarat` | Sahkan sekaligus baris tanpa kecocokan tinggi | FR-105 |
| POST | `/standar-harga/usulan/{id}/putuskan` | Keputusan perencana | FR-018 |
| GET/POST | `/rba/{rba}/rincian` | Penyusunan rincian | FR-022–025 |
| POST | `/rba/{rba}/ajukan`, `/rba/{rba}/tetapkan-definitif` | Alur RBA | FR-026–030 |
| GET | `/revisi/edit/{paguJenis}` | Halaman edit rincian (membuat/membuka draf) | FR-031 |
| PUT | `/revisi/{paket}/baris` | Simpan otomatis baris draf | FR-032 |
| GET | `/revisi/{paket}/ringkasan` | Indikator status pagu | FR-091 |
| POST | `/revisi/{paket}/simpan` | Simpan; `409 PERLU_PERSETUJUAN` bila pagu berubah | FR-092 |
| POST | `/revisi/{paket}/kirim-pergeseran` | Kirim selisih sebagai permintaan | FR-092, FR-093 |
| GET | `/pergeseran/terbuka?jenis=&sumber=` | Daftar permintaan terbuka | FR-097 |
| POST | `/pergeseran/permintaan` | Permintaan langsung (tanpa halaman revisi) | FR-039 |
| POST | `/pergeseran/pasangan` | Bentuk pasangan | FR-096 |
| POST | `/pergeseran/pasangan/{id}/persetujuan-unit` | Persetujuan unit anggota | FR-097 |
| POST | `/pergeseran/pasangan/{id}/sahkan` / `/tolak` | Keputusan perencana | FR-042, FR-101 |
| POST | `/transaksi-belanja` | Buat transaksi | FR-051–054 |
| POST | `/sp3b/{periode}/susun` | Susun SP3B | FR-074 |
| GET | `/laporan/{kode}?format=pdf|xlsx` | Laporan | FR-083, FR-089 |

### 6.3 Halaman edit rincian

Tata letak:

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Unit Farmasi · 5.1.02 Belanja Barang dan Jasa · Sumber: Jasa Layanan    │
│ ┌───────────────────────────────────────────────────────────────────┐   │
│ │ Pagu jenis Rp 500.000.000 │ Total rincian Rp 505.000.000          │   │
│ │ ● PAGU BERUBAH: bertambah Rp 5.000.000 — perlu persetujuan        │   │  ← sticky
│ └───────────────────────────────────────────────────────────────────┘   │
│ [+ Tambah rincian]   Filter …                       Draf tersimpan 23:41│
│ ┌──────┬─────────────────┬────────┬──────────┬───────────┬──────────┐   │
│ │ Kode │ Uraian/item     │ Volume │ Harga    │ Nilai     │ Status   │   │
│ ├──────┼─────────────────┼────────┼──────────┼───────────┼──────────┤   │
│ │ …    │ … (terkunci 🔒 PGS-0012)                          │ menunggu │   │
│ │ …    │ …               │ 10     │ 120.000▲ │ 1.200.000 │ > standar│   │
│ └──────┴─────────────────┴────────┴──────────┴───────────┴──────────┘   │
│                                   [Batalkan]  [Simpan]                   │
└─────────────────────────────────────────────────────────────────────────┘
```

Perilaku:

- Indikator berwarna netral saat **Seimbang**, kuning saat **Pagu berubah**, dan menampilkan arah serta nilai selisih.
- Kolom item memakai pencarian standar harga; jika tidak ditemukan, tautan "Usulkan standar harga baru" membuka formulir usulan dengan pengecekan kecocokan langsung. Setelah usulan disahkan, notifikasi membawa pengguna kembali ke draf revisi yang sama.
- Indikator menampilkan keterangan aturan yang berlaku untuk jenis belanja tersebut: *"Belanja Barang dan Jasa — revisi seimbang langsung berlaku"* atau *"Belanja Modal — revisi memerlukan persetujuan perencana"*.
- Harga di atas standar ditandai ▲ dan meminta lampiran bukti pada baris tersebut.
- Rincian terkunci tampil abu-abu dengan tautan ke paket/transaksi yang menguncinya.
- **Simpan** dengan pagu berubah → dialog: *"Perubahan ini mengubah pagu Belanja Barang dan Jasa unit Anda sebesar +Rp 5.000.000 dan memerlukan persetujuan melalui pergeseran antarunit."* Tombol: **Kirim sebagai permintaan pergeseran** · **Lanjutkan edit** · **Batalkan perubahan**.
- Setelah kirim, tampil notifikasi dengan nomor permintaan, batas waktu pasangan, dan tombol **Edit rincian lain**.

### 6.4 Halaman pergeseran

- **Daftar permintaan terbuka:** dikelompokkan per jenis belanja dan sumber dana; kolom unit, arah, nilai, sisa waktu (hari kerja), status. Perencana dapat memilih beberapa baris lalu **Bentuk pasangan**; tombol aktif hanya bila Σ tambah = Σ kurang.
- **Detail pasangan:** anggota, persetujuan unit, ringkasan dampak pagu per unit (sebelum/sesudah), paket revisi terkait, lampiran, linimasa persetujuan, dan hitung mundur batas pengesahan. Label tegas "Tidak dapat disetujui otomatis — memuat harga di atas standar" bila berlaku.

### 6.5 Kotak masuk persetujuan *mobile-first* (Pimpinan BLUD)

#### 6.5.1 Cakupan

Dipakai untuk setiap langkah workflow yang ditugaskan kepada peran **Pimpinan BLUD** (sesuai matriks kewenangan: RBA, perubahan RBA, ambang batas, SP3B, dan pengajuan di atas batas nilai tertentu). Pejabat penyetuju lain dapat memilih mode ini melalui pengaturan profil. Mode ini hanya untuk **meninjau dan memutuskan**; penyusunan dan pengeditan tetap di tampilan desktop.

#### 6.5.2 Arsitektur

| Aspek | Desain |
|---|---|
| Rute | Grup rute `/m/*` dengan layout Inertia terpisah (`MobileLayout`) yang ringan, tanpa sidebar dan tabel lebar |
| Aplikasi web progresif (PWA) | `manifest.webmanifest` (nama, ikon, `display: standalone`) dan service worker yang hanya menyimpan **cangkang aplikasi** (CSS, JS, ikon) di cache; **data keuangan tidak disimpan offline** |
| Notifikasi | Web Push (VAPID) melalui kanal notifikasi Laravel, ditambah Reverb saat aplikasi terbuka dan email sebagai cadangan; tautan notifikasi membuka langsung `/m/persetujuan/{uuid}` |
| Konfirmasi identitas | **WebAuthn** dengan autentikator perangkat (sidik jari/wajah) sebagai pilihan utama; **PIN 6 digit** (hash argon2id) sebagai cadangan. Menghasilkan token *step-up* berumur 5 menit yang wajib disertakan pada aksi Setujui/Tolak/Kembalikan |
| Ringkasan | Antarmuka `RingkasanPersetujuan` diimplementasikan per jenis objek (`RbaRingkasan`, `Sp3bRingkasan`, `PasanganRingkasan`, `AmbangBatasRingkasan`, `PerubahanRbaRingkasan`, `TransaksiRingkasan`) dan menghasilkan struktur seragam: judul, nilai utama, dampak (sebelum → sesudah), daftar penyetuju sebelumnya, catatan penting, tautan lampiran |
| Anggaran kinerja | JS awal ≤ 150 KB (gzip), LCP ≤ 2,5 detik pada jaringan 4G, target sentuh minimal 48 × 48 px, kontras teks memenuhi WCAG AA |

#### 6.5.3 Tata letak

```
┌──────────────────────────┐   ┌──────────────────────────┐   ┌──────────────────────────┐
│ Persetujuan        (3)   │   │ ← SP3B Triwulan III      │   │ Konfirmasi               │
│ ┌──────────────────────┐ │   │                          │   │                          │
│ │ SP3B Triwulan III    │ │   │ Rp 4.812.500.000         │   │  Setujui SP3B Triwulan   │
│ │ Rp 4,81 M · 2 hari   │ │   │ Pendapatan & belanja BLUD│   │  III senilai             │
│ │ Pejabat Keuangan ✓   │ │   │                          │   │  Rp 4.812.500.000?       │
│ └──────────────────────┘ │   │ Dampak                   │   │                          │
│ ┌──────────────────────┐ │   │ Pendapatan  Rp 5,10 M    │   │   [ sidik jari / PIN ]   │
│ │ Ambang batas · Lab   │ │   │ Belanja     Rp 4,81 M    │   │                          │
│ │ Rp 250 jt · MENDESAK │ │   │                          │   │                          │
│ └──────────────────────┘ │   │ Sudah menyetujui         │   │                          │
│ ┌──────────────────────┐ │   │ ✓ Kasubbag Keuangan      │   │                          │
│ │ Perubahan RBA        │ │   │ ✓ Pejabat Keuangan       │   │                          │
│ │ Rp 1,2 M · 4 hari    │ │   │                          │   │                          │
│ └──────────────────────┘ │   │ Lampiran (2)   Rincian › │   │                          │
│                          │   ├──────────────────────────┤   │                          │
│                          │   │ [Kembalikan] [Tolak]     │   │                          │
│                          │   │ [      SETUJUI       ]   │   │      [ Batal ]           │
└──────────────────────────┘   └──────────────────────────┘   └──────────────────────────┘
     1. Kotak masuk                2. Ringkasan                    3. Konfirmasi
```

- **Kotak masuk:** kartu diurutkan dari yang paling mendesak (mendekati batas waktu) lalu terlama. Tarik ke bawah untuk memuat ulang. Tab sekunder: *Sudah diputuskan* (30 hari terakhir).
- **Ringkasan:** satu kolom; nilai utama besar di atas; tombol aksi menempel di bawah layar (jangkauan ibu jari). "Rincian ›" membuka tampilan baca lengkap; lampiran PDF/gambar dibuka dengan penampil bawaan perangkat lewat URL bertanda tangan.
- **Tolak/Kembalikan:** membuka lembar bawah (*bottom sheet*) dengan kolom catatan wajib (minimal 10 karakter) dan pilihan alasan cepat.
- **Alur ≤ 3 ketukan dari notifikasi:** ketuk notifikasi → ketuk **Setujui** → konfirmasi sidik jari/PIN.
- Bila pimpinan bertindak sebagai pelaksana delegasi, kartu menampilkan "atas nama …".

#### 6.5.4 Endpoint

| Metode | Path | Fungsi |
|---|---|---|
| GET | `/m/persetujuan` | Daftar tugas persetujuan pengguna (termasuk sebagai delegasi) |
| GET | `/m/persetujuan/{uuid}` | Ringkasan satu pengajuan |
| POST | `/m/step-up` | Verifikasi WebAuthn/PIN → token step-up |
| POST | `/m/persetujuan/{uuid}/setujui` · `/tolak` · `/kembalikan` | Aksi; wajib token step-up dan `row_version` |
| POST | `/m/push/langganan` | Mendaftarkan perangkat untuk Web Push |

Aksi memanggil `WorkflowService` yang sama dengan tampilan desktop, sehingga aturan pemisahan tugas, delegasi, validasi ulang, dan audit berlaku identik. Audit mencatat kanal (`MOBILE`), metode konfirmasi (`WEBAUTHN`/`PIN`), dan identitas perangkat.

---

## 7. Keamanan

| Aspek | Desain |
|---|---|
| Autentikasi | Fortify; sandi minimal 12 karakter, bcrypt/argon2id; penguncian 5 kali gagal/15 menit; SSO Pemda (OIDC/SAML) disiapkan sebagai adaptor jika tersedia |
| MFA | TOTP wajib untuk peran pengesah, perencana, bendahara, pejabat keuangan, administrator |
| Step-up pada aksi persetujuan mobile | WebAuthn (biometrik perangkat) atau PIN; token berumur 5 menit, terikat pada sesi dan perangkat; 5 kali PIN salah mengunci step-up 15 menit |
| Sesi | Redis, `secure`/`httponly`/`samesite=lax`, idle timeout 30 menit, absolut 8 jam |
| Otorisasi | Permission granular (`revisi.ajukan`, `pergeseran.sahkan`, …); Policy memeriksa permission **dan** cakupan unit; global scope Eloquent `UnitScope` membatasi query sesuai unit pengguna |
| Pemisahan tugas | Aturan di `WorkflowService`, diuji otomatis |
| Lampiran | Validasi MIME dari isi file (bukan ekstensi), daftar putih (PDF, JPG, PNG, XLSX, DOCX), maks 10 MB (dapat dikonfigurasi), nama file acak, disimpan di luar web root, unduhan lewat URL bertanda tangan berumur pendek, pemindaian ClamAV opsional |
| Transport | HTTPS saja, HSTS |
| Header | CSP ketat, `X-Frame-Options: DENY`, `Referrer-Policy` |
| Data pasien | Impor tagihan/klaim hanya menyimpan data minimum (nomor tagihan, nomor SEP/klaim, nilai, tanggal); nama pasien opsional dan disamarkan pada laporan non-detail |
| Rahasia | `.env` di luar repositori; kunci aplikasi dan kredensial DB dirotasi saat serah terima |
| DB | Peran terpisah: `app_rw` (tanpa UPDATE/DELETE pada `audit_log`, `pagu_mutasi`), `app_ro` (laporan), `migrator` |
| Pembatasan laju | Login dan endpoint pencocokan dibatasi per pengguna |

---

## 8. Impor dan Ekspor

### 8.1 Alur impor umum

```
unggah file ─▶ impor_batch (DIUNGGAH) ─▶ job validasi per chunk 1.000 baris ─▶ tabel staging
          ─▶ impor_baris_gagal (baris, kolom, pesan) ─▶ pratinjau (valid/gagal/calon duplikat)
          ─▶ konfirmasi pengguna berwenang ─▶ job aktivasi (transaksi tunggal) ─▶ SELESAI
```

- File asli disimpan dengan hash SHA-256 sebagai bukti.
- Laporan kesalahan dapat diunduh dalam Excel dengan kolom tambahan "Pesan kesalahan".
- Kebijakan baris valid pada impor gagal sebagian mengikuti PRD K-9; default sementara: **semua atau tidak sama sekali**.

### 8.2 Template impor

| Template | Kolom wajib |
|---|---|
| Kodefikasi rekening | kode, uraian, tingkat, kode_induk, dapat_dianggarkan |
| Nomenklatur | kode, uraian, tingkat, kode_induk |
| Pemetaan rekening | kode_rekening_blud, kode_rekening_pemda, berlaku_mulai |
| Standar harga Pemda | kode_barang, uraian, spesifikasi, satuan, harga, kode_rekening (opsional) |
| Kalender libur | tanggal, jenis, keterangan |
| Tagihan layanan | nomor_tagihan, tanggal, debitur/penjamin, nilai, unit layanan |
| Klaim BPJS | nomor_klaim/SEP, periode layanan, nilai_diajukan, nilai_disetujui, status, tanggal_status |
| Saldo awal (migrasi) | per jenis saldo: utang, piutang, kas/bank, pajak belum setor |
| RBA format lama | file Lampiran Rincian Anggaran Belanja apa adanya (lihat 8.4) |
| Pemetaan label unit | label, kode_unit |
| Saldo kas awal tahun | sumber_dana, saldo |
| Jadwal perubahan | tahun, tanggal_buka, tanggal_tutup, dasar |
| Rencana penarikan | kode_unit, kode_jenis, sumber_dana, tw1, tw2, tw3, tw4 |
| Rekening wajib cadangan | kode_rekening_blud |

Format final mengikuti format resmi Pemda dan ekspor SIMRS (PRD K-13); parser dibuat per template sebagai kelas tersendiri agar mudah disesuaikan.

### 8.4 Impor RBA format lama (Lampiran Rincian Anggaran Belanja)

Contoh struktur yang dibaca (berdasarkan dokumen RBA BLUD yang dipakai sebagai acuan):

| Bagian | Ciri di file | Penanganan |
|---|---|---|
| Kepala dokumen | Baris berlabel *Urusan Pemerintahan*, *Bidang*, *Unit Organisasi*, *Sub Unit Organisasi*, *Program*, *Kegiatan*, *Sub Kegiatan*, *Waktu*, *Lokasi* | Dipetakan ke `konfigurasi_dokumen` dan nomenklatur |
| Baris judul tabel | Sel berisi `KODE REKENING`, `KOMPONEN`, `Jml`, `Satuan`, `Harga Satuan`, `Jumlah` | Menentukan posisi kolom secara dinamis (tidak bergantung huruf kolom tetap) |
| Rekening Pemda | Kode bertitik utuh, mis. `5.1.02.99.99.9999` | Validasi terhadap `rekening` bagan PEMDA |
| Rekening rinci BLUD | Segmen kode di beberapa kolom, mis. `01.` `01.` `0037` di bawah jenis `5.1.02` | Disusun ulang menjadi `5.1.02.01.01.0037` berdasarkan jenis induk terakhir |
| Komponen | Baris dengan jumlah, satuan, harga, jumlah | Menjadi baris staging |
| Sub-komponen bernilai | Baris uraian tanpa kode di bawah komponen (mis. "Belanja ATK", "Belanja Linen") | Menjadi kandidat pemecahan item |
| Label penanggung jawab | Kolom teks bebas (mis. UMUM, Keu, Binpro, Penunjang, IBS-Bedah Saraf) | Dipetakan lewat `pemetaan_label_unit` |
| Beberapa sheet versi | Sheet Penetapan, Penetapan→Pergeseran, Pergeseran→Perubahan | Pengguna memilih sheet sumber; kolom kedua (sesudah) dipakai bila sheet pembanding |
| Gangguan | `#REF!`, catatan manual ("smp Nov", "Minus 200 Jt"), sel kosong | Dilaporkan sebagai peringatan; tidak menghentikan pembacaan |

**Tabel staging**

```sql
CREATE TABLE staging_rba (
  id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  impor_batch_id   bigint NOT NULL REFERENCES impor_batch(id),
  sheet            text NOT NULL,
  baris_sumber     integer NOT NULL,
  kode_rekening    text,
  rekening_blud_id bigint REFERENCES rekening(id),
  komponen         text NOT NULL,
  volume           numeric(18,4),
  satuan           text,
  harga            numeric(18,2),
  nilai_sumber     numeric(18,2),
  label_unit       text,
  unit_id          bigint REFERENCES unit_kerja(id),
  status           text NOT NULL CHECK (status IN ('PERLU_DIRINCI','PERLU_UNIT','SIAP','DIABAIKAN','GAGAL')),
  peringatan       text[]
);

CREATE TABLE staging_rba_item (                    -- hasil pemecahan baris gelondongan
  staging_id       bigint NOT NULL REFERENCES staging_rba(id),
  item_id          bigint NOT NULL REFERENCES standar_harga_item(id),
  volume           numeric(18,4) NOT NULL,
  harga            numeric(18,2) NOT NULL,
  nilai            numeric(18,2) GENERATED ALWAYS AS (round(volume * harga, 2)) STORED,
  unit_id          bigint REFERENCES unit_kerja(id)
);
```

**Alur**

1. Parser (`LampiranRbaParser`) membaca file dengan mode *streaming* (file contoh berukuran ±22 MB dengan puluhan sheet) dan hanya memproses sheet yang dipilih.
2. Setiap komponen menjadi baris `staging_rba`. Baris yang tidak memiliki item standar langsung berstatus `PERLU_DIRINCI`; sistem menampilkan kandidat item dari `StandarHargaMatcher` berdasarkan uraian komponen dan sub-komponennya.
3. Pengguna memecah baris menjadi `staging_rba_item` (pencarian item, impor Excel pecahan, atau salin dari tahun sebelumnya) dan memilih unit; label penanggung jawab yang sudah dipetakan mengisi unit otomatis.
4. **Rekonsiliasi:** untuk setiap baris, Σ `staging_rba_item.nilai` harus sama dengan `nilai_sumber`; untuk setiap rekening, total staging harus sama dengan total file. Selisih ditampilkan per rekening.
5. Bila seluruh baris `SIAP` atau `DIABAIKAN` (dengan alasan) dan total cocok, sistem membentuk `rba` berstatus `DRAF` beserta `rba_rincian`, lalu mengikuti alur RBA biasa.

### 8.3 Ekspor

- Semua laporan: XLSX dan PDF; data mentah: CSV UTF-8.
- Ekspor SIPD (Fase 3): data anggaran dan realisasi dalam rekening Pemda hasil pemetaan; struktur kolom menyesuaikan format yang ditetapkan (PRD K-16).

---

## 9. Pelaporan

- Laporan operasional dibaca langsung dari tabel transaksional memakai peran `app_ro`.
- Laporan berat (konsolidasi, umur piutang, SP3B) memakai *materialized view* yang di-*refresh* oleh job atau saat dibutuhkan, dengan cap waktu data ditampilkan di laporan.
- Template laporan resmi dibuat sebagai Blade → PDF via Gotenberg; setiap cetakan menyimpan hash dan parameter cetak di `audit_log`.

| Laporan | Sumber |
|---|---|
| Lampiran Rincian Anggaran Belanja (Penetapan/Pergeseran ke-n/Perubahan), rinci dan agregat | `dokumen_anggaran_rincian` (4.14) |
| Posisi dana: pendapatan vs belanja, komitmen, transaksi tertahan | `posisi_dana`, `dana_mutasi`, materialized view bulanan |
| Perbandingan antar tahun | `dokumen_anggaran_rincian` tahun n dan n−1 per rekening |
| Pagu per unit × jenis × sumber dana (baseline, revisi, pergeseran masuk/keluar, perubahan, ambang batas, berlaku, realisasi, komitmen, tercadang, sisa) | `pagu_jenis` + agregasi `pagu_mutasi` per `sumber_tipe` |
| Riwayat revisi dan pergeseran | `revisi_paket`, `permintaan_pergeseran`, `pasangan_pergeseran`, `persetujuan_aksi` |
| Persetujuan otomatis | `persetujuan_aksi` dengan aksi `SETUJUI_OTOMATIS` |
| Rincian di atas standar | `rba_rincian` dengan `di_atas_standar` + lampiran |
| BKU, buku pembantu, SPJ | modul Bendahara |
| Pajak | `pajak_transaksi`, `setoran_pajak` |
| Umur utang/piutang | `utang`, `tagihan`, `klaim` |

---

## 10. Infrastruktur dan Operasional

### 10.1 Topologi

Untuk <50 pengguna, satu server aplikasi dan satu server basis data sudah memadai:

| Server | Spesifikasi awal | Isi |
|---|---|---|
| Aplikasi | 4 vCPU, 8 GB RAM, 100 GB SSD | Nginx, PHP-FPM, Redis, worker Horizon, scheduler, Reverb, Gotenberg, MinIO (atau disk terpisah) |
| Basis data | 4 vCPU, 8 GB RAM, 200 GB SSD | PostgreSQL |

Seluruh komponen dijalankan dengan Docker Compose; lingkungan **pengembangan**, **staging/UAT**, dan **produksi** terpisah. Staging memakai salinan data produksi yang dianonimkan.

### 10.2 Cadangan dan pemulihan

| Item | Usulan (dikonfirmasi pada PRD K-17) |
|---|---|
| Basis data | pgBackRest: full mingguan, differential harian, WAL archiving berkelanjutan |
| RPO | ≤ 15 menit |
| RTO | ≤ 4 jam |
| Lampiran | Sinkronisasi harian ke penyimpanan cadangan di lokasi berbeda |
| Uji pemulihan | Bulanan ke server staging, hasil dicatat |
| Retensi cadangan | 35 hari harian, 12 bulan bulanan |

### 10.3 Pemantauan

- Kesehatan: endpoint `/up`, pemantauan uptime eksternal.
- Antrian: Horizon (job gagal, waktu tunggu).
- Job terjadwal: setiap job menulis waktu sukses terakhir; peringatan jika `ProsesBatasPasangan` atau `ProsesBatasPengesahan` tidak sukses > 1 jam.
- Log terstruktur JSON dengan `request_id`, dirotasi 90 hari.
- Peringatan email/WhatsApp ke admin TI untuk kegagalan backup, rekonsiliasi pagu, dan rantai audit.

### 10.4 Kinerja

| Operasi | Target awal |
|---|---|
| Halaman umum | p95 < 1 detik |
| Ringkasan indikator pagu | p95 < 300 ms |
| Pencocokan standar harga (≤ 50.000 item) | p95 < 500 ms |
| Simpan paket revisi (≤ 200 baris) | p95 < 2 detik |
| Impor standar harga 20.000 baris | < 5 menit (asinkron) |

Indeks utama: `rba_rincian (rba_id, unit_id, jenis_id, sumber_dana_id) WHERE aktif`, GIN trigram pada `standar_harga_item.uraian_norm`, `permintaan_pergeseran (status, batas_pasangan)`, `pagu_mutasi (pagu_jenis_id, dibuat_pada)`, `audit_log (tabel, objek_id)`.

---

## 11. Strategi Pengujian

| Lapisan | Alat | Fokus |
|---|---|---|
| Unit | Pest | `KalenderKerja`, normalisasi dan skor kecocokan, kalkulasi selisih revisi, rumus ambang batas, format penomoran |
| Fitur | Pest + PostgreSQL nyata (bukan SQLite) | Setiap UAT PRD menjadi satu atau lebih test; nama test memuat ID UAT |
| Constraint DB | Pest | Memastikan constraint menolak data tidak sah walau service di-*bypass* |
| Konkurensi | Pest + proses paralel | Dua transaksi bersamaan atas sisa pagu terakhir: tepat satu berhasil; dua pengesahan bersamaan pada pasangan yang sama: tepat satu berhasil |
| Waktu | `Carbon::setTestNow` + data kalender | Batas pasangan/pengesahan melewati akhir pekan dan libur (UAT-42), kedaluwarsa (UAT-40, 41, 43) |
| UI | Playwright | Indikator pagu, dialog simpan, peringatan keluar halaman, mode baris terkunci (UAT-31–35) |
| Keamanan | Test otorisasi | Setiap endpoint mutasi diuji dengan peran tanpa izin dan unit di luar cakupan; pemisahan tugas (UAT-27) |
| Migrasi | Skrip rekonsiliasi | Jumlah baris, total nilai, saldo per jenis |

Target cakupan: 100% untuk modul `Anggaran`, `Revisi`, `Pergeseran`, `Workflow`; ≥ 80% keseluruhan. CI menjalankan seluruh test, Larastan level ≥ 8, Pint, dan architecture test pada setiap *pull request* (lihat 16.5).

---

## 12. Migrasi Data

1. Buat skema di staging; impor kodefikasi, nomenklatur, pemetaan, unit, sumber dana, pengguna dan peran.
2. Impor dan bersihkan standar harga (jalankan pencocokan untuk deduplikasi).
3. Impor master unit dan pemetaan label penanggung jawab; impor standar harga internal (termasuk obat dan BMHP) sebelum RBA.
3a. Impor RBA definitif tahun berjalan dari file Lampiran Rincian Anggaran Belanja melalui staging (8.4); baris gelondongan dipecah ke item standar dan diberi unit; setelah rekonsiliasi total cocok, sistem membentuk `rba`, `pagu_jenis`, mutasi `RBA_DEFINITIF`, dan dokumen formal `PENETAPAN`. Saldo kas awal tahun, realisasi pendapatan, dan belanja s.d. cutover diimpor ke `posisi_dana` melalui mutasi `MIGRASI`. Bila tahun berjalan sudah memiliki Pergeseran, versi terakhir diimpor sebagai baseline dan dokumen sebelumnya disimpan sebagai arsip pembanding.
4. Impor realisasi s.d. tanggal cutover sebagai transaksi saldo (`sumber_tipe='MIGRASI'`) per rincian.
5. Impor saldo awal utang, piutang/klaim berjalan, kas/bank, pajak belum disetor.
6. Jalankan `RekonsiliasiPagu` dan laporan pembanding vs data lama; pemilik data menandatangani berita acara.
7. Cutover disarankan pada awal tahun anggaran atau awal periode SP3B.

---

## 13. Rencana Pengembangan Teknis

| Iterasi (2 minggu) | Cakupan | FR utama |
|---|---|---|
| 0 | Fondasi SDLC 2.0: repositori, `AGENTS.md`/`CLAUDE.md`, template spec, branch protection, pipeline CI/CD, Laravel Boost, lingkungan staging | — |
| 1 | Kerangka proyek Laravel 13, autentikasi + MFA, RBAC, audit, kalender, penomoran | FR-008–012, FR-088, FR-100 |
| 2 | Kodefikasi, nomenklatur, pemetaan + impor | FR-002–005, FR-090 |
| 3 | Standar harga: impor, usulan, pencocokan | FR-013–021 |
| 4 | Master unit, RBA, rincian, workflow RBA, penetapan definitif, pagu jenis + ledger, jadwal perubahan | FR-022–030, FR-110, FR-119 |
| 4a | Impor RBA format lama + staging + rekonsiliasi | FR-117 |
| 5 | Revisi rincian + halaman edit + indikator | FR-031–038, FR-091–095 |
| 6 | Permintaan, pasangan, pencadangan, batas waktu, persetujuan otomatis | FR-039–046, FR-096–102 |
| 7 | Perubahan RBA, dokumen formal + cetakan Berkurang/Bertambah, kotak masuk persetujuan *mobile-first* (PWA, Web Push, WebAuthn), laporan anggaran, UAT Fase 1 | FR-047, FR-050, FR-107–113, FR-118 |
| 8–12 | Fase 2 (termasuk `posisi_dana` dan dashboard posisi dana, FR-114–116): transaksi, dokumen pembayaran, BKU/SPJ, pajak, utang, piutang/klaim, ambang batas, SP3B | FR-048–049, FR-051–076, FR-078 |
| 13+ | Fase 3: akuntansi, persediaan/aset, laporan keuangan, ekspor SIPD | FR-077, FR-079–083 |

---

## 14. Keterlacakan PRD → Komponen

| PRD | Komponen teknis |
|---|---|
| 7.2 Rekening & pemetaan | `rekening`, `pemetaan_rekening` (+EXCLUDE, trigger tingkat), modul Kodefikasi |
| 7.4 / 7.4a Standar harga | `standar_harga_item` (GIN trigram, `berlaku_mulai`, `tambahan_tengah_tahun`), `usulan_*`, `usulan_standar_batch`, `StandarHargaMatcher` (4.3) |
| 7.6 Pengendalian pagu | `pagu_jenis` (CHECK sisa ≥ 0), `pagu_mutasi`, `PaguLedger` (4.1) |
| 7.7 Revisi rincian | `revisi_paket/detail`, `RevisiService::simpan` (4.4), halaman 6.3 |
| 7.8 / 7.8a Pergeseran | `permintaan_pergeseran`, `pasangan_*` (CHECK seimbang), `PasanganService` (4.5), job 4.7, persetujuan otomatis 4.8 |
| 7.9 Perubahan RBA & ambang batas | `perubahan_rba`, `penggunaan_ambang_batas`, strategy 4.10 |
| 7.10–7.12 Penatausahaan, utang, piutang | 4.9, modul Penatausahaan/Utang/Piutang |
| 7.13 SP3B | 4.12, kunci periode 3.9 |
| 7.14 Pajak | modul Pajak, job rekonsiliasi |
| 7.15 Persetujuan & delegasi | Workflow (5), `delegasi`, kotak masuk mobile (6.5) |
| 7.16 Unit kerja | `unit_kerja`, `rba_rincian.unit_id NOT NULL` |
| 7.17 Dokumen formal | `dokumen_anggaran*` (4.14) |
| 7.18 Ketersediaan dana | `posisi_dana`, `dana_mutasi` (4.15) |
| 7.9 Jadwal perubahan | `jadwal_perubahan` (4.15) |
| 7.18a Cadangan kas minimum | `cadangan_kas_aturan`, `cadangan_kas_rekening`, job `HitungUlangCadangan` (4.15) |
| 7.17a Rencana penarikan | `rencana_penarikan`, snapshot dokumen (4.16) |
| 7.19 Impor RBA lama | `LampiranRbaParser`, `staging_rba*` (8.4) |
| NFR-001–012 | Bagian 7 dan 10 |

---

## 15. Asumsi Teknis dan Isu Terbuka

### 15.1 Asumsi yang perlu dikonfirmasi

| No | Asumsi |
|---|---|
| T-1 | Hitungan 5 hari kerja dimulai dari hari kerja setelah hari pengajuan, berakhir pukul 23:59 WIB hari kerja ke-5 |
| T-2 | Saat pasangan ditolak oleh salah satu unit, permintaan kembali terbuka dengan batas waktu awal (tidak di-*reset*) |
| T-3 | Selisih pembulatan `volume × harga` dibulatkan ke rupiah penuh (2 desimal disimpan); total rincian dibandingkan dengan pagu setelah pembulatan per baris |
| T-4 | Satu pengguna dapat terhubung ke lebih dari satu unit |
| T-5 | Default impor gagal sebagian: semua atau tidak sama sekali (sampai PRD K-9 diputuskan) |
| T-6 | Default prioritas pembanding standar: standar Pemda (sampai PRD K-3 diputuskan) |
| T-7 | Server dan penyimpanan berada di infrastruktur Pemda/RSUD (on-premise) |

### 15.2 Isu terbuka dari PRD yang memengaruhi desain

| PRD | Dampak teknis |
|---|---|
| K-3 | Logika `harga_standar` pada rincian |
| K-6 | Implementasi strategy ambang batas |
| K-7 | Model dan template dokumen pembayaran |
| K-9 | Mode aktivasi impor |
| K-13, K-16 | Parser impor dan format ekspor SIPD |
| K-14 | Isi tabel `aturan_jurnal` dan bagan akun |
| K-15 | Ruang lingkup modul persediaan/aset |
| K-17, K-18 | Ukuran server, RPO/RTO, retensi |
| K-25 | Nilai default `pergeseran.jenis_boleh_otomatis` |
| K-26 | Isi matriks kewenangan langkah Pimpinan BLUD |
| K-27 | Apakah aksi mobile tertentu (mis. SP3B) memerlukan TTE selain step-up |
| K-30 | Data awal `unit_kerja` dan `pemetaan_label_unit` |

---

## 16. Metodologi Pengembangan SDLC 2.0

### 16.1 Prinsip

1. **Spec-driven.** Tidak ada kode tanpa spec tertulis. Alurnya: PRD → spec fitur → plan implementasi → kode. Setiap tahap ditutup dengan artefak di repositori yang dibaca tahap berikutnya.
2. **AI membantu, manusia memutuskan.** Asisten AI boleh menyusun spec, plan, kode, test, dan review awal. Keputusan pada setiap gerbang (spec, plan, merge, rilis) diambil manusia yang berwenang.
3. **Kontrol ditegakkan di kode dan konfigurasi**, bukan kebiasaan: branch protection, pipeline wajib lulus, architecture test, constraint database, dan hook.
4. **Shift-left security.** Pemeriksaan keamanan berjalan di setiap pull request, bukan menjelang rilis.
5. **Pengetahuan tim terdokumentasi.** Aturan bisnis kritis ditulis dalam file konteks yang dibaca manusia dan asisten AI.
6. **Umpan balik berkelanjutan.** Temuan UAT, monitoring, dan insiden menjadi spec baru, bukan perbaikan diam-diam.
7. **Jejak audit pengembangan.** Rangkaian PRD → spec → plan → PR → test → rilis dapat ditelusuri. Ini melengkapi jejak audit data di dalam aplikasi, dan berguna saat pemeriksaan aplikasi oleh inspektorat/BPK.

### 16.2 Tahapan dan gerbang

| Tahap | Masukan | Aktivitas (AI membantu) | Artefak | Gerbang manusia |
|---|---|---|---|---|
| **1. Intent** | Kebutuhan, temuan UAT, insiden | Merumuskan masalah, tujuan, dan ID PRD terkait | `specs/<id>/intent.md` | Product owner (perencana/pengelola anggaran BLUD) menyetujui |
| **2. Spec** | Intent, PRD, TDD | Menyusun aturan bisnis, kriteria penerimaan Given/When/Then, dampak skema, risiko | `specs/<id>/spec.md` | Product owner + tech lead menyetujui; perubahan aturan uang wajib disetujui pemilik proses keuangan |
| **3. Plan** | Spec | Menyusun rencana teknis: migrasi, kelas, endpoint, test | `specs/<id>/plan.md` | Tech lead menyetujui sebelum kode ditulis |
| **4. Build** | Plan | Implementasi + test; agen bekerja di branch fitur | Kode, migrasi, test | — |
| **5. Verify** | Kode | CI otomatis; review AI; review manusia | Laporan CI, komentar review | Minimal satu reviewer manusia; dua reviewer untuk modul kritis (16.4) |
| **6. UAT** | Build di staging | Skenario UAT dijalankan pengguna kunci; test otomatis UAT sebagai pembanding | Berita acara UAT | Pemilik proses menandatangani |
| **7. Release** | Build lulus UAT | Catatan rilis disusun otomatis dari PR | `CHANGELOG.md`, tag rilis | Otorisasi rilis oleh tech lead + pemilik sistem; deploy produksi hanya manual-approve |
| **8. Operate & Learn** | Monitoring, keluhan | Analisis log/insiden dibantu AI | Laporan insiden, intent baru | Triase oleh tech lead dan product owner |

Perubahan kecil (typo, perbaikan tampilan tanpa logika) boleh langsung dari tahap 3 dengan label `minor`, tetapi tetap melalui tahap 5 dan 7.

### 16.3 Struktur repositori

```
/
├─ AGENTS.md / CLAUDE.md      # Konteks untuk asisten AI: aturan bisnis kritis, konvensi, larangan
├─ docs/
│  ├─ prd/                    # PRD berversi (sumber kebenaran kebutuhan)
│  ├─ tdd/                    # TDD berversi
│  ├─ adr/                    # Architecture Decision Records (0001-pakai-laravel-13.md, ...)
│  └─ runbook/                # Operasional: backup, pemulihan, insiden
├─ specs/
│  └─ PGS-012-pasangan-satu-ke-banyak/
│     ├─ intent.md
│     ├─ spec.md
│     └─ plan.md
├─ app/Modules/...
├─ database/migrations/...
├─ tests/
│  ├─ Arch/                   # architecture test antarmodul
│  ├─ Feature/Uat/            # satu file per UAT PRD (Uat31IndikatorPaguTest.php, ...)
│  └─ Unit/
└─ .github/ atau .gitlab/     # pipeline, template PR, CODEOWNERS
```

Setiap PR wajib merujuk ID spec dan ID FR/UAT. Template PR memuat daftar periksa: spec disetujui, plan disetujui, test UAT terkait ditambahkan/diperbarui, migrasi dapat di-*rollback*, dampak terhadap pagu/ledger dijelaskan.

### 16.4 Klasifikasi risiko modul

| Kelas | Modul | Persyaratan tambahan |
|---|---|---|
| **Kritis** | Anggaran (pagu, ledger), Revisi, Pergeseran, Workflow, Audit, Penatausahaan, Pajak, SP3B | 2 reviewer manusia (salah satunya pemegang `CODEOWNERS`), cakupan test 100% untuk Service, test konkurensi bila menyentuh pagu, perubahan constraint DB disetujui tech lead |
| **Standar** | Master, Kodefikasi, Standar harga, Piutang, Utang, Pelaporan | 1 reviewer manusia, cakupan ≥ 80% |
| **Rendah** | Tampilan, teks, dokumentasi | 1 reviewer manusia |

### 16.5 Pipeline CI/CD

```
PR dibuka
  ├─ Lint & format      : Pint, ESLint, Prettier
  ├─ Analisis statis    : Larastan level ≥ 8, vue-tsc
  ├─ Test               : Pest unit + feature (PostgreSQL nyata) + architecture + UAT
  ├─ Test konkurensi    : wajib jika label `kritis`
  ├─ Keamanan           : composer audit, npm audit, deteksi rahasia (gitleaks), SAST (Semgrep)
  ├─ Migrasi            : migrate + rollback pada DB kosong dan pada snapshot staging
  ├─ Review AI          : komentar otomatis (tidak dapat menyetujui)
  └─ Review manusia     : sesuai kelas risiko
Merge ke main (branch protection: semua check hijau + approval)
  └─ Deploy otomatis ke staging + smoke test (Playwright)
Tag rilis
  └─ Deploy produksi    : approval manual pemilik sistem, backup DB sebelum migrasi, rollback plan
```

### 16.6 Aturan penggunaan asisten AI

**Diizinkan**

- Menyusun draf intent, spec, plan, kode, test, dokumentasi, dan catatan rilis.
- Membaca skema DB, route, dan log non-produksi melalui Laravel Boost.
- Melakukan review awal PR.

**Dilarang / wajib manusia**

- Menyetujui spec, plan, PR, atau rilis.
- Mengakses basis data produksi atau data pasien.
- Mengubah constraint DB, fungsi `SECURITY DEFINER`, rumus pagu, atau aturan pasangan tanpa spec yang disetujui.
- Menonaktifkan, melewati, atau melemahkan test dan check CI.
- Memasukkan rahasia, kredensial, atau data produksi ke dalam prompt.

**Penegakan**

- `AGENTS.md`/`CLAUDE.md` memuat invarian yang tidak boleh dilanggar, antara lain:
  - sisa pagu = pagu berlaku − realisasi − komitmen − tercadang, tidak boleh negatif;
  - semua perubahan pagu hanya melalui `PaguLedger`;
  - pasangan pergeseran wajib seimbang dan diterapkan atomik;
  - pasangan dengan harga di atas standar tidak pernah disetujui otomatis;
  - tidak ada hapus fisik untuk data keuangan.
- Hook pada asisten AI dan pre-commit menolak perubahan pada file migrasi constraint kritis atau file test UAT tanpa label spec.
- Kredensial yang dipakai lingkungan pengembangan dan asisten AI terpisah dari produksi.
- Kode yang dihasilkan AI diperlakukan sama dengan kode manusia: wajib test, wajib review, tanggung jawab ada pada pengembang yang membuat PR.

### 16.7 Definisi siap (Definition of Ready) dan selesai (Definition of Done) per fitur

**Siap dikerjakan** bila: intent dan spec disetujui, kriteria penerimaan berbentuk Given/When/Then, dampak skema diketahui, dan keputusan terbuka PRD yang relevan sudah ditetapkan atau diberi default tertulis.

**Selesai** bila: memenuhi DoD PRD bagian 21, ditambah seluruh check CI hijau, test UAT terkait lulus otomatis, spec/plan/ADR diperbarui sesuai implementasi akhir, dan catatan rilis tersedia.

### 16.8 Metrik proses

| Metrik | Kegunaan |
|---|---|
| Lead time intent → produksi | Kecepatan pengiriman |
| Tingkat kegagalan perubahan (rilis yang memerlukan perbaikan darurat) | Kualitas |
| Waktu pemulihan insiden | Ketahanan |
| Jumlah cacat yang ditemukan di UAT vs produksi | Efektivitas shift-left |
| Persentase PR dengan spec tertaut | Kepatuhan proses |
| Selisih rekonsiliasi pagu harian (target 0) | Integritas data |

### 16.9 Peran tim

| Peran | Tanggung jawab dalam SDLC 2.0 |
|---|---|
| Product owner (dari BLUD) | Menyetujui intent dan spec, memprioritaskan backlog |
| Pemilik proses keuangan | Menyetujui spec yang menyentuh aturan uang, menandatangani UAT |
| Tech lead | Menyetujui plan, menjaga `AGENTS.md`/ADR, reviewer modul kritis, otorisasi rilis |
| Pengembang | Bekerja bersama asisten AI, bertanggung jawab penuh atas PR |
| QA / penguji | Menjaga test UAT otomatis, menjalankan UAT bersama pengguna |
| Admin TI / pemilik sistem | Infrastruktur, backup, approval deploy produksi |

---

**Akhir dokumen — TDD Aplikasi Keuangan BLUD RSUD, versi 1.5 (draf).**
