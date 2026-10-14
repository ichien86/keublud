# Spec — 0002 Impor RBA format lama (TDD 8.4)

Status: **DRAF — menunggu persetujuan** (dibuat asisten AI).

## Aturan bisnis
1. Pembacaan memakai baris judul (KODE REKENING, KOMPONEN, Jml/Satuan/Harga Satuan/Jumlah), bukan huruf kolom tetap.
2. Kode rinci BLUD = jenis Pemda (3 segmen) + 3 segmen BLUD di file (01. 01. 0037 → 5.1.02.01.01.0037).
3. Sub-komponen bernilai menggantikan komponennya **hanya bila jumlahnya tepat sama**; selain itu komponen dipakai utuh dengan peringatan.
4. Nilai yang tertulis di baris judul rincian objek dibebankan ke komponen pertama di bawahnya bila tidak ada komponen bernilai (dengan peringatan).
5. Jml bernilai 0/"-" tetapi Jumlah terisi (rumus rusak) dianggap 0 dengan peringatan.
6. Setiap baris wajib dirinci ke item standar harga aktif tahun tujuan, harga ≤ standar (dijaga trigger `fn_staging_item_harga`).
   Baris gelondongan dapat diajukan sebagai usulan item BLUD bersatuan paket; pengesahan tetap oleh perencana lain (pemisahan tugas).
7. RBA terbentuk (DRAF, versi berikutnya) hanya bila semua baris SIAP/DIABAIKAN (alasan ≥ 10 karakter) dan Σ rincian + Σ diabaikan = total file per rekening Pemda.
8. Tahun yang sudah memiliki RBA definitif ditolak (perubahan RBA memakai alur tersendiri).
9. Melengkapi kodefikasi dari file butuh `kodefikasi.kelola`; impor butuh `rba.impor` (perencana, administrator).

## Kriteria penerimaan
- `tests/Unit/LampiranRbaParserTest.php`, `tests/Feature/ImporRbaTest.php` lulus.
- 27 sheet file acuan: rekonsiliasi cocok di semua rekening (kecuali sheet yang terpotong di salinan uji).

## Dampak skema
Berkas baru `database/sql/090_impor_rba.sql` + migrasi `2026_10_14_000200_impor_rba.php` (impor_rba, impor_rba_rekening, staging_rba, staging_rba_item).
Tidak mengubah berkas SQL yang sudah ada. Permission baru `rba.impor`.

## Dampak pagu / ledger
Tidak ada: RBA hasil impor berstatus DRAF; pagu baru terbentuk saat penetapan Pimpinan BLUD lewat alur yang sudah ada.

## Risiko
- Uraian rekening induk yang tidak tertulis di file diberi tanda "(lengkapi uraian)".
- Item paket memenuhi aturan "wajib standar harga" secara formal; disarankan dirinci ulang ke item nyata saat penyusunan berikutnya.

## Persetujuan
- [ ] Product owner
- [ ] Tech lead
- [ ] Pemilik proses keuangan
