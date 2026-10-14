# ADR 0002 — Ledger pagu append-only

- Status: Diterima (14 Okt 2026)
- Keputusan: setiap perubahan pagu dicatat di `pagu_mutasi` (append-only) melalui `fn_pagu_catat`; agregat `pagu_jenis` diperbarui dalam transaksi yang sama dan dijaga trigger `fn_pagu_jaga_agregat`. Rekonsiliasi harian membandingkan agregat dengan ledger dan total rincian.
- Alasan: jejak audit penuh, mudah ditelusuri saat pemeriksaan, kebal terhadap update langsung yang tidak sengaja.
