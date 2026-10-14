# Panduan untuk pengembang dan asisten AI

Dokumen ini dibaca manusia **dan** asisten AI (TDD bagian 16.6). `CLAUDE.md` merujuk ke berkas ini.

## Sumber kebenaran

1. `docs/prd/` — kebutuhan (PRD v1.9)
2. `docs/tdd/` — desain teknis (TDD v1.5)
3. `specs/<id>/` — intent, spec, plan per perubahan

Tidak ada kode tanpa spec yang disetujui. Ikuti alur Intent → Spec → Plan → Build → Verify → UAT → Release.

## Invarian bisnis (WAJIB)

1. `sisa pagu = pagu berlaku − realisasi − komitmen − tercadang` dan **tidak boleh negatif**.
2. Agregat pagu **hanya** berubah melalui `PaguLedger::catat()` → `fn_pagu_catat`. Jangan menulis kolom `pagu_jenis.pagu_berlaku/realisasi/komitmen/tercadang` secara langsung.
3. Pengendalian pagu di tingkat **unit × jenis belanja (rekening Pemda tingkat 3) × sumber dana**.
4. Setiap rincian wajib merujuk **item standar harga** yang aktif; harga di atas standar tidak pernah diizinkan saat penyusunan RBA, dan setelah definitif hanya dengan bukti + persetujuan perencana.
5. Revisi rincian seimbang langsung berlaku **hanya** untuk jenis pada konfigurasi `revisi.jenis_berlaku_langsung` (default Belanja Barang dan Jasa 5.1.02).
6. Pasangan pergeseran wajib **seimbang**, sejenis, sesumber dana, dari unit berbeda, dan diterapkan **atomik** bersama paket revisinya.
7. Pasangan yang memuat **harga di atas standar tidak pernah disetujui otomatis**.
8. Tidak ada hapus fisik data keuangan; pembatalan memakai status atau reversal.
9. Operasi yang menyentuh beberapa baris pagu mengunci baris **berurutan id** (`Kunci::baris`).
10. Uang memakai `App\Modules\Shared\Domain\Uang` (sen) — **tidak pernah float**.

## Larangan untuk asisten AI

- Menyetujui spec, plan, PR, atau rilis.
- Mengakses basis data produksi atau data pasien; memasukkan rahasia/data produksi ke prompt.
- Mengubah berkas di `database/sql/` yang sudah dirilis (buat berkas + migrasi baru), fungsi `SECURITY DEFINER`, rumus pagu, atau aturan pasangan tanpa spec yang disetujui.
- Menonaktifkan, melewati, atau melemahkan test dan pemeriksaan CI.

## Kebiasaan kode

- Logika murni di `app/Modules/*/Domain` (tanpa Laravel) dan diuji di `tests/Unit`.
- Kesalahan aturan bisnis dilempar sebagai `KesalahanDomain` (kode, pesan, tindakan, detail).
- Setiap UAT di PRD memiliki test dengan ID UAT pada namanya.
- Jalankan sebelum PR: `composer test:domain`, `composer test:sql`, `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`.
