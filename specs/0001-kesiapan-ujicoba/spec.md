# Spec — 0001 Kesiapan ujicoba

Status: **DRAF — menunggu persetujuan** (dibuat asisten AI; tidak boleh disetujui oleh AI).

## Aturan bisnis
1. Login memakai email **atau NIP**; akun `aktif = false` ditolak. Tidak ada pendaftaran mandiri.
2. Kotak masuk persetujuan menampilkan hanya pengajuan yang boleh diputuskan pengguna (permission langkah aktif,
   cakupan unit, pemisahan tugas). Sumber: workflow BERJALAN, persetujuan unit atas pasangan, usulan standar harga.
3. Penolakan/pengembalian wajib alasan ≥ 10 karakter.
4. Pengguna yang memiliki PIN wajib memasukkan PIN untuk setiap keputusan dari kotak masuk (metode `PIN`, kanal `MOBILE`/`WEB` tercatat).
5. RBA melalui 3 langkah: Verifikasi → Persetujuan internal → **Penetapan Pimpinan BLUD**; langkah terakhir membentuk pagu lewat ledger dalam transaksi yang sama.
6. Simpan revisi tanpa perubahan ditolak (`TIDAK_ADA_PERUBAHAN`); baris yang nilainya identik diabaikan.

## Kriteria penerimaan
- `tests/Feature/KotakMasukTest.php` (6 skenario) dan `tests/Feature/RevisiPergeseranTest.php` lulus.
- Alur ujicoba di README dapat diselesaikan di browser (diverifikasi otomatis dengan Chromium).

## Dampak skema & data
Tidak ada perubahan `database/sql/`. Format nomor `PENETAPAN_RBA` ditambahkan di PenomoranService.
`DemoSeeder` hanya untuk lingkungan lokal — **jangan dijalankan di produksi**.

## Dampak terhadap pagu / ledger
Tidak ada rumus baru. Perbaikan: (a) mengunci rincian kini menyinkronkan `rincian_row_version` agar pengesahan
pasangan tidak gagal palsu "diubah orang lain"; (b) validasi ulang pasangan memakai jam aplikasi, bukan jam sistem.

## Risiko & mitigasi
- PIN bukan pengganti WebAuthn (TDD 6.5) — WebAuthn/passkey dijadwalkan tahap berikutnya.
- Kotak masuk menghitung kewenangan per baris; perlu indeks/kueri khusus bila pengajuan > ribuan.

## Persetujuan
- [ ] Product owner
- [ ] Tech lead
- [ ] Pemilik proses keuangan
