# Product Requirements Document (PRD)
## Aplikasi Keuangan BLUD RSUD Berbasis Web

**Versi:** 1.9 (Draf kebutuhan bisnis, revisi hasil telaah)  
**Tanggal:** 13 Oktober 2026  
**Status:** Draf untuk validasi pemangku kepentingan  
**Jenis implementasi:** Pengembangan baru (*from scratch*)  
**Target pengguna:** Kurang dari 50 pengguna pada tahap awal  
**Lingkup organisasi:** Satu RSUD berstatus BLUD dalam satu instalasi  
**Pemerintah daerah:** Belum ditentukan

---

## Riwayat Perubahan

| Versi | Tanggal | Ringkasan perubahan |
|---|---|---|
| 1.0 | 13 Okt 2026 | Draf awal kebutuhan bisnis |
| 1.1 | 13 Okt 2026 | Hasil telaah: (1) perubahan anggaran dipisah menjadi tiga mekanisme — revisi rincian, pergeseran antarunit, dan perubahan RBA; (2) pengendalian pagu ditetapkan di tingkat jenis belanja; (3) ditambahkan dimensi sumber dana, pemetaan rekening Pemda ke rekening BLUD, ambang batas BLUD, pencadangan (blokir) nilai pergeseran; (4) semua item wajib memiliki standar harga, dengan fitur pengecekan kecocokan usulan standar harga; (5) standar harga tidak berubah di tengah tahun — harga di atas standar diajukan melalui revisi rincian dengan bukti dan persetujuan perencana; (6) ditambahkan penatausahaan bendahara (dokumen pembayaran, BKU, SPJ), pajak, utang jangka pendek, piutang operasional, impor klaim/tagihan, pengesahan SP3B/SP2B, ekspor SIPD, delegasi/Plt, notifikasi, penomoran, dan TTE; (7) FR diberi prioritas dan fase, UAT diperluas, ditambahkan matriks keterlacakan |
| 1.2 | 13 Okt 2026 | (1) Struktur kodefikasi rekening dan nomenklatur mengikuti pola Permendagri 90/2019, dengan fitur pemetaan; (2) halaman edit rincian memiliki indikator status pagu jenis belanja, pengecekan saat menyimpan, opsi mengirim selisih sebagai permintaan pergeseran antarunit, opsi melanjutkan edit, dan peringatan saat meninggalkan halaman; (3) utang belum dibayar ditetapkan sebagai komitmen yang mengurangi sisa pagu |
| 1.3 | 13 Okt 2026 | Pergeseran antarunit wajib memiliki pasangan: kenaikan pagu suatu unit harus diimbangi penurunan di unit lain dalam jenis belanja dan sumber dana yang sama, dan sebaliknya; jika pasangan tidak terpenuhi dalam batas maksimal 5 hari, permintaan dan paket revisinya dinyatakan ditolak |
| 1.4 | 13 Okt 2026 | Batas waktu dihitung dalam hari kerja (dengan kalender hari libur); setelah pasangan lengkap, perencana diberi notifikasi dan jika tidak merespons dalam 5 hari kerja, pengesahan dinyatakan disetujui otomatis dengan validasi ulang dan jejak audit khusus |
| 1.5 | 13 Okt 2026 | Pasangan yang memuat harga di atas standar dikecualikan dari persetujuan otomatis dan wajib diputuskan perencana; persetujuan otomatis dinyatakan dapat diterima sebagai pengesahan yang sah |
| 1.6 | 14 Okt 2026 | (1) Item standar harga internal baru dapat ditambahkan di tengah tahun sesuai kebutuhan, termasuk saat akan belanja; harga item yang sudah disahkan tetap tidak berubah; (2) impor Excel untuk usulan standar harga internal BLUD, dengan pengecekan kecocokan per baris dan keputusan perencana secara massal; (3) revisi rincian seimbang yang berlaku langsung dikhususkan untuk Belanja Barang dan Jasa — jenis belanja lain wajib disetujui perencana; (4) tampilan persetujuan sederhana dan *mobile-first* untuk persetujuan yang sampai ke Pimpinan BLUD |
| 1.7 | 14 Okt 2026 | Penyesuaian terhadap contoh dokumen RBA BLUD (format Lampiran Rincian Anggaran Belanja DPA/DPPA BLUD): (1) standar harga tetap wajib untuk seluruh rincian, termasuk belanja yang selama ini dianggarkan gelondongan; (2) unit kerja/penanggung jawab menjadi master dan wajib diisi per rincian; (3) pemetaan rekening rinci BLUD ke rekening agregat Pemda (banyak-ke-satu); (4) dokumen anggaran formal bernomor (Penetapan, Pergeseran ke-n, Perubahan) dengan cetakan kolom Berkurang/Bertambah; (5) anggaran kas triwulan; (6) impor RBA dari format Excel lama untuk migrasi |
| 1.8 | 14 Okt 2026 | Anggaran kas triwulan diganti dengan **pengendalian ketersediaan dana berbasis realisasi pendapatan**; dokumen Pergeseran ke-n dapat dibentuk sewaktu-waktu, sedangkan Perubahan hanya dapat diajukan dalam jadwal yang ditetapkan Pemda |
| 1.9 | 14 Okt 2026 | Pengaturan cadangan kas minimum untuk belanja wajib (7.18a); piutang yang belum dibayar ditetapkan tidak dihitung sebagai dana tersedia; lampiran rencana penarikan dana (tidak mengikat) pada dokumen DPA/DPPA BLUD (7.17a) |

---

## 1. Ringkasan Eksekutif

Aplikasi ini mendukung pengelolaan anggaran dan keuangan RSUD berstatus Badan Layanan Umum Daerah (BLUD), mulai dari penyusunan Rencana Bisnis dan Anggaran (RBA), penetapan RBA definitif, perubahan anggaran, penatausahaan pendapatan dan belanja, pengesahan SP3B, akuntansi, hingga pelaporan dan audit.

Prinsip pengendalian utama:

- Anggaran disusun per unit kerja dan per sumber dana, dengan **pengendalian pagu di tingkat jenis belanja**.
- **Setiap item belanja wajib terdaftar dalam daftar standar harga** (standar harga Pemda yang diimpor dari Excel atau standar harga internal BLUD). Usulan standar harga baru dicek kecocokannya dengan daftar yang sudah ada sebelum disahkan perencana.
- Saat penyusunan RBA, harga usulan tidak boleh melebihi standar harga.
- **Harga** standar yang sudah disahkan tidak berubah di tengah tahun anggaran, tetapi **item baru dapat ditambahkan kapan saja** sesuai kebutuhan internal, satu per satu atau melalui impor Excel.
- Belanja dikendalikan dua lapis: **sisa pagu** (batas anggaran) dan **dana tersedia** dari realisasi pendapatan yang benar-benar diterima (batas kas).
- Setelah RBA definitif, perubahan anggaran dilakukan melalui tiga mekanisme:
  1. **Revisi rincian** — dalam unit, jenis belanja, dan sumber dana yang sama; pagu jenis belanja tetap. Untuk **Belanja Barang dan Jasa** revisi berlaku saat disimpan; untuk jenis belanja lain wajib disetujui perencana. Harga di atas standar hanya dapat diajukan di sini, dengan bukti dan persetujuan perencana. Jika hasil edit mengubah pagu jenis belanja unit, selisihnya dikirim sebagai permintaan pergeseran antarunit yang memerlukan persetujuan.
  2. **Pergeseran antarunit** — jenis belanja dan sumber dana tetap sama, dengan pengesahan khusus antarunit dan pencadangan nilai di unit asal.
  3. **Perubahan RBA** — lintas jenis belanja, perubahan total, dan penggunaan ambang batas BLUD.
- Transaksi belanja wajib merujuk rincian anggaran yang berlaku. Jika harga atau volume riil berbeda, rincian direvisi terlebih dahulu sebelum transaksi diajukan.

Aturan teknis dan alur persetujuan harus dikonfirmasi terhadap regulasi Pemda, kebijakan RSUD, dan kebijakan akuntansi yang berlaku sebelum implementasi produksi.

## 2. Latar Belakang dan Permasalahan

Pengelolaan anggaran BLUD membutuhkan keseimbangan antara fleksibilitas operasional rumah sakit dan kepatuhan pada anggaran yang telah disahkan. Pengelolaan menggunakan spreadsheet atau aplikasi terpisah menimbulkan risiko:

- Standar harga tidak seragam, menggunakan versi yang salah, atau item dibelanjakan tanpa standar.
- Item standar harga ganda dengan nama berbeda.
- Kesulitan melacak perubahan dari RBA awal ke RBA definitif, revisi rincian, pergeseran, dan perubahan RBA.
- Pergeseran antarunit yang tidak tercatat sebagai satu transaksi anggaran yang utuh, atau dananya terpakai sebelum pergeseran disahkan.
- Transaksi belanja tanpa referensi anggaran yang sah atau melampaui sisa pagu.
- Pendapatan dan belanja BLUD terlambat disahkan melalui SP3B sehingga tidak tercermin di laporan Pemda.
- Pajak yang dipotong atau dipungut tidak tercatat dan tidak terekonsiliasi dengan setoran.
- Utang pemasok dan piutang layanan (pasien, BPJS, kerja sama) tidak terpantau umurnya.
- Sulit menelusuri siapa yang mengajukan, memverifikasi, dan mengesahkan perubahan.
- Rekonsiliasi memakan waktu antara anggaran, penatausahaan, buku besar, dan laporan.

Aplikasi harus menyediakan satu sumber data yang dapat ditelusuri, kontrol yang konsisten, serta riwayat perubahan yang dapat diaudit.

## 3. Tujuan Produk

1. Menyediakan pengelolaan RBA per unit kerja dan sumber dana secara terstruktur.
2. Mengelola standar harga Pemda melalui impor Excel dan standar harga internal BLUD, tanpa duplikasi item.
3. Mengendalikan harga, jenis belanja, unit kerja, sumber dana, dan pagu pada setiap tahap siklus anggaran.
4. Menyediakan revisi rincian yang cepat bagi unit, serta pergeseran antarunit dan perubahan RBA dengan persetujuan sesuai kewenangan.
5. Memastikan penatausahaan belanja bersumber dari rincian anggaran yang sah.
6. Mendukung tugas bendahara: dokumen pembayaran, BKU, buku pembantu, pajak, dan SPJ.
7. Mengelola utang jangka pendek dan piutang operasional.
8. Menghasilkan SP3B dan mencatat SP2B agar pendapatan dan belanja BLUD terkonsolidasi ke Pemda.
9. Mengintegrasikan transaksi operasional dengan pencatatan akuntansi dan pelaporan.
10. Menyediakan jejak audit atas perubahan data, lampiran, dan persetujuan.
11. Memudahkan impor/ekspor data, termasuk ekspor sesuai rekening Pemda untuk SIPD.

## 4. Sasaran dan Indikator Keberhasilan

| Sasaran | Indikator keberhasilan awal |
|---|---|
| Kontrol anggaran | Setiap transaksi belanja memiliki referensi rincian anggaran yang sah dan tidak melampaui sisa pagu jenis belanja |
| Kelengkapan standar harga | 100% rincian anggaran merujuk item dalam daftar standar harga |
| Kebersihan standar harga | Setiap usulan standar baru melewati pengecekan kecocokan sebelum disahkan |
| Validasi harga | Sistem menolak harga di atas standar pada penyusunan RBA, dan pada revisi rincian tanpa bukti dan persetujuan perencana |
| Kontrol pergeseran | Nilai pergeseran antarunit tercadang sejak pengajuan dan diterapkan atomik setelah disahkan |
| Auditabilitas | Perubahan dan persetujuan memiliki pengguna, waktu, alasan, dan riwayat |
| Kepatuhan pengesahan | SP3B tiap periode dapat dihasilkan dari data aplikasi tanpa kompilasi manual |
| Pajak | Setiap potongan/pungutan pajak tercatat dan dapat direkonsiliasi dengan bukti setor |
| Rekonsiliasi | Saldo laporan dapat ditelusuri ke transaksi dan jurnal sumber |
| Adopsi | Pengguna dapat menyelesaikan tugas sesuai peran setelah pelatihan dan UAT |

Target angka operasional (waktu proses persetujuan, tingkat kesalahan impor, dan sebagainya) ditetapkan setelah baseline proses saat ini diketahui.

## 5. Ruang Lingkup

### 5.1 Termasuk dalam lingkup

- Pengaturan organisasi, unit kerja, pengguna, peran, matriks kewenangan, dan delegasi/Plt.
- Master rekening BLUD, rekening Pemda, dan **pemetaan rekening Pemda ke rekening BLUD**.
- Master program/kegiatan, sumber dana, barang/jasa, satuan, dan standar harga.
- Impor standar harga Pemda dari Excel dan pengelolaan standar harga internal BLUD, termasuk pengecekan kecocokan usulan.
- Penyusunan RBA per unit dan sumber dana, verifikasi, persetujuan, dan penetapan definitif.
- Revisi rincian, pergeseran antarunit, perubahan RBA, dan penggunaan ambang batas.
- Penatausahaan pendapatan dan belanja, dokumen pembayaran, BKU, buku pembantu, dan SPJ.
- Perpajakan atas transaksi (pemotongan, pemungutan, penyetoran, pelaporan).
- Kas dan bank.
- Utang jangka pendek dan piutang operasional, termasuk impor klaim/tagihan dari file.
- Pengesahan pendapatan dan belanja BLUD (SP3B/SP2B).
- Persediaan dan aset tetap (lihat catatan 5.3).
- Jurnal, buku besar, penutupan periode, rekonsiliasi, dan laporan.
- Ekspor data berbasis rekening Pemda untuk keperluan SIPD.
- Notifikasi, penomoran dokumen, cetakan dokumen resmi, dan dukungan dokumen bertanda tangan elektronik.
- Impor/ekspor data serta jejak audit.
- Dokumen anggaran formal bernomor (Penetapan, Pergeseran ke-n, Perubahan) dan cetakan Lampiran Rincian Anggaran Belanja.
- Pengendalian ketersediaan dana berbasis realisasi pendapatan.
- Impor RBA dari format Excel yang berlaku saat ini untuk migrasi.

### 5.2 Di luar lingkup tahap awal

- Integrasi API langsung dengan SIPD, sistem Pemda lain, perbankan, BPJS, pengadaan, SIMRS, layanan TTE, atau sistem perpajakan. Pada tahap awal, pertukaran data dilakukan melalui impor/ekspor file dengan template terdokumentasi.
- Aplikasi seluler native.
- Pengelolaan multi-BLUD dalam satu instalasi.
- Otomatisasi keputusan persetujuan tanpa pejabat berwenang, **kecuali** persetujuan otomatis berbatas waktu atas pasangan pergeseran antarunit yang ditetapkan pada 7.8a.
- Penggantian sistem klinis seperti rekam medis elektronik atau sistem pelayanan pasien.

### 5.3 Produk minimum (MVP) dan catatan cakupan

**MVP = Fase 1 + Fase 2** (lihat bagian 20): siklus anggaran lengkap dan penatausahaan, termasuk pajak, utang/piutang operasional, dan SP3B. Akuntansi penuh, persediaan, dan aset tetap masuk Fase 3.

Sebelum Fase 3 dimulai, perlu dipastikan apakah persediaan dan aset tetap sudah dikelola oleh aplikasi lain (misalnya aplikasi barang milik daerah). Jika sudah, modul tersebut cukup berupa impor saldo/mutasi untuk keperluan jurnal dan laporan, bukan pengelolaan penuh.

## 6. Pemangku Kepentingan dan Peran Pengguna

| Peran | Tanggung jawab utama |
|---|---|
| Administrator aplikasi | Pengguna, hak akses, konfigurasi, periode, master data, pemetaan rekening |
| Perencana / pengelola anggaran BLUD | Mengesahkan standar harga internal, menyetujui revisi rincian dengan harga di atas standar, konsolidasi RBA, pemantauan anggaran |
| Pengusul anggaran unit | Menyusun anggaran unit, melakukan revisi rincian, mengusulkan standar harga baru, mengajukan pergeseran |
| Verifikator anggaran | Memeriksa kelengkapan dan kepatuhan pengajuan |
| Pejabat pengesah | Mengesahkan RBA, pergeseran antarunit, perubahan RBA, dan penggunaan ambang batas sesuai matriks kewenangan |
| Pejabat teknis kegiatan | Mengajukan dan memeriksa kebutuhan belanja unit |
| Petugas penatausahaan | Mencatat transaksi berdasarkan anggaran yang sah |
| Bendahara penerimaan | Penerimaan, setoran, BKU penerimaan |
| Bendahara pengeluaran | Pembayaran, uang muka/persediaan, pajak, BKU pengeluaran, SPJ |
| Pejabat keuangan BLUD | Menyetujui dokumen pembayaran, SP3B, dan laporan keuangan |
| Petugas akuntansi | Memeriksa jurnal, rekonsiliasi, dan menutup periode |
| Pengelola persediaan/aset | Kartu stok, mutasi, register aset |
| Pimpinan BLUD/manajemen | Memantau dashboard dan laporan, pengesahan tingkat pimpinan |
| Auditor/pemeriksa | Mengakses data dan riwayat sesuai kewenangan baca |

Pemisahan tugas harus dapat dikonfigurasi. Pengguna tidak boleh menyetujui pengajuannya sendiri jika kebijakan pemisahan tugas melarangnya.

## 7. Prinsip dan Aturan Bisnis Inti

### 7.1 Tiga mekanisme perubahan anggaran setelah definitif

| Aspek | Revisi rincian | Pergeseran antarunit | Perubahan RBA |
|---|---|---|---|
| Tujuan | Menyesuaikan item, volume, atau harga satuan | Memindahkan nilai pagu antarunit | Mengubah total, lintas jenis belanja, menggunakan ambang batas |
| Unit | Sama | Berbeda | Bebas sesuai ketentuan |
| Jenis belanja | Sama | Sama | Boleh berbeda |
| Sumber dana | Sama | Sama | Sesuai ketentuan |
| Pagu jenis belanja unit | Tetap | Unit asal turun, unit penerima naik; total tetap | Dapat berubah |
| Harga ≤ standar | Berlaku saat disimpan jika pagu jenis belanja tetap (lihat 7.7) | Pengesahan antarunit | Pengesahan sesuai ketentuan |
| Harga > standar | Wajib bukti + persetujuan perencana | Wajib bukti + persetujuan perencana, lalu pengesahan antarunit | Wajib bukti + persetujuan perencana |
| Pencadangan nilai | Tidak diperlukan | Wajib sejak pengajuan | Sesuai ketentuan |
| Berlaku | Saat disimpan / setelah persetujuan perencana | Setelah pengesahan antarunit | Setelah pengesahan |

Sistem menentukan mekanisme secara otomatis dari unit, jenis belanja, dan sumber dana yang dipilih, sehingga pengguna tidak dapat memilih mekanisme yang lebih longgar.

### 7.2 Rekening dan pemetaan

1. Sistem menyimpan bagan rekening Pemda dan bagan rekening khusus BLUD dengan **struktur kodefikasi mengikuti pola Permendagri 90/2019** beserta pemutakhirannya:
   - **Rekening:** Akun → Kelompok → Jenis → Objek → Rincian Objek → Sub Rincian Objek.
   - **Nomenklatur perencanaan:** Urusan → Bidang Urusan → Program → Kegiatan → Sub Kegiatan.
   
   Struktur dapat diimpor dari Excel, berversi per tahun, dan rekening BLUD mengikuti tingkat yang sama sehingga dapat dipetakan tingkat demi tingkat.
2. Setiap rekening BLUD tingkat terendah yang dapat dianggarkan wajib dipetakan ke satu rekening Pemda. Pemetaan bersifat **banyak-ke-satu**: rekening rinci BLUD yang mengikuti kodefikasi Permendagri 90 (misalnya 5.1.02.01.01.0037 Belanja Obat-Obatan-Obat) dipetakan ke rekening agregat BLUD di Pemda pada tingkat yang sama (misalnya 5.1.02.99.99.9999 Belanja Barang dan Jasa BLUD). Contoh rekening agregat:

   | Rekening agregat Pemda | Uraian |
   |---|---|
   | 5.1.01.99.99.9999 | Belanja Pegawai BLUD |
   | 5.1.02.99.99.9999 | Belanja Barang dan Jasa BLUD |
   | 5.2.02.99.99.9999 | Belanja Modal Peralatan dan Mesin BLUD |
   | 5.2.03.99.99.9999 | Belanja Modal Gedung dan Bangunan BLUD |

   Daftar final mengikuti bagan rekening Pemda yang berlaku.
3. Pemetaan memiliki versi dan tanggal berlaku; perubahan pemetaan tidak mengubah data historis secara retroaktif.
4. Rekening BLUD tanpa pemetaan tidak dapat digunakan dalam penyusunan RBA.
5. **Jenis belanja** adalah tingkat "Jenis" dalam kodefikasi rekening Pemda (misalnya Belanja Barang dan Jasa). Kesamaan jenis belanja dievaluasi pada rekening Pemda hasil pemetaan.
6. Pemetaan digunakan untuk konsolidasi laporan ke Pemda, SP3B, dan ekspor SIPD.
7. **Fitur pemetaan:** layar pemetaan menampilkan rekening BLUD yang belum dipetakan, pencarian rekening Pemda, pemetaan massal dari Excel, validasi bahwa rekening BLUD hanya dipetakan ke rekening Pemda pada tingkat yang setara, dan laporan perbedaan antarversi pemetaan.

### 7.3 Sumber dana

1. Setiap rincian anggaran, transaksi, dan saldo pagu memiliki sumber dana (misalnya jasa layanan, APBD, hibah, kerja sama, lain-lain pendapatan BLUD yang sah).
2. Revisi rincian dan pergeseran antarunit tidak boleh melintasi sumber dana.
3. Belanja bersumber APBD mengikuti ketentuan penatausahaan APBD; perbedaan alur dengan belanja bersumber pendapatan BLUD harus dikonfigurasi per sumber dana.

### 7.4 Standar harga

1. **Tidak ada item tanpa standar harga.** Setiap rincian anggaran wajib merujuk satu item dalam daftar standar harga yang aktif.
2. Standar harga Pemda diimpor dari Excel dengan template dan validasi terdokumentasi, menyimpan periode berlaku, sumber, tanggal impor, dan versi.
3. Standar harga internal BLUD ditambahkan melalui **usulan standar harga**, yang dapat diajukan oleh pengusul unit atau perencana dan disahkan oleh perencana. Usulan dapat diisi **satu per satu melalui formulir** atau **massal melalui impor Excel** (7.4a).
4. **Pengecekan kecocokan:** sebelum perencana mengesahkan usulan, sistem menampilkan daftar standar harga yang sudah ada (Pemda dan internal) yang mirip dengan usulan, beserta skor kecocokan berdasarkan kemiripan uraian, kode barang, satuan, spesifikasi, dan rentang harga. Perencana memilih salah satu:
   - **Tolak, gunakan item yang ada** — pengusul diarahkan ke item tersebut;
   - **Sahkan sebagai item baru** — wajib mengisi alasan mengapa item yang mirip tidak dapat digunakan;
   - **Kembalikan untuk perbaikan.**
5. Pengecekan kecocokan juga dijalankan saat impor standar harga Pemda untuk menandai calon duplikat dengan standar internal.
6. **Harga item yang sudah disahkan tidak berubah di tengah tahun anggaran.** Kebutuhan harga di atas standar untuk item yang sudah ada ditangani melalui revisi rincian dengan bukti dan persetujuan perencana (7.7).
7. **Penambahan item di tengah tahun diperbolehkan.** Setelah RBA definitif, item standar harga internal baru tetap dapat diusulkan dan disahkan sesuai kebutuhan, misalnya ketika unit akan belanja barang/jasa yang belum ada di RBA. Item tambahan dicatat dengan tanggal berlaku sejak disahkan dan penanda *tambahan tengah tahun*, serta langsung dapat dipakai dalam revisi rincian.
8. **Alur belanja item baru:** usulan standar harga → pengecekan kecocokan → disahkan perencana → revisi rincian menambah rincian dengan item tersebut (7.7) → transaksi belanja. Dari formulir transaksi dan halaman edit rincian tersedia tautan langsung ke usulan standar harga; setelah disahkan, pengusul menerima notifikasi dan dapat melanjutkan.
9. Sistem tidak boleh menimpa standar harga lama tanpa menyimpan riwayat versi.
10. Setiap rincian menyimpan referensi item standar, harga standar, dan harga rincian.
11. Jika item yang sama tercantum di standar Pemda dan standar internal, aturan prioritas pembanding ditetapkan pada keputusan terbuka K-3.

### 7.4a Impor Excel usulan standar harga internal

1. Pengusul unit atau perencana mengunduh **template Excel** standar harga internal (kolom: kode barang, uraian, spesifikasi, satuan, harga, kategori, rekening terkait, referensi bukti harga).
2. Setelah diunggah, sistem memvalidasi setiap baris (format, kolom wajib, satuan, harga > 0, duplikasi di dalam file).
3. Sistem menjalankan **pengecekan kecocokan per baris** terhadap standar harga Pemda dan internal yang sudah ada, lalu menampilkan pratinjau dengan kategori: **tanpa kecocokan**, **kecocokan sedang**, **kecocokan tinggi**, dan **gagal validasi**.
4. Pengusul dapat menghapus baris, mengganti baris dengan item yang sudah ada, atau memperbaiki file lalu mengunggah ulang. Bukti harga dapat dilampirkan per baris atau sebagai satu berkas untuk seluruh impor.
5. Baris valid diajukan sebagai **satu batch usulan**.
6. Perencana memutuskan batch tersebut:
   - baris **tanpa kecocokan / kecocokan sedang** dapat **disahkan sekaligus** (pilih semua → sahkan);
   - baris **kecocokan tinggi** wajib diputuskan **per baris** (gunakan item yang ada, sahkan sebagai baru dengan alasan, atau kembalikan);
   - keputusan per baris dapat berbeda dalam satu batch (sebagian disahkan, sebagian ditolak/dikembalikan).
7. Impor Excel berlaku baik saat penyusunan RBA maupun di tengah tahun.

### 7.5 Penyusunan RBA sebelum definitif

1. Anggaran diajukan per unit kerja dan sumber dana.
2. Setiap rincian memuat sekurang-kurangnya **unit kerja/penanggung jawab**, rekening BLUD (dan rekening Pemda hasil pemetaan), item standar harga, uraian, volume, satuan, harga satuan, jumlah, dan sumber dana.
3. **Semua rincian wajib dirinci per item standar harga**, termasuk belanja yang selama ini dianggarkan gelondongan ("1 tahun × nilai"), seperti obat dan BMHP, bahan laboratorium, jasa, tagihan, dan makan pasien. Untuk kebutuhan yang memang bersifat langganan atau paket, item standar harga dapat memakai satuan yang sesuai (misalnya bulan, paket, kegiatan), tetapi tetap merupakan item terdaftar dengan harga standar.
4. Harga usulan harus sama dengan atau lebih rendah daripada standar harga.
5. Harga di atas standar ditolak pada tahap penyusunan, meskipun pengguna melampirkan bukti.
6. Status RBA: **draf → diajukan → (dikembalikan) → diverifikasi → disetujui internal → menunggu penetapan definitif → definitif**.
7. RBA mencantumkan target pendapatan dan **persentase ambang batas** belanja yang ditetapkan.
8. Penetapan definitif mencatat nomor/tanggal dokumen pengesahan, periode anggaran, dan versi.
9. Setelah definitif, versi dikunci sebagai baseline. Perubahan hanya melalui mekanisme pada 7.1.

### 7.6 RBA definitif dan pengendalian pagu

1. RBA definitif adalah RBA yang telah disahkan dan menjadi dasar pengendalian anggaran sesuai ketentuan yang berlaku.
2. Sistem menyimpan salinan versi definitif sebagai baseline. Semua laporan pagu, revisi, pergeseran, dan perubahan dapat ditelusuri terhadap baseline.
3. **Pengendalian pagu (batas keras) berada di tingkat unit × jenis belanja × sumber dana.**
4. Sisa pagu yang dapat digunakan dihitung sebagai:

   `sisa = pagu berlaku − realisasi − komitmen − nilai tercadang`

5. Rincian berfungsi sebagai referensi item, harga satuan, dan volume. Transaksi tidak boleh melebihi nilai rincian; jika diperlukan, rincian direvisi terlebih dahulu dalam pagu jenis belanja yang sama (7.7).

### 7.7 Revisi rincian

1. Revisi rincian dilakukan dalam **unit, jenis belanja, dan sumber dana yang sama**. Revisi yang tidak mengubah pagu jenis belanja unit dapat langsung berlaku; revisi yang mengubahnya diproses sesuai butir 7–11.
2. Revisi dapat berupa: mengubah volume atau harga satuan, menambah rincian baru, mengurangi atau menghapus rincian, serta memindahkan nilai antarrincian (pola satu-ke-banyak dan banyak-ke-satu diperbolehkan).
3. **Rincian baru wajib merujuk item yang ada di daftar standar harga.** Jika item belum ada, pengusul mengajukan usulan standar harga terlebih dahulu (7.4).
4. **Pagu tetap dan harga ≤ standar:**
   - untuk jenis **Belanja Barang dan Jasa**, revisi dilakukan oleh pengusul unit dan **berlaku saat disimpan** tanpa persetujuan, dengan jejak audit lengkap;
   - untuk **jenis belanja lainnya** (misalnya Belanja Modal), revisi berstatus menunggu dan baru berlaku setelah **disetujui perencana**; tidak berlaku persetujuan otomatis. Selama menunggu, rincian lama tetap berlaku dan rincian dalam paket dikunci.
   - Daftar jenis belanja yang boleh berlaku langsung disimpan sebagai konfigurasi (default: Belanja Barang dan Jasa).
5. **Harga > standar:** wajib melampirkan bukti harga pasar, berstatus menunggu, dan baru berlaku setelah **disetujui perencana**. Selama menunggu, rincian lama tetap berlaku.
6. **Indikator status pagu:** halaman edit rincian menampilkan secara langsung pagu jenis belanja unit, total rincian setelah edit, dan selisihnya, dengan status **Seimbang** atau **Pagu berubah** (bertambah/berkurang sebesar Rp …).
7. **Pengecekan saat menyimpan:** sistem membandingkan total rincian dengan pagu jenis belanja unit.
   - Jika **seimbang**, revisi disimpan dan berlaku untuk Belanja Barang dan Jasa; untuk jenis belanja lain, atau bila ada harga di atas standar, revisi menunggu persetujuan perencana.
   - Jika **pagu berubah**, sistem menampilkan dialog bahwa perubahan memerlukan persetujuan, dengan pilihan:
     1. **Kirim sebagai permintaan pergeseran antarunit** dalam jenis belanja dan sumber dana yang sama — seluruh perubahan dalam sesi edit menjadi satu paket revisi berstatus *menunggu pergeseran* yang terkait dengan permintaan pergeseran;
     2. **Lanjutkan edit rincian** — kembali ke halaman edit untuk menyeimbangkan atau mengubah rincian lain;
     3. **Batalkan perubahan.**
8. **Paket revisi menunggu pergeseran:** rincian lama tetap berlaku sampai pergeseran disahkan; saat disahkan, pergeseran dan paket revisi diterapkan dalam satu transaksi atomik; jika ditolak, paket revisi gugur. Rincian dalam paket yang menunggu dikunci dan tidak dapat diedit di paket lain.
9. **Melanjutkan edit setelah mengirim permintaan:** setelah permintaan pergeseran terkirim, pengusul dapat langsung membuka sesi edit baru untuk rincian lain yang tidak terkunci dalam jenis belanja yang sama. Sesi baru dihitung terhadap pagu berlaku (tanpa memperhitungkan paket yang masih menunggu).
10. **Peringatan saat meninggalkan halaman:** jika ada perubahan belum tersimpan dan status pagu berubah, sistem menampilkan pemberitahuan bahwa perubahan memerlukan persetujuan, dengan pilihan kirim sebagai permintaan pergeseran, kembali ke halaman edit, atau buang perubahan.
11. **Kenaikan maupun penurunan pagu wajib memiliki pasangan** di unit lain (7.8a). Kenaikan memerlukan unit lain yang berkurang; penurunan memerlukan unit lain yang bertambah. Jika pasangan tidak terpenuhi dalam batas waktu, permintaan beserta paket revisinya dinyatakan ditolak.
12. Revisi tidak boleh menurunkan nilai rincian di bawah realisasi + komitmen yang sudah melekat pada rincian tersebut.
13. Revisi tidak dapat dilakukan pada rincian yang sedang dipakai dalam transaksi berstatus diajukan, sampai transaksi tersebut selesai atau dibatalkan.
14. Revisi yang memerlukan perubahan unit diarahkan ke pergeseran antarunit; yang memerlukan perubahan jenis belanja atau sumber dana diarahkan ke perubahan RBA.

### 7.8 Pergeseran antarunit

1. Hanya dapat dilakukan setelah RBA definitif ditetapkan.
2. Jenis belanja dan sumber dana harus sama; unit asal dan penerima berbeda.
3. Sisa pagu jenis belanja unit asal harus cukup (rumus 7.6.4).
4. **Pencadangan:** sejak diajukan, nilai pergeseran **dicadangkan (diblokir)** pada pagu unit asal sehingga tidak dapat dipakai untuk transaksi. Pagu berlaku kedua unit belum berubah.
5. Jika ditolak atau dibatalkan, pencadangan dilepas.
6. Sebelum pengesahan, sistem memvalidasi ulang seluruh aturan.
7. Setelah disahkan, sistem mengurangi pagu unit asal, menambah pagu unit penerima, dan membentuk rincian tujuan dalam satu transaksi atomik. Total pagu tidak berubah.
8. Rincian tujuan wajib merujuk item dalam daftar standar harga. Harga di atas standar wajib bukti dan persetujuan perencana sebelum masuk pengesahan antarunit.
9. Mekanisme pengesahan (termasuk apakah kepala unit asal dan penerima sama-sama menyetujui) ditentukan dalam matriks kewenangan.
10. Semua perubahan menyimpan nilai sebelum dan sesudah, alasan, lampiran, pengusul, verifikator, pengesah, waktu, dan nomor dokumen.

### 7.8a Pasangan pergeseran dan batas waktu

1. Setiap permintaan pergeseran antarunit adalah **permintaan tambah** (unit membutuhkan kenaikan pagu) atau **permintaan kurang** (unit melepas pagu).
2. Permintaan hanya dapat diproses ke pengesahan jika memiliki **pasangan** dari unit lain dengan jenis belanja dan sumber dana yang sama, dengan arah berlawanan dan **nilai total yang sama**.
3. Satu permintaan dapat dipasangkan dengan satu atau beberapa permintaan lawan (satu-ke-banyak), selama totalnya tepat sama. Pasangan sebagian tidak diterapkan.
4. Pasangan dapat dibentuk oleh:
   - pengusul, dengan memilih permintaan terbuka dari unit lain; atau
   - perencana, yang mencocokkan permintaan terbuka antarunit.
   
   Unit pasangan wajib menyetujui keterlibatannya sesuai matriks kewenangan.
5. **Daftar permintaan terbuka** menampilkan permintaan tambah dan kurang per jenis belanja dan sumber dana yang belum berpasangan, beserta sisa waktunya. Unit dengan jenis belanja yang sama menerima notifikasi saat ada permintaan baru.
6. Nilai pada permintaan kurang dicadangkan sejak diajukan (7.8.4).
7. **Batas waktu pasangan:** jika pasangan lengkap tidak terbentuk dalam **maksimal 5 hari kerja** sejak permintaan diajukan, sistem otomatis menyatakan permintaan **ditolak**; paket revisi terkait gugur, rincian terbuka kembali, dan pencadangan dilepas. Pengusul dan unit terkait diberi notifikasi.
8. Sistem mengirim pengingat sebelum batas waktu berakhir (misalnya pada hari ke-4).
9. **Batas waktu pengesahan:** setelah pasangan lengkap, perencana menerima notifikasi untuk memproses pengesahan. Jika perencana **tidak merespons dalam 5 hari kerja**, sistem menyatakan pasangan **disetujui otomatis**, dengan ketentuan:
   - sistem menjalankan validasi ulang seluruh aturan (jenis belanja, sumber dana, sisa pagu, standar harga, kelengkapan persetujuan unit pasangan) pada saat batas waktu; jika ada yang gagal, persetujuan otomatis tidak dijalankan dan permintaan dinyatakan ditolak dengan alasan validasi;
   - pengingat dikirim kepada perencana sebelum batas waktu (misalnya pada hari kerja ke-3 dan ke-4);
   - audit log mencatat keputusan sebagai **"disetujui otomatis oleh sistem — batas waktu terlampaui"**, beserta penerima notifikasi dan waktu pengirimannya;
   - perencana dan pengusul menerima pemberitahuan bahwa persetujuan otomatis telah terjadi;
   - **pasangan yang memuat harga di atas standar dikecualikan**: tidak pernah disetujui otomatis dan tetap menunggu keputusan perencana; pengingat terus dikirim setiap hari kerja setelah batas waktu terlampaui.
10. **Hari kerja** dihitung berdasarkan kalender kerja yang dikelola administrator: Sabtu–Minggu, hari libur nasional, dan cuti bersama tidak dihitung.
11. Kedua batas waktu (pasangan dan pengesahan) dapat dikonfigurasi oleh administrator; perubahan nilai batas hanya berlaku untuk permintaan baru.

### 7.9 Perubahan RBA dan ambang batas

1. Perubahan yang lintas jenis belanja, lintas sumber dana, atau mengubah total anggaran diproses sebagai **perubahan RBA**, termasuk penyesuaian yang mengikuti perubahan APBD.
2. **Jadwal perubahan:** perubahan RBA hanya dapat **diajukan** dalam periode jadwal perubahan yang ditetapkan Pemda dan dicatat administrator (tanggal buka dan tutup). Di luar jadwal, usulan dapat disusun sebagai draf tetapi tidak dapat diajukan. Penggunaan ambang batas (butir 4) tidak terikat jadwal ini.
3. Perubahan RBA menghasilkan versi baseline baru yang tetap dapat dibandingkan dengan versi sebelumnya.
4. **Ambang batas:** belanja yang bersumber dari pendapatan BLUD dapat melampaui pagu sampai persentase ambang batas yang ditetapkan dalam RBA, apabila realisasi pendapatan BLUD melebihi target. Sistem:
   - menghitung kapasitas ambang batas yang tersedia dari realisasi pendapatan di atas target;
   - mencatat setiap penggunaan ambang batas dengan pengesahan pejabat berwenang;
   - menolak penggunaan melebihi persentase ambang batas, kecuali melalui perubahan RBA dengan persetujuan sesuai ketentuan;
   - menyediakan laporan penggunaan ambang batas.
5. Detail perhitungan, pejabat pengesah, dan kewajiban pelaporan ambang batas dikonfirmasi terhadap ketentuan BLUD dan Perkada yang berlaku (K-6).

### 7.10 Penatausahaan belanja

1. Transaksi belanja hanya dapat dibuat berdasarkan rincian anggaran yang berlaku (definitif atau hasil revisi/pergeseran/perubahan yang sah).
2. Pengguna memilih unit, sumber dana, dan rincian anggaran.
3. Sistem menampilkan pagu jenis belanja, realisasi, komitmen, nilai tercadang, dan sisa; serta nilai dan sisa rincian.
4. Harga satuan transaksi harus sama dengan harga satuan rincian yang berlaku, dan nilai transaksi tidak melebihi sisa rincian.
5. **Jika harga atau volume riil berbeda, pengguna melakukan revisi rincian terlebih dahulu (7.7) sebelum transaksi diajukan.**
6. Sistem menolak transaksi yang melampaui sisa pagu jenis belanja atau tidak memiliki dasar anggaran yang sah.
7. Sistem juga menolak transaksi yang melampaui **dana tersedia** pada sumber dananya (7.18).
8. Pembatalan transaksi yang sudah diposting dilakukan melalui reversal yang meninggalkan jejak audit.
9. Dokumen pembayaran mendukung dua pola: **uang muka/uang persediaan** (dengan pertanggungjawaban dan penggantian) dan **pembayaran langsung** ke pihak ketiga. Istilah dan format dokumen mengikuti Perkada (K-7).
10. Bendahara mengelola BKU, buku pembantu (kas tunai, bank, pajak, uang muka, rincian per jenis belanja), serta SPJ fungsional dan administratif.

### 7.11 Utang jangka pendek

1. Utang diakui ketika barang/jasa telah diterima atau tagihan sah diterima tetapi belum dibayar.
2. Utang mengikat pagu sebagai **komitmen** yang mengurangi sisa pagu sampai dibayar, lalu menjadi realisasi.
3. Sistem mencatat pemasok, dokumen tagihan, tanggal jatuh tempo, nilai, pembayaran sebagian, dan umur utang.
4. Utang yang belum terbayar pada akhir tahun tercatat sebagai saldo awal tahun berikutnya; sumber anggaran pembayarannya mengikuti kebijakan (K-8).

### 7.12 Pendapatan dan piutang operasional

1. Sistem mencatat pendapatan per sumber (pasien umum, BPJS/JKN, asuransi lain, kerja sama, pendapatan lain).
2. Piutang operasional yang belum terbayar diakui dari tagihan layanan, termasuk klaim BPJS yang diajukan.
3. Data tagihan dan klaim diimpor dari file (ekspor SIMRS dan berkas klaim) dengan template terdokumentasi.
4. Status klaim dicatat: diajukan, disetujui, *pending*, *dispute*, dibayar, ditolak, beserta selisih klaim.
5. Sistem menyediakan umur piutang, penerimaan sebagian, penyisihan, dan penghapusan sesuai kebijakan akuntansi.

### 7.13 Pengesahan pendapatan dan belanja BLUD (SP3B/SP2B)

1. Sistem menghasilkan SP3B per periode (periode dapat dikonfigurasi, misalnya triwulanan) dari pendapatan dan belanja yang telah diposting, disajikan dalam rekening Pemda hasil pemetaan.
2. SP3B melalui persetujuan pejabat keuangan dan pimpinan BLUD.
3. Nomor dan tanggal SP2B dari PPKD dicatat; setelah itu transaksi dalam periode tersebut terkunci.
4. Koreksi setelah SP2B dilakukan melalui transaksi koreksi pada periode berjalan.

### 7.14 Perpajakan

1. Setiap transaksi pembayaran dapat memiliki potongan/pungutan pajak (misalnya PPh 21, PPh 22, PPh 23, PPh final, PPN) dengan tarif yang dapat dikonfigurasi.
2. Sistem menghitung, mencatat ke buku pembantu pajak, dan memantau status setor.
3. Bukti setor (misalnya NTPN) dan nomor bukti potong dicatat sebagai referensi.
4. Sistem menyediakan rekonsiliasi pajak dipotong vs disetor dan laporan pendukung pelaporan pajak.

### 7.15 Persetujuan, delegasi, penomoran, dan tanda tangan

1. Matriks kewenangan menentukan peran, tahap, dan batas nilai persetujuan per jenis dokumen.
2. **Delegasi/Plt:** pejabat dapat mendelegasikan kewenangan untuk periode tertentu; persetujuan mencatat nama pejabat definitif dan pelaksana.
3. **Notifikasi:** pengguna diberi tahu saat ada tugas persetujuan, pengembalian, pengesahan, dan tenggat.
4. **Penomoran:** format nomor dokumen dapat dikonfigurasi per jenis dokumen dan tahun.
5. **Cetakan resmi:** dokumen (RBA, SP3B, dokumen pembayaran, SPJ, berita acara) dapat dicetak sesuai format yang ditetapkan.
6. **TTE:** tahap awal mendukung unggah dokumen bertanda tangan elektronik dan pencatatan metadatanya; integrasi langsung dengan penyelenggara TTE masuk fase integrasi.
7. **Tampilan persetujuan pimpinan (*mobile-first*):** persetujuan yang sampai ke Pimpinan BLUD (sesuai matriks kewenangan, misalnya RBA, perubahan RBA, ambang batas, SP3B, dan pengajuan di atas batas nilai tertentu) memiliki tampilan khusus yang sederhana dan dirancang pertama untuk ponsel:
   - **Kotak masuk persetujuan** berisi kartu ringkas per pengajuan: jenis dokumen, unit, nilai, pengusul, umur pengajuan, dan penanda mendesak.
   - **Ringkasan satu layar:** apa yang diajukan, dampaknya (misalnya pagu sebelum → sesudah), siapa saja yang sudah menyetujui, dan catatan penting; rincian lengkap dan lampiran dapat dibuka bila perlu.
   - **Aksi besar dan jelas:** Setujui, Tolak, Kembalikan; penolakan dan pengembalian wajib disertai catatan singkat.
   - Konfirmasi ulang identitas saat menyetujui (PIN atau biometrik perangkat sesuai kebijakan keamanan).
   - Notifikasi yang langsung membuka pengajuan terkait.
   - Dapat dipasang di layar utama ponsel seperti aplikasi, tanpa harus memasang dari toko aplikasi.
   - Tampilan yang sama dapat dipakai pejabat penyetuju lain yang memilih mode sederhana.

### 7.16 Unit kerja dan penanggung jawab

1. Unit kerja adalah master data berhierarki (misalnya Bagian Umum, Bagian Keuangan, Binpro, Penunjang, Instalasi Bedah Sentral, Instalasi Radiologi, Poli Mata).
2. Setiap rincian anggaran **wajib** memiliki satu unit kerja. Pagu dikendalikan per unit × jenis belanja × sumber dana (7.6), dan seluruh aturan revisi rincian serta pergeseran antarunit berlaku.
3. Satu unit dapat memiliki sub-unit; pagu dan laporan dapat dilihat per unit maupun agregat induknya.
4. Saat migrasi, label penanggung jawab dalam dokumen lama dipetakan ke master unit melalui tabel pemetaan label (7.19).

### 7.17 Dokumen anggaran formal

1. Selain versi internal (revisi rincian, pasangan pergeseran), sistem mengelola **dokumen anggaran formal** yang diserahkan ke Pemda:

   | Dokumen | Isi |
   |---|---|
   | **Penetapan** (DPA BLUD) | Baseline RBA definitif |
   | **Pergeseran ke-n** (DPPA BLUD Pergeseran I, II, …) | Konsolidasi semua revisi rincian dan pergeseran yang telah disahkan sejak dokumen sebelumnya |
   | **Perubahan** (DPPA BLUD Perubahan) | Hasil perubahan RBA (7.9), termasuk perubahan total; hanya dalam jadwal perubahan Pemda |

2. Dokumen Pergeseran ke-n dapat dibentuk **sewaktu-waktu** oleh perencana, sesuai kebutuhan: sistem mengambil **snapshot** seluruh rincian berlaku saat itu, membandingkannya dengan dokumen formal sebelumnya, lalu dokumen melalui persetujuan sampai Pimpinan BLUD (dapat dari tampilan *mobile-first*).
3. Dokumen formal yang telah disahkan terkunci, bernomor, dan menjadi pembanding dokumen berikutnya.
4. **Cetakan Lampiran Rincian Anggaran Belanja** mengikuti format yang berlaku:
   - kepala dokumen: urusan, bidang, unit organisasi, sub unit organisasi, program, kegiatan, sub kegiatan, waktu dan lokasi pelaksanaan;
   - kolom: kode rekening, komponen/uraian, lalu untuk dokumen sebelumnya dan dokumen ini masing-masing **jumlah, satuan, harga satuan, jumlah**, dan kolom **Berkurang/Bertambah**;
   - subtotal bertingkat mengikuti hierarki rekening (akun → kelompok → jenis → objek → rincian objek → sub rincian objek);
   - tanda tangan Pemimpin BLUD.
5. Tersedia dua tampilan cetak: **rinci** (per rekening BLUD dan item) untuk internal, dan **agregat** (per rekening Pemda) untuk Pemda.

### 7.17a Lampiran rencana penarikan dana (tidak mengikat)

1. Karena Pemda mewajibkan lampiran rencana penarikan dana dalam DPA/DPPA BLUD, setiap pagu unit × jenis belanja × sumber dana dapat diisi **rencana penarikan per triwulan** (TW I–IV).
2. Rencana penarikan **hanya untuk dokumen** dan **tidak dipakai** untuk mengendalikan transaksi; pengendalian kas tetap memakai dana tersedia (7.18).
3. Saat dokumen formal (Penetapan, Pergeseran ke-n, Perubahan) dibentuk, sistem memeriksa bahwa total rencana penarikan per pagu sama dengan pagu berlaku; pagu yang belum sesuai ditampilkan dalam daftar yang harus dilengkapi sebelum dokumen dapat diajukan.
4. Sistem menyediakan **isi otomatis** (dibagi rata per triwulan, atau mengikuti pola tahun lalu) yang dapat disunting.
5. Rencana penarikan ikut disimpan dalam snapshot dokumen formal dan dicetak sebagai lampiran sesuai format Pemda, pada tingkat rinci maupun agregat rekening Pemda.
6. Laporan pembanding rencana penarikan vs realisasi per triwulan tersedia sebagai informasi.

### 7.18 Pengendalian ketersediaan dana (berbasis realisasi pendapatan)

1. Belanja tidak dikendalikan dengan rencana penarikan (anggaran kas), melainkan dengan **dana yang benar-benar tersedia**. Setiap transaksi belanja harus lolos dua pemeriksaan:
   - **sisa pagu** unit × jenis belanja × sumber dana (batas anggaran, 7.6); dan
   - **dana tersedia** pada sumber dana tersebut (batas kas).
2. Dana tersedia dihitung **per sumber dana untuk seluruh RSUD** (bukan per unit):

   `dana tersedia = saldo kas awal tahun + realisasi pendapatan yang diterima − belanja yang sudah dibayar − komitmen berjalan − cadangan kas minimum`

   - *saldo kas awal tahun* — saldo kas BLUD per 1 Januari (termasuk SiLPA) yang ditetapkan saat pembukaan tahun;
   - *realisasi pendapatan yang diterima* — penerimaan kas yang telah diposting (jasa layanan, pembayaran klaim BPJS, kerja sama, pendapatan lain); piutang yang belum dibayar, termasuk klaim BPJS yang sudah disetujui verifikator tetapi belum dibayar, **tidak** dihitung;
   - *komitmen berjalan* — transaksi belanja yang telah disetujui tetapi belum dibayar, ditambah utang yang belum dibayar;
   - *cadangan kas minimum* — nilai yang disisihkan untuk belanja wajib, diatur melalui pengaturan cadangan (7.18a).
3. Pemeriksaan dilakukan saat transaksi belanja **diajukan** dan **divalidasi ulang saat disetujui**. Jika nilai transaksi melebihi dana tersedia, transaksi **ditolak** dengan pesan yang menunjukkan posisi dana saat ini.
4. Utang yang timbul dari transaksi yang sudah disetujui tetap dicatat (komitmennya sudah dihitung saat persetujuan). Pembayaran tetap memerlukan saldo kas/bank riil yang cukup.
5. Belanja bersumber APBD atau hibah dihitung terhadap penerimaan dana sumber tersebut (misalnya dana yang telah ditransfer), bukan terhadap pendapatan jasa layanan.
6. **Dashboard posisi dana** untuk perencana, pejabat keuangan, dan Pimpinan BLUD: saldo awal, pendapatan diterima, belanja dibayar, komitmen, cadangan, dan dana tersedia per sumber dana; tren bulanan pendapatan vs belanja; serta daftar transaksi yang tertahan karena dana tidak cukup.
7. Perubahan pagu (revisi, pergeseran, perubahan RBA, ambang batas) tidak memerlukan penyesuaian rencana kas apa pun.

### 7.18a Pengaturan cadangan kas minimum

1. Cadangan kas minimum diatur **per tahun anggaran dan per sumber dana** melalui halaman pengaturan.
2. Dua cara penetapan nilai (dapat dipilih per aturan):
   - **Nilai tetap** — misalnya Rp 5 M;
   - **Kebutuhan N bulan** — rata-rata belanja bulanan rekening wajib (dari pagu berlaku ÷ 12, atau rata-rata realisasi 3 bulan terakhir, sesuai pilihan) dikalikan jumlah bulan, misalnya 2 bulan.
3. Setiap aturan memuat **daftar rekening belanja wajib** yang dilindungi cadangan (misalnya gaji/jasa pelayanan BLUD, listrik, air, telepon/internet, obat dan BMHP, makan pasien). Daftar dapat diisi per rekening BLUD atau per kelompok rekening.
4. **Penggunaan cadangan:**
   - transaksi pada **rekening wajib** dibatasi oleh *dana tersedia + cadangan* (cadangan memang disiapkan untuk belanja ini);
   - transaksi pada **rekening lain** dibatasi oleh *dana tersedia* saja.
5. Penetapan dan perubahan aturan cadangan diajukan oleh pejabat keuangan atau perencana dan **disetujui Pimpinan BLUD** (dapat dari tampilan *mobile-first*). Nilai baru berlaku sejak disetujui dan tercatat di jejak audit.
6. Untuk metode *kebutuhan N bulan*, nilai dihitung ulang otomatis setiap awal bulan dan setiap pagu rekening wajib berubah; perubahan nilai hasil hitung ulang tidak memerlukan persetujuan ulang selama aturannya tidak berubah.
7. Dashboard posisi dana menampilkan cadangan terpisah: nilai cadangan, pemakaian oleh belanja wajib, dan sisa cadangan.
8. Cadangan dapat dinonaktifkan (nilai 0) melalui alur persetujuan yang sama.

### 7.19 Impor RBA dari format Excel lama (migrasi)

1. Sistem dapat membaca file Lampiran Rincian Anggaran Belanja yang berlaku saat ini: kepala dokumen, kode rekening bertingkat di kolom terpisah, komponen, jumlah, satuan, harga satuan, jumlah, dan label penanggung jawab.
2. Hasil baca masuk ke **area persiapan (staging)**, bukan langsung menjadi RBA.
3. Untuk setiap baris, pengguna melengkapi:
   - **item standar harga** — baris gelondongan ("1 tahun × nilai") wajib dipecah menjadi item-item standar harga sebelum dapat diproses; sistem menampilkan kandidat item hasil pengecekan kecocokan;
   - **unit kerja** — dari tabel pemetaan label penanggung jawab ke master unit.
4. Sistem memvalidasi bahwa total per rekening hasil rincian sama dengan total di file sumber, dan menampilkan selisih per rekening.
5. Baris yang mengandung kesalahan rumus (misalnya #REF!), catatan manual, atau baris tanpa nilai dilaporkan untuk ditinjau.
6. RBA hasil impor hanya dapat diajukan setelah seluruh baris terpetakan ke item standar dan unit, dan seluruh total cocok.

## 8. Alur Proses Utama

### 8.1 Impor standar harga Pemda

1. Administrator memilih tahun anggaran dan mengunggah template Excel.
2. Sistem memvalidasi struktur kolom, kode, satuan, nilai harga, duplikasi, dan kelengkapan.
3. Sistem menjalankan pengecekan kecocokan terhadap standar internal dan menandai calon duplikat.
4. Sistem menampilkan ringkasan baris valid, gagal, dan calon duplikat.
5. Pengguna mengunduh laporan kesalahan, memperbaiki file, lalu mengimpor ulang.
6. Pengguna berwenang mengonfirmasi impor.
7. Sistem menyimpan versi, sumber file, waktu, dan pengguna pengimpor.

### 8.2 Usulan standar harga internal

1. Pengusul unit atau perencana mengisi usulan: uraian, spesifikasi, satuan, harga, kategori, dan bukti harga.
2. Sistem langsung menampilkan item standar yang mirip agar pengusul dapat memakai item yang ada.
3. Jika tetap diajukan, perencana menerima usulan beserta **daftar kecocokan dan skornya**.
4. Perencana menolak (mengarahkan ke item yang ada), mengembalikan, atau mengesahkan sebagai item baru dengan alasan.
5. Item yang disahkan langsung dapat dipakai dalam penyusunan dan revisi rincian, termasuk di tengah tahun.
6. **Melalui impor Excel:** pengusul mengunduh template, mengunggah file, meninjau pratinjau validasi dan kecocokan per baris, lalu mengajukan batch. Perencana mengesahkan sekaligus baris tanpa kecocokan tinggi, dan memutuskan per baris untuk baris kecocokan tinggi (7.4a).

### 8.3 Penyusunan dan penetapan RBA

1. Pengelola menetapkan tahun anggaran, unit, rekening dan pemetaannya, sumber dana, serta versi standar harga.
2. Unit menyusun rincian dengan memilih item dari daftar standar harga.
3. Sistem memvalidasi harga terhadap standar dan kelengkapan pemetaan rekening.
4. Unit mengajukan RBA.
5. Verifikator mengembalikan atau meneruskan.
6. Pejabat berwenang memberi persetujuan internal.
7. RBA ditetapkan definitif setelah pengesahan direkam.
8. Sistem mengunci baseline dan membentuk pagu per unit × jenis belanja × sumber dana.

### 8.4 Revisi rincian

1. Pengusul membuka halaman edit rincian untuk satu unit, jenis belanja, dan sumber dana.
2. Selama mengedit, indikator status pagu menampilkan pagu jenis belanja, total rincian, selisih, dan status Seimbang/Pagu berubah.
3. Sistem memvalidasi setiap baris: item ada di daftar standar harga, harga terhadap standar, dan rincian tidak turun di bawah realisasi + komitmen.
4. Saat menyimpan:
   - **Seimbang, Belanja Barang dan Jasa, semua harga ≤ standar** → revisi berlaku.
   - **Seimbang, jenis belanja lain** → revisi menunggu persetujuan perencana.
   - **Seimbang dan ada harga > standar** (jenis apa pun) → pengusul melampirkan bukti; revisi menunggu persetujuan perencana.
   - **Pagu berubah** → dialog persetujuan: kirim sebagai permintaan pergeseran antarunit, lanjutkan edit, atau batalkan.
5. Jika dikirim sebagai permintaan pergeseran, paket revisi menunggu pengesahan (8.5) dan pengusul dapat melanjutkan edit rincian lain yang tidak terkunci.
6. Jika pengusul meninggalkan halaman dengan perubahan belum tersimpan yang mengubah pagu, sistem menampilkan peringatan bahwa perubahan memerlukan persetujuan.

### 8.5 Pergeseran antarunit

1. Unit mengajukan **permintaan tambah** atau **permintaan kurang** dari halaman edit rincian (8.4) atau secara langsung, dengan jenis belanja, sumber dana, nilai, dan rincian terkait (item dari daftar standar harga).
2. Sistem memvalidasi jenis belanja, sumber dana, dan — untuk permintaan kurang — kecukupan sisa pagu, lalu **mencadangkan** nilai permintaan kurang.
3. Permintaan tampil di daftar permintaan terbuka dan unit sejenis menerima notifikasi.
4. Pengusul atau perencana membentuk **pasangan** dengan permintaan berlawanan dari unit lain hingga totalnya sama; unit pasangan menyetujui keterlibatannya.
5. Jika pasangan lengkap tidak terbentuk dalam **5 hari kerja**, permintaan otomatis ditolak; paket revisi gugur dan pencadangan dilepas.
6. Jika ada harga di atas standar, pengajuan melewati persetujuan perencana dengan bukti.
7. Pasangan lengkap diteruskan ke perencana untuk pengesahan; sebelum pengesahan, sistem memvalidasi ulang. Jika perencana tidak merespons dalam **5 hari kerja**, pasangan disetujui otomatis setelah validasi ulang (7.8a.9).
8. Setelah disahkan, pagu seluruh unit yang terlibat dan paket revisi terkait diperbarui dalam satu transaksi atomik; pencadangan dilepas.
9. Sistem membuat nomor referensi dan menyimpan seluruh persetujuan.

### 8.6 Perubahan RBA dan penggunaan ambang batas

1. Perencana menyusun usulan perubahan (lintas jenis, lintas sumber dana, perubahan total) atau usulan penggunaan ambang batas.
2. Untuk ambang batas, sistem menampilkan kapasitas yang tersedia dari realisasi pendapatan di atas target.
3. Usulan disahkan sesuai matriks kewenangan.
4. Sistem membentuk versi baseline baru atau mencatat penggunaan ambang batas, lalu memperbarui pagu.

### 8.7 Penatausahaan belanja

1. Petugas memilih unit, sumber dana, pemasok/penerima, dan rincian anggaran.
2. Sistem memeriksa harga, sisa rincian, sisa pagu jenis belanja, dan status anggaran.
3. Jika harga atau volume berbeda dari rincian, petugas diarahkan ke revisi rincian terlebih dahulu.
4. Petugas memasukkan detail transaksi, pajak, dan dokumen pendukung.
5. Transaksi melewati pemeriksaan dan persetujuan sesuai jenisnya; dokumen pembayaran dibuat.
6. Bendahara membayar, mencatat di BKU dan buku pembantu, serta memotong/memungut pajak.
7. Sistem membuat jurnal sesuai konfigurasi dan memperbarui realisasi.

### 8.8 Utang jangka pendek

1. Penerimaan barang/jasa atau tagihan dicatat; utang diakui dan pagu terikat sebagai komitmen.
2. Pembayaran (penuh atau sebagian) mengurangi utang dan mengubah komitmen menjadi realisasi.
3. Sistem memantau jatuh tempo dan umur utang.

### 8.9 Pendapatan dan piutang operasional

1. Data tagihan layanan dan klaim diimpor dari file.
2. Sistem membentuk piutang per debitur/penjamin.
3. Status klaim diperbarui; penerimaan dicocokkan dengan piutang.
4. Selisih klaim dan piutang yang tidak tertagih diproses sesuai kebijakan.

### 8.10 SP3B/SP2B

1. Pada akhir periode, sistem menyusun SP3B dari transaksi yang diposting.
2. Pejabat keuangan dan pimpinan BLUD menyetujui; SP3B dicetak/diekspor.
3. Nomor dan tanggal SP2B dicatat; periode terkunci.

## 9. Kebutuhan Fungsional

**Prioritas:** W = wajib untuk fase tersebut, P = penting, O = opsional.

### 9.1 Master data, rekening, dan konfigurasi

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-001 | Master unit kerja dan hierarki organisasi | W | 1 |
| FR-002 | Master rekening Pemda dan rekening BLUD berstruktur Akun–Kelompok–Jenis–Objek–Rincian Objek–Sub Rincian Objek; nomenklatur Urusan–Bidang Urusan–Program–Kegiatan–Sub Kegiatan; satuan; barang/jasa | W | 1 |
| FR-003 | Pemetaan rekening BLUD ke rekening Pemda dengan versi dan tanggal berlaku; layar rekening belum terpetakan, pemetaan massal dari Excel, validasi kesetaraan tingkat, laporan perbedaan antarversi | W | 1 |
| FR-004 | Validasi bahwa setiap rekening BLUD yang dapat dianggarkan sudah dipetakan | W | 1 |
| FR-005 | Konfigurasi tingkat kode yang mendefinisikan "jenis belanja" (default: tingkat jenis rekening Pemda) | W | 1 |
| FR-006 | Master sumber dana dan konfigurasi alur per sumber dana | W | 1 |
| FR-007 | Tahun dan periode anggaran | W | 1 |
| FR-008 | Peran, matriks kewenangan, dan batas nilai persetujuan | W | 1 |
| FR-009 | Delegasi/Plt berjangka waktu | W | 1 |
| FR-010 | Konfigurasi format penomoran dokumen | W | 1 |
| FR-011 | Notifikasi tugas, pengembalian, pengesahan, dan tenggat | P | 1 |
| FR-012 | Perubahan master penting tercatat di audit log dan memakai versi efektif | W | 1 |
| FR-090 | Impor struktur kodefikasi rekening dan nomenklatur dari Excel, berversi per tahun | W | 1 |

### 9.2 Standar harga

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-013 | Impor standar harga Pemda dari Excel per tahun anggaran | W | 1 |
| FR-014 | Validasi format, tipe data, duplikasi, kode, satuan, harga sebelum aktivasi | W | 1 |
| FR-015 | Menyimpan sumber, periode, versi, tanggal impor, dan pengimpor | W | 1 |
| FR-016 | Usulan standar harga internal oleh pengusul unit atau perencana | W | 1 |
| FR-017 | **Pengecekan kecocokan**: menampilkan item standar yang mirip beserta skor (uraian, kode, satuan, spesifikasi, harga) kepada pengusul saat mengisi dan kepada perencana sebelum mengesahkan | W | 1 |
| FR-018 | Keputusan perencana: tolak/arahkan ke item ada, kembalikan, sahkan sebagai baru dengan alasan wajib | W | 1 |
| FR-019 | Pengecekan kecocokan saat impor Pemda terhadap standar internal | P | 1 |
| FR-020 | Harga item standar yang sudah disahkan terkunci selama tahun anggaran | W | 1 |
| FR-103 | Penambahan item standar harga internal di tengah tahun (setelah RBA definitif), dengan tanggal berlaku dan penanda tambahan tengah tahun; tautan usulan dari formulir transaksi dan halaman edit rincian | W | 1 |
| FR-104 | Impor Excel usulan standar harga internal: template, validasi per baris, pengecekan kecocokan per baris, pratinjau berkategori, pengajuan sebagai batch | W | 1 |
| FR-105 | Keputusan perencana atas batch: sahkan sekaligus untuk baris tanpa kecocokan tinggi; keputusan per baris wajib untuk kecocokan tinggi | W | 1 |
| FR-021 | Referensi standar pada rincian historis tidak berubah secara retroaktif | W | 1 |

### 9.3 Penyusunan RBA

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-022 | Unit menyusun dan mengajukan anggaran per sumber dana | W | 1 |
| FR-023 | Rincian hanya dapat memilih item dari daftar standar harga aktif | W | 1 |
| FR-024 | Rincian memuat rekening BLUD, rekening Pemda (pemetaan), item, volume, satuan, harga, jumlah, sumber dana | W | 1 |
| FR-025 | Menolak harga di atas standar saat penyusunan | W | 1 |
| FR-026 | Status: draf, diajukan, dikembalikan, diverifikasi, disetujui internal, menunggu penetapan, definitif | W | 1 |
| FR-027 | Target pendapatan dan persentase ambang batas dalam RBA | W | 1 |
| FR-028 | Nomor/tanggal pengesahan dan snapshot baseline definitif | W | 1 |
| FR-029 | RBA definitif tidak dapat diedit langsung | W | 1 |
| FR-030 | Pembentukan pagu per unit × jenis belanja × sumber dana | W | 1 |

### 9.4 Revisi rincian

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-031 | Revisi rincian dalam unit, jenis belanja, dan sumber dana yang sama; total jenis belanja tetap | W | 1 |
| FR-032 | Tambah/ubah/kurangi rincian, pola satu-ke-banyak dan banyak-ke-satu | W | 1 |
| FR-033 | Rincian baru wajib dari daftar standar harga; tautan langsung ke usulan standar baru | W | 1 |
| FR-034 | Revisi seimbang dengan harga ≤ standar berlaku saat disimpan khusus Belanja Barang dan Jasa (daftar jenis dapat dikonfigurasi) | W | 1 |
| FR-106 | Revisi seimbang pada jenis belanja selain yang dikonfigurasi wajib disetujui perencana, tanpa persetujuan otomatis | W | 1 |
| FR-035 | Harga > standar wajib bukti dan persetujuan perencana; rincian lama berlaku selama menunggu | W | 1 |
| FR-036 | Menolak revisi yang menurunkan rincian di bawah realisasi + komitmen | W | 1 |
| FR-037 | Mengunci rincian yang sedang dipakai transaksi berstatus diajukan | W | 2 |
| FR-038 | Mengarahkan perubahan lintas unit ke pergeseran dan lintas jenis/sumber dana ke perubahan RBA | W | 1 |
| FR-091 | Indikator status pagu langsung di halaman edit: pagu jenis belanja, total rincian, selisih, status Seimbang/Pagu berubah | W | 1 |
| FR-092 | Pengecekan saat menyimpan; jika pagu berubah tampil dialog: kirim sebagai permintaan pergeseran antarunit, lanjutkan edit, atau batalkan | W | 1 |
| FR-093 | Paket revisi berstatus menunggu pergeseran, diterapkan atomik bersama pergeseran saat disahkan, gugur jika ditolak | W | 1 |
| FR-094 | Mengunci rincian dalam paket menunggu; pengusul dapat melanjutkan edit rincian lain yang tidak terkunci | W | 1 |
| FR-095 | Peringatan saat meninggalkan halaman edit jika ada perubahan belum tersimpan yang mengubah pagu | W | 1 |

### 9.5 Pergeseran antarunit

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-039 | Pengajuan setelah RBA definitif, jenis belanja dan sumber dana sama | W | 1 |
| FR-040 | Validasi sisa pagu unit asal (pagu − realisasi − komitmen − tercadang) | W | 1 |
| FR-041 | Pencadangan nilai sejak pengajuan; dilepas jika ditolak/dibatalkan | W | 1 |
| FR-042 | Pengesahan khusus antarunit sesuai matriks kewenangan | W | 1 |
| FR-043 | Persetujuan perencana untuk rincian tujuan dengan harga di atas standar | W | 1 |
| FR-044 | Validasi ulang saat pengesahan | W | 1 |
| FR-045 | Penerapan atomik: pagu asal, pagu penerima, dan rincian tujuan | W | 1 |
| FR-046 | Menyimpan nilai sebelum/sesudah, alasan, dokumen, pengguna, waktu, keputusan | W | 1 |
| FR-096 | Permintaan tambah/kurang wajib berpasangan dengan unit lain (jenis belanja dan sumber dana sama, arah berlawanan, total sama); dukungan satu-ke-banyak | W | 1 |
| FR-097 | Daftar permintaan terbuka per jenis belanja dan sumber dana, dengan sisa waktu; pencocokan oleh pengusul atau perencana; persetujuan unit pasangan | W | 1 |
| FR-098 | Penolakan otomatis jika pasangan tidak lengkap dalam batas waktu (default 5 hari kerja): paket revisi gugur, rincian terbuka, pencadangan dilepas | W | 1 |
| FR-099 | Notifikasi permintaan baru ke unit sejenis, pengingat sebelum batas waktu, dan pemberitahuan penolakan otomatis | W | 1 |
| FR-100 | Kalender kerja (akhir pekan, libur nasional, cuti bersama) yang dikelola administrator dan dipakai untuk semua perhitungan batas waktu | W | 1 |
| FR-101 | Notifikasi dan pengingat kepada perencana atas pasangan lengkap; persetujuan otomatis setelah 5 hari kerja tanpa respons, didahului validasi ulang; pasangan dengan harga di atas standar dikecualikan | W | 1 |
| FR-102 | Audit log dan laporan khusus persetujuan otomatis | W | 1 |

### 9.6 Perubahan RBA dan ambang batas

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-047 | Perubahan RBA lintas jenis, lintas sumber dana, atau perubahan total, menghasilkan versi baseline baru | W | 1 |
| FR-048 | Perhitungan kapasitas ambang batas dari realisasi pendapatan di atas target | W | 2 |
| FR-049 | Pencatatan dan pengesahan penggunaan ambang batas; penolakan di atas persentase | W | 2 |
| FR-050 | Perbandingan antarversi: baseline awal, revisi, pergeseran, perubahan, ambang batas | W | 1 |

### 9.7 Penatausahaan belanja dan bendahara

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-051 | Transaksi wajib merujuk rincian anggaran yang berlaku | W | 2 |
| FR-052 | Tampilan pagu jenis belanja, realisasi, komitmen, tercadang, sisa; nilai dan sisa rincian | W | 2 |
| FR-053 | Harga transaksi = harga rincian; nilai ≤ sisa rincian; jika berbeda diarahkan ke revisi rincian | W | 2 |
| FR-054 | Menolak transaksi tanpa anggaran sah atau melampaui sisa pagu jenis belanja | W | 2 |
| FR-055 | Dokumen pembayaran: uang muka/uang persediaan beserta pertanggungjawaban, dan pembayaran langsung | W | 2 |
| FR-056 | BKU penerimaan dan pengeluaran | W | 2 |
| FR-057 | Buku pembantu: kas tunai, bank, pajak, uang muka, rincian per jenis belanja | W | 2 |
| FR-058 | SPJ fungsional dan administratif bendahara | W | 2 |
| FR-059 | Alur pemeriksaan dan persetujuan transaksi sesuai matriks kewenangan | W | 2 |
| FR-060 | Pembatalan/koreksi melalui reversal dengan jejak audit | W | 2 |

### 9.8 Perpajakan

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-061 | Master jenis dan tarif pajak yang dapat dikonfigurasi | W | 2 |
| FR-062 | Perhitungan potongan/pungutan pada transaksi pembayaran | W | 2 |
| FR-063 | Pencatatan setoran, bukti setor, dan nomor bukti potong | W | 2 |
| FR-064 | Rekonsiliasi pajak dipotong vs disetor dan laporan pendukung | W | 2 |

### 9.9 Utang jangka pendek

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-065 | Pengakuan utang dari penerimaan barang/jasa atau tagihan | W | 2 |
| FR-066 | Utang mengikat pagu sebagai komitmen sampai dibayar | W | 2 |
| FR-067 | Pembayaran penuh/sebagian, jatuh tempo, umur utang | W | 2 |
| FR-068 | Saldo utang akhir tahun terbawa sebagai saldo awal | W | 2 |

### 9.10 Pendapatan dan piutang operasional

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-069 | Pencatatan pendapatan per sumber dengan referensi transaksi | W | 2 |
| FR-070 | Impor tagihan layanan dan klaim dari file (SIMRS, berkas klaim) | W | 2 |
| FR-071 | Status klaim dan selisih klaim | W | 2 |
| FR-072 | Piutang per debitur/penjamin, penerimaan sebagian, umur piutang | W | 2 |
| FR-073 | Penyisihan dan penghapusan piutang sesuai kebijakan | P | 3 |

### 9.11 SP3B/SP2B dan pelaporan ke Pemda

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-074 | Penyusunan SP3B per periode dalam rekening Pemda | W | 2 |
| FR-075 | Persetujuan SP3B dan pencatatan SP2B | W | 2 |
| FR-076 | Penguncian transaksi periode yang telah ber-SP2B | W | 2 |
| FR-077 | Ekspor data anggaran dan realisasi berbasis rekening Pemda untuk SIPD | P | 3 |

### 9.12 Modul keuangan lain

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-078 | Kas, rekening bank, mutasi, dan rekonsiliasi bank | W | 2 |
| FR-079 | Persediaan: mutasi, stok opname, nilai persediaan (atau impor dari aplikasi lain) | P | 3 |
| FR-080 | Aset tetap: perolehan, mutasi, penyusutan, penghapusan (atau impor dari aplikasi lain) | P | 3 |
| FR-081 | Jurnal otomatis berbasis aturan yang dapat dikonfigurasi dan ditinjau | W | 3 |
| FR-082 | Penutupan periode dan pembatasan transaksi setelah tutup buku | W | 3 |
| FR-083 | Laporan keuangan, laporan manajemen, rekonsiliasi, dan laporan audit | W | 3 |

### 9.13 Dokumen, impor/ekspor, dan audit

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-084 | Cetakan dokumen resmi sesuai format yang ditetapkan | W | 1 |
| FR-085 | Unggah dokumen bertanda tangan elektronik dan metadata TTE | P | 2 |
| FR-107 | Kotak masuk persetujuan *mobile-first* untuk Pimpinan BLUD: kartu ringkas, ringkasan dampak satu layar, aksi Setujui/Tolak/Kembalikan dengan catatan | W | 1 |
| FR-108 | Konfirmasi identitas saat menyetujui dari ponsel (PIN/biometrik perangkat) dan notifikasi yang membuka langsung pengajuan | W | 1 |
| FR-109 | Dapat dipasang di layar utama ponsel (aplikasi web progresif) dan dapat dipakai pejabat penyetuju lain | P | 1 |

### 9.14 Unit, dokumen formal, ketersediaan dana, dan impor RBA

| ID | Kebutuhan | Prioritas | Fase |
|---|---|---|---|
| FR-110 | Master unit kerja/penanggung jawab berhierarki; unit wajib diisi pada setiap rincian | W | 1 |
| FR-111 | Pemetaan rekening rinci BLUD ke rekening agregat Pemda secara banyak-ke-satu | W | 1 |
| FR-112 | Pembentukan dokumen Pergeseran ke-n dari snapshot rincian berlaku, dibandingkan dengan dokumen formal sebelumnya, dengan persetujuan sampai Pimpinan BLUD | W | 1 |
| FR-113 | Cetakan Lampiran Rincian Anggaran Belanja (Penetapan, Pergeseran ke-n, Perubahan) dengan kolom sebelum, sesudah, dan Berkurang/Bertambah; tampilan rinci dan agregat per rekening Pemda | W | 1 |
| FR-114 | Perhitungan dana tersedia per sumber dana: saldo kas awal + pendapatan diterima − belanja dibayar − komitmen − cadangan minimum | W | 2 |
| FR-115 | Penolakan transaksi belanja yang melampaui dana tersedia, saat diajukan dan saat disetujui | W | 2 |
| FR-116 | Dashboard dan laporan posisi dana: pendapatan vs belanja, komitmen, cadangan, transaksi tertahan | W | 2 |
| FR-119 | Jadwal perubahan RBA dari Pemda (tanggal buka/tutup); pengajuan perubahan di luar jadwal ditolak | W | 1 |
| FR-120 | Pengaturan cadangan kas minimum per tahun dan sumber dana: metode nilai tetap atau kebutuhan N bulan, daftar rekening wajib, persetujuan Pimpinan BLUD | W | 2 |
| FR-121 | Transaksi rekening wajib dibatasi dana tersedia + cadangan; rekening lain dibatasi dana tersedia | W | 2 |
| FR-122 | Rencana penarikan dana per triwulan yang tidak mengikat, isi otomatis, validasi total saat dokumen formal dibentuk, dan cetakan lampiran | W | 1 |
| FR-117 | Impor RBA dari format Excel lama ke staging, pemecahan baris gelondongan ke item standar, pemetaan label ke unit, rekonsiliasi total per rekening | W | 1 |
| FR-118 | Laporan perbandingan anggaran antar tahun (misalnya 2025 vs 2026) per rekening dan unit | P | 1 |
| FR-086 | Impor/ekspor Excel atau CSV dengan template terdokumentasi | W | 1 |
| FR-087 | Hasil impor menampilkan baris gagal dan alasan | W | 1 |
| FR-088 | Audit log untuk perubahan penting dan aktivitas persetujuan; tidak dapat diubah pengguna bisnis | W | 1 |
| FR-089 | Ekspor laporan sesuai hak akses | W | 1 |

## 10. Kebutuhan Nonfungsional

| ID | Kebutuhan | Status | Penanggung jawab keputusan |
|---|---|---|---|
| NFR-001 Keamanan | Autentikasi (SSO Pemda bila tersedia), MFA untuk peran pengesah dan bendahara, batas waktu sesi, RBAC, pembatasan data per unit | Perlu keputusan | TI Pemda/RSUD |
| NFR-002 Pemisahan tugas | Pengajuan, verifikasi, pengesahan, dan pembayaran dapat dipisahkan dan diuji | Ditetapkan | — |
| NFR-003 Auditabilitas | Pelaku, waktu, alasan, nilai sebelum dan sesudah | Ditetapkan | — |
| NFR-004 Integritas | Pergeseran, revisi, dan perubahan RBA diproses atomik; penanganan konkurensi pada pagu dan rincian | Ditetapkan | — |
| NFR-005 Ketersediaan | Target layanan dan jendela pemeliharaan | Ditetapkan sebelum desain teknis | Pemilik sistem |
| NFR-006 Kinerja | Target waktu respons, termasuk pengecekan kecocokan standar harga | Ditetapkan setelah estimasi volume | Tim teknis |
| NFR-007 Pencadangan | Backup terjadwal, pemantauan, uji pemulihan | Target RPO/RTO perlu keputusan | TI RSUD |
| NFR-008 Privasi | Akses data keuangan, data pasien pada tagihan/klaim, dan dokumen pendukung dibatasi sesuai kebutuhan | Ditetapkan | — |
| NFR-009 Kemudahan penggunaan | Pesan validasi menjelaskan masalah dan arah perbaikan (misalnya "harga berbeda — lakukan revisi rincian") | Ditetapkan | — |
| NFR-013 Mobile | Tampilan persetujuan pimpinan dapat dipakai penuh di layar ponsel (lebar mulai 360 px), memuat cepat di jaringan seluler, dan dapat menyelesaikan satu persetujuan dalam ≤ 3 ketukan dari notifikasi | Ditetapkan | — |
| NFR-010 Portabilitas | Ekspor format terbuka terdokumentasi | Ditetapkan | — |
| NFR-011 Keamanan dokumen | Batas tipe/ukuran file dan pemeriksaan lampiran | Ditetapkan | TI RSUD |
| NFR-012 Retensi | Masa simpan dokumen dan log | Perlu keputusan | Arsip/keuangan |

## 11. Struktur Data Konseptual

- **UnitKerja:** kode, nama, induk, status.
- **RekeningPemda / RekeningBLUD:** kode, uraian, tingkat, induk, status.
- **PemetaanRekening:** rekening BLUD, rekening Pemda, versi, berlaku mulai/sampai.
- **SumberDana:** kode, nama, konfigurasi alur.
- **StandarHarga:** sumber (Pemda/internal), tahun, kode item, uraian, spesifikasi, satuan, harga, versi, status.
- **UsulanStandarHarga:** pengusul, data item, bukti, daftar kecocokan beserta skor, keputusan, alasan, batch impor (jika ada), penanda tambahan tengah tahun.
- **BatchUsulanStandar:** file impor, pengusul, jumlah baris per kategori, status, keputusan perencana.
- **RBAHeader:** tahun, versi, jenis versi (awal/definitif/perubahan), status, nomor dokumen, target pendapatan, persen ambang batas.
- **RBADetail:** unit, sumber dana, rekening BLUD, rekening Pemda, item standar, volume, satuan, harga standar, harga rincian, nilai.
- **PaguJenisBelanja:** unit, jenis belanja, sumber dana, pagu baseline, pagu berlaku, realisasi, komitmen, tercadang.
- **Kodefikasi:** jenis struktur (rekening/nomenklatur), kode, uraian, tingkat, induk, versi tahun.
- **RevisiRincian (paket):** nomor, unit, jenis belanja, sumber dana, detail sebelum/sesudah, selisih terhadap pagu, bukti, status (berlaku/menunggu perencana/menunggu pergeseran/gugur), referensi pergeseran, persetujuan perencana.
- **PermintaanPergeseran:** nomor, unit, arah (tambah/kurang), jenis belanja, sumber dana, nilai, paket revisi terkait, waktu pengajuan, batas waktu, status (terbuka/berpasangan/disahkan/ditolak otomatis/ditolak).
- **PasanganPergeseran:** daftar permintaan tambah dan kurang yang dipasangkan, total, pembentuk pasangan, persetujuan unit, status pengesahan.
- **PerubahanRBA / PenggunaanAmbangBatas:** nomor, dasar, nilai, versi hasil, pengesahan.
- **Persetujuan:** objek, tahap, pemberi persetujuan, pejabat definitif/delegasi, keputusan, waktu, catatan.
- **TransaksiBelanja:** nomor, tanggal, unit, sumber dana, pemasok/penerima, rincian, harga, volume, nilai, pajak, status.
- **DokumenPembayaran:** jenis (uang muka/langsung), nomor, nilai, status, pertanggungjawaban.
- **BKU / BukuPembantu:** bendahara, tanggal, uraian, debit, kredit, saldo, referensi.
- **Pajak:** transaksi, jenis, tarif, nilai, status setor, bukti setor, bukti potong.
- **Utang:** pemasok, tagihan, nilai, jatuh tempo, pembayaran, sisa.
- **Piutang / Klaim:** debitur/penjamin, tagihan, status klaim, nilai diajukan/disetujui, penerimaan, sisa.
- **SP3B:** periode, pendapatan, belanja, status, nomor/tanggal SP2B.
- **DokumenAnggaran:** jenis (Penetapan/Pergeseran/Perubahan), nomor urut, tanggal, status, snapshot rincian, dokumen pembanding.
- **AturanCadanganKas:** tahun, sumber dana, metode, nilai/jumlah bulan, daftar rekening wajib, status, persetujuan.
- **RencanaPenarikan:** pagu jenis, triwulan, nilai.
- **PosisiDana:** tahun, sumber dana, saldo awal, pendapatan diterima, belanja dibayar, komitmen, cadangan minimum, dana tersedia.
- **JadwalPerubahan:** tahun, tanggal buka, tanggal tutup, dasar (surat/jadwal Pemda).
- **StagingImporRBA:** file sumber, baris, kode rekening, komponen, nilai, label penanggung jawab, item standar terpilih, unit terpilih, status.
- **PemetaanLabelUnit:** label teks lama, unit kerja.
- **Jurnal:** tanggal, periode, sumber transaksi, akun, debit, kredit, status posting.
- **Delegasi:** pejabat definitif, pelaksana, kewenangan, periode.
- **AuditLog:** pengguna, tindakan, objek, waktu, nilai lama/baru, alasan.

Struktur fisik, relasi, penguncian, dan konkurensi ditentukan pada tahap desain teknis.

## 12. Laporan dan Dashboard

### 12.1 Laporan anggaran

- RBA per unit, per sumber dana, dan konsolidasi RSUD.
- Pagu baseline, revisi, pergeseran masuk/keluar, perubahan RBA, ambang batas, dan pagu berlaku per jenis belanja.
- Realisasi, komitmen, tercadang, dan sisa per unit × jenis belanja × sumber dana.
- Perbandingan anggaran dengan realisasi.
- Daftar rincian di bawah, sama dengan, dan di atas standar (dengan status persetujuan).
- Daftar revisi rincian, pergeseran, dan usulan standar harga per status.
- Penggunaan ambang batas.
- Lampiran Rincian Anggaran Belanja (Penetapan, Pergeseran ke-n, Perubahan) dengan kolom Berkurang/Bertambah.
- Posisi dana: realisasi pendapatan vs belanja per sumber dana dan per bulan.
- Perbandingan anggaran antar tahun.

### 12.2 Laporan bendahara dan perpajakan

- BKU dan buku pembantu.
- SPJ fungsional dan administratif.
- Register dokumen pembayaran dan uang muka.
- Rekapitulasi pajak dipotong/dipungut, disetor, dan selisih.

### 12.3 Laporan utang, piutang, dan pengesahan

- Umur utang dan jadwal jatuh tempo.
- Umur piutang per debitur/penjamin dan status klaim.
- SP3B dan register SP2B.
- Ekspor data rekening Pemda untuk SIPD.

### 12.4 Laporan pengawasan dan audit

- Riwayat perubahan RBA, revisi rincian, dan pergeseran.
- Revisi dengan harga di atas standar beserta bukti dan persetujuan.
- Usulan standar harga yang disahkan meski ada item mirip, beserta alasan.
- Transaksi yang ditolak sistem.
- Log aktivitas pengguna, persetujuan, dan delegasi.
- Daftar persetujuan otomatis per perencana dan periode.
- Rekonsiliasi anggaran dengan penatausahaan.

### 12.5 Laporan keuangan

Laporan keuangan BLUD dan laporan manajemen sesuai standar, kebijakan akuntansi, dan format yang diwajibkan Pemda. Kandidat: Neraca, LRA, LO, LPE, LAK, CaLK, serta laporan lain yang dipersyaratkan. Daftar final dikunci setelah verifikasi regulasi dan format.

## 13. Kriteria Penerimaan dan Skenario UAT

### Standar harga

**UAT-01 — Impor standar harga.** *Given* file sesuai template, *when* diunggah, *then* sistem memvalidasi, menampilkan ringkasan berhasil/gagal/calon duplikat, dan menyimpan versi setelah dikonfirmasi.

**UAT-02 — Impor gagal sebagian.** *Given* file berisi baris valid dan tidak valid, *when* diimpor, *then* sistem melaporkan kesalahan per baris dan tidak mengaktifkan baris gagal (kebijakan pemrosesan baris valid ditetapkan pada K-9).

**UAT-03 — Pengecekan kecocokan.** *Given* standar "Kertas HVS A4 70 gr, rim" sudah ada, *when* pengusul mengusulkan "Kertas A4 70gsm, rim", *then* sistem menampilkan item yang ada sebagai kecocokan tinggi kepada pengusul dan perencana sebelum pengesahan.

**UAT-04 — Pengesahan item mirip.** *Given* usulan memiliki kecocokan tinggi, *when* perencana memilih "sahkan sebagai baru" tanpa alasan, *then* sistem menolak penyimpanan keputusan.

### Penyusunan RBA

**UAT-46 — Item baru di tengah tahun.** *Given* RBA definitif dan item "Reagen X" belum ada di daftar standar, *when* pengusul mengusulkannya dari formulir transaksi dan perencana mengesahkan, *then* item tersedia dengan penanda tambahan tengah tahun, pengusul diberi tahu, dan dapat menambah rincian melalui revisi rincian.

**UAT-47 — Impor Excel standar internal.** *Given* file berisi 100 baris (90 valid, 10 gagal validasi), *when* diunggah, *then* sistem menampilkan pratinjau per kategori kecocokan dan daftar kesalahan, dan hanya 90 baris valid yang dapat diajukan sebagai batch.

**UAT-48 — Keputusan batch.** *Given* batch berisi 80 baris tanpa kecocokan tinggi dan 10 baris kecocokan tinggi, *when* perencana memilih "sahkan semua", *then* hanya 80 baris yang disahkan dan 10 baris tetap menunggu keputusan per baris.

**UAT-05 — Item tanpa standar.** *Given* item tidak ada di daftar standar harga, *when* pengusul menyusun rincian, *then* item tidak dapat dipilih dan sistem menawarkan pengajuan usulan standar.

**UAT-06 — Harga di atas standar saat penyusunan.** *Given* standar Rp100.000, *when* diajukan Rp110.000, *then* sistem menolak meskipun ada lampiran.

**UAT-07 — Harga di bawah standar.** *Given* standar Rp100.000, *when* diajukan Rp90.000, *then* rincian lolos validasi harga.

**UAT-08 — Rekening tanpa pemetaan.** *Given* rekening BLUD belum dipetakan ke rekening Pemda, *when* dipakai dalam rincian, *then* sistem menolak.

### Revisi rincian

**UAT-09 — Revisi dalam standar.** *Given* RBA definitif, *when* pengusul memindahkan Rp5 juta dari rincian A ke rincian B (unit, jenis belanja, dan sumber dana sama, harga ≤ standar), *then* untuk Belanja Barang dan Jasa revisi berlaku saat disimpan dan pagu jenis belanja unit tidak berubah.

**UAT-49 — Revisi jenis belanja lain.** *Given* rincian Belanja Modal, *when* pengusul menyimpan revisi seimbang dengan harga ≤ standar, *then* revisi menunggu persetujuan perencana, rincian lama tetap berlaku, dan tidak ada persetujuan otomatis meskipun batas waktu terlampaui.

**UAT-10 — Revisi di atas standar tanpa bukti.** *Given* harga revisi Rp120.000 dengan standar Rp100.000, *when* tanpa bukti, *then* sistem menolak pengajuan.

**UAT-11 — Revisi di atas standar dengan bukti.** *Given* bukti terlampir, *when* diajukan, *then* revisi menunggu persetujuan perencana; transaksi tetap memakai rincian lama sampai disetujui.

**UAT-12 — Revisi lintas jenis belanja.** *Given* rincian asal Belanja Barang dan Jasa dan tujuan Belanja Modal, *when* direvisi, *then* sistem menolak dan mengarahkan ke perubahan RBA.

**UAT-13 — Revisi di bawah realisasi.** *Given* rincian Rp10 juta dengan realisasi + komitmen Rp8 juta, *when* direvisi menjadi Rp7 juta, *then* sistem menolak.

**UAT-31 — Indikator status pagu.** *Given* pagu jenis belanja unit Rp100 juta, *when* pengusul menaikkan satu rincian sebesar Rp5 juta tanpa menurunkan rincian lain, *then* indikator berubah menjadi "Pagu berubah: bertambah Rp5.000.000".

**UAT-32 — Simpan dengan pagu berubah.** *Given* kondisi UAT-31, *when* pengusul menyimpan, *then* tampil dialog persetujuan; jika memilih kirim, paket revisi berstatus menunggu pergeseran, rincian lama tetap berlaku, dan rincian dalam paket terkunci.

**UAT-33 — Lanjutkan edit.** *Given* permintaan pergeseran dari UAT-32 terkirim, *when* pengusul membuka sesi edit baru, *then* rincian yang terkunci tidak dapat diubah, rincian lain dapat diedit, dan perhitungan memakai pagu berlaku.

**UAT-34 — Keluar halaman.** *Given* ada perubahan belum tersimpan dengan status pagu berubah, *when* pengusul meninggalkan halaman, *then* tampil pemberitahuan bahwa perubahan memerlukan persetujuan dengan pilihan kirim, kembali, atau buang.

**UAT-35 — Penerapan paket.** *Given* pergeseran terkait paket revisi disahkan, *when* diterapkan, *then* pagu kedua unit dan seluruh rincian dalam paket berubah dalam satu transaksi; jika ditolak, paket gugur dan rincian terbuka kembali.

**UAT-37 — Pasangan lengkap.** *Given* Unit A mengajukan tambah Rp5 juta dan Unit B mengajukan kurang Rp5 juta pada jenis belanja dan sumber dana yang sama, *when* perencana memasangkan keduanya dan pengesahan selesai, *then* pagu A naik, pagu B turun, dan paket revisi kedua unit diterapkan dalam satu transaksi.

**UAT-38 — Pasangan satu-ke-banyak.** *Given* Unit A tambah Rp5 juta, Unit B kurang Rp3 juta, Unit C kurang Rp2 juta, *when* ketiganya dipasangkan, *then* pasangan dinyatakan lengkap dan dapat diajukan pengesahan.

**UAT-39 — Pasangan tidak sama.** *Given* Unit A tambah Rp5 juta dan hanya ada Unit B kurang Rp3 juta, *when* dipasangkan, *then* sistem menolak pengajuan pengesahan karena total tidak sama.

**UAT-40 — Batas waktu habis.** *Given* permintaan tambah tanpa pasangan lengkap, *when* 5 hari terlampaui, *then* sistem menyatakan ditolak otomatis, paket revisi gugur, rincian terbuka kembali, dan pengusul menerima notifikasi.

**UAT-41 — Permintaan kurang kedaluwarsa.** *Given* permintaan kurang dengan nilai tercadang, *when* batas waktu habis tanpa pasangan, *then* pencadangan dilepas dan sisa pagu unit kembali.

**UAT-42 — Hari kerja.** *Given* permintaan diajukan Kamis dan Jumat berikutnya adalah libur nasional, *when* batas waktu dihitung, *then* akhir pekan dan hari libur tidak dihitung.

**UAT-43 — Persetujuan otomatis.** *Given* pasangan lengkap dan perencana tidak merespons, *when* 5 hari kerja terlampaui dan validasi ulang lolos, *then* pasangan disetujui otomatis, pagu diterapkan atomik, audit log mencatat "disetujui otomatis oleh sistem", dan perencana serta pengusul diberi tahu.

**UAT-44 — Persetujuan otomatis gagal validasi.** *Given* kondisi UAT-43 tetapi sisa pagu salah satu unit tidak lagi mencukupi, *when* batas waktu tercapai, *then* persetujuan otomatis tidak dijalankan dan permintaan ditolak dengan alasan validasi.

**UAT-45 — Harga di atas standar tidak disetujui otomatis.** *Given* pasangan lengkap memuat rincian dengan harga di atas standar dan perencana tidak merespons, *when* 5 hari kerja terlampaui, *then* pasangan tetap menunggu keputusan perencana, pagu tidak berubah, dan pengingat terus dikirim.

**UAT-36 — Pemetaan rekening.** *Given* rekening BLUD tingkat Sub Rincian Objek, *when* dipetakan ke rekening Pemda tingkat Objek, *then* sistem menolak karena tingkat tidak setara.

### Pergeseran antarunit

**UAT-14 — Pencadangan.** *Given* unit asal memiliki sisa Rp50 juta, *when* pergeseran Rp30 juta diajukan, *then* sisa yang dapat dipakai unit asal menjadi Rp20 juta dan pagu berlaku kedua unit belum berubah.

**UAT-15 — Transaksi atas nilai tercadang.** *Given* kondisi UAT-14, *when* unit asal mengajukan transaksi Rp25 juta, *then* sistem menolak karena melampaui sisa.

**UAT-16 — Pengesahan.** *Given* pergeseran disahkan, *when* diterapkan, *then* pagu unit asal turun dan unit penerima naik sebesar nilai yang sama, rincian tujuan terbentuk, pencadangan dilepas, dan total pagu tidak berubah.

**UAT-17 — Penolakan.** *Given* pergeseran ditolak, *when* keputusan disimpan, *then* pencadangan dilepas.

**UAT-18 — Beda sumber dana.** *Given* rincian asal bersumber jasa layanan dan tujuan bersumber APBD, *when* diajukan, *then* sistem menolak.

### Perubahan RBA dan ambang batas

**UAT-19 — Ambang batas.** *Given* ambang batas 10% dan realisasi pendapatan BLUD di atas target, *when* penggunaan ambang batas melebihi kapasitas yang dihitung atau melebihi 10%, *then* sistem menolak.

### Penatausahaan

**UAT-20 — Harga transaksi berbeda.** *Given* harga rincian Rp100.000, *when* harga transaksi Rp95.000, *then* sistem menolak dan mengarahkan ke revisi rincian; setelah revisi berlaku, transaksi dapat diajukan.

**UAT-21 — Melampaui pagu.** *Given* sisa pagu jenis belanja tidak cukup, *when* transaksi diajukan, *then* sistem menolak.

**UAT-22 — Pajak.** *Given* transaksi jasa dengan PPh 23, *when* dibayar, *then* potongan tercatat di buku pembantu pajak dan berstatus belum disetor sampai bukti setor dicatat.

**UAT-23 — Reversal.** *Given* transaksi telah diposting, *when* dibatalkan, *then* sistem membuat transaksi reversal dan riwayat asli tetap ada.

### Utang dan piutang

**UAT-24 — Utang.** *Given* barang diterima dan belum dibayar, *when* dicatat, *then* utang terbentuk, pagu terikat sebagai komitmen, dan umur utang dihitung.

**UAT-25 — Klaim.** *Given* file klaim diimpor, *when* sebagian klaim berstatus *pending*, *then* piutang terbentuk per status dan muncul di laporan umur piutang.

### Pengesahan dan kontrol

**UAT-26 — SP3B.** *Given* periode berakhir, *when* SP3B disusun dan SP2B dicatat, *then* nilai sesuai transaksi terposting dalam rekening Pemda dan transaksi periode tersebut terkunci.

**UAT-27 — Pemisahan tugas.** *Given* pengguna mengajukan pergeseran, *when* pengguna yang sama mencoba mengesahkannya, *then* sistem menolak.

**UAT-28 — Delegasi.** *Given* pejabat mendelegasikan kewenangan kepada Plt, *when* Plt mengesahkan, *then* persetujuan mencatat pejabat definitif dan pelaksana; setelah masa delegasi habis, Plt tidak dapat mengesahkan.

**UAT-29 — Tutup periode.** *Given* periode ditutup, *when* transaksi bertanggal periode tersebut diinput, *then* sistem menolak.

**UAT-50 — Persetujuan pimpinan dari ponsel.** *Given* SP3B menunggu persetujuan Pimpinan BLUD, *when* pimpinan membuka notifikasi di ponsel, *then* ringkasan tampil dalam satu layar dan persetujuan selesai dalam ≤ 3 ketukan setelah konfirmasi PIN/biometrik; menolak tanpa catatan tidak dapat dilakukan.

**UAT-51 — Rincian tanpa unit.** *Given* rincian baru tanpa unit kerja, *when* disimpan, *then* sistem menolak.

**UAT-52 — Dokumen Pergeseran ke-n.** *Given* sejak Penetapan telah disahkan 12 revisi dan 3 pasangan pergeseran, *when* perencana membentuk Pergeseran I, *then* cetakan menampilkan nilai Penetapan, nilai Pergeseran I, dan kolom Berkurang/Bertambah per rincian, dengan total sesuai pagu berlaku.

**UAT-53 — Dana tidak cukup.** *Given* sisa pagu unit Rp 500 juta tetapi dana tersedia sumber jasa layanan hanya Rp 120 juta, *when* transaksi Rp 150 juta diajukan, *then* sistem menolak dan menampilkan posisi dana; setelah penerimaan pembayaran klaim Rp 200 juta diposting, transaksi yang sama dapat diajukan.

**UAT-56 — Validasi ulang saat persetujuan.** *Given* dua transaksi masing-masing Rp 80 juta diajukan saat dana tersedia Rp 120 juta, *when* keduanya disetujui berurutan, *then* yang pertama disetujui dan yang kedua ditolak karena dana tidak lagi cukup.

**UAT-58 — Cadangan untuk belanja wajib.** *Given* dana tersedia Rp 100 juta dan cadangan Rp 2 M untuk rekening listrik, *when* diajukan transaksi listrik Rp 600 juta dan transaksi ATK Rp 150 juta, *then* transaksi listrik diterima (dibatasi dana tersedia + cadangan) dan transaksi ATK ditolak.

**UAT-59 — Perubahan aturan cadangan.** *Given* aturan cadangan Rp 2 M, *when* pejabat keuangan mengubahnya menjadi kebutuhan 2 bulan, *then* nilai lama tetap berlaku sampai Pimpinan BLUD menyetujui, dan perubahan tercatat di jejak audit.

**UAT-60 — Rencana penarikan.** *Given* pagu Belanja Barang dan Jasa unit Farmasi Rp 20 M dengan rencana penarikan berjumlah Rp 18 M, *when* perencana membentuk dokumen Pergeseran I, *then* sistem menampilkan pagu tersebut dalam daftar yang harus dilengkapi dan dokumen belum dapat diajukan; transaksi belanja tidak terpengaruh.

**UAT-57 — Jadwal perubahan.** *Given* jadwal perubahan Pemda 1–31 Agustus, *when* perencana mengajukan perubahan RBA pada 5 September, *then* sistem menolak pengajuan; draf tetap tersimpan. Pembentukan dokumen Pergeseran ke-n pada tanggal yang sama tetap dapat dilakukan.

**UAT-54 — Impor RBA lama.** *Given* file Lampiran Rincian Anggaran Belanja dengan baris "Belanja Obat-Obatan 1 tahun × Rp 27,5 M", *when* diimpor, *then* baris masuk staging berstatus "perlu dirinci", tidak dapat diajukan sampai dipecah menjadi item standar harga yang totalnya Rp 27,5 M dan diberi unit.

**UAT-55 — Pemetaan banyak-ke-satu.** *Given* rekening BLUD 5.1.02.01.01.0037 dan 5.1.02.01.01.0024 dipetakan ke 5.1.02.99.99.9999, *when* cetakan agregat dibuat, *then* keduanya dijumlahkan dalam satu baris rekening Pemda.

**UAT-30 — Audit.** *Given* pergeseran disahkan, *when* auditor membuka detailnya, *then* tampil nilai sebelum/sesudah, alasan, dokumen, pengusul, verifikator, pengesah, dan waktu.

## 14. Matriks Keterlacakan

| Aturan | FR | UAT |
|---|---|---|
| 7.2 Rekening dan pemetaan | FR-002–005, FR-090 | UAT-08, UAT-12, UAT-36 |
| 7.3 Sumber dana | FR-006, FR-022, FR-039 | UAT-18 |
| 7.4 Standar harga | FR-013–021, FR-023, FR-103 | UAT-01–05, UAT-46 |
| 7.4a Impor usulan standar internal | FR-104–105 | UAT-47, UAT-48 |
| 7.5 Penyusunan RBA | FR-022–029 | UAT-05–08 |
| 7.6 Pengendalian pagu | FR-030, FR-040, FR-052, FR-054 | UAT-14, UAT-15, UAT-21 |
| 7.7 Revisi rincian | FR-031–038, FR-091–095, FR-106 | UAT-09–13, UAT-31–35, UAT-49 |
| 7.8 Pergeseran antarunit | FR-039–046 | UAT-14–18, UAT-30 |
| 7.8a Pasangan dan batas waktu | FR-096–102 | UAT-37–45 |
| 7.9 Perubahan RBA/ambang batas | FR-047–050 | UAT-12, UAT-19 |
| 7.10 Penatausahaan belanja | FR-051–060 | UAT-20, UAT-21, UAT-23 |
| 7.11 Utang | FR-065–068 | UAT-24 |
| 7.12 Piutang | FR-069–073 | UAT-25 |
| 7.13 SP3B/SP2B | FR-074–076 | UAT-26 |
| 7.14 Pajak | FR-061–064 | UAT-22 |
| 7.15 Persetujuan/delegasi | FR-008–011, FR-084–085, FR-107–109 | UAT-27, UAT-28, UAT-50 |
| 7.16 Unit kerja | FR-110 | UAT-51 |
| 7.17 Dokumen formal | FR-112–113, FR-118 | UAT-52, UAT-55 |
| 7.18 Ketersediaan dana | FR-114–116 | UAT-53, UAT-56 |
| 7.9 Jadwal perubahan | FR-119 | UAT-57 |
| 7.18a Cadangan kas minimum | FR-120–121 | UAT-58, UAT-59 |
| 7.17a Rencana penarikan | FR-122 | UAT-60 |
| 7.19 Impor RBA lama | FR-117 | UAT-54 |
| 7.2 Pemetaan banyak-ke-satu | FR-111 | UAT-55 |
| Tutup periode | FR-082 | UAT-29 |

## 15. Migrasi Data dan Go-Live

1. Inventarisasi sumber data: standar harga, RBA, rekening dan pemetaan, unit, sumber dana, utang, piutang/klaim berjalan, persediaan, aset, kas/bank, saldo pajak belum disetor, dan saldo akuntansi.
2. Tetapkan pemilik data dan template per sumber.
3. Petakan kode lama ke master aplikasi; jalankan pengecekan kecocokan untuk membersihkan duplikasi standar harga.
4. Bersihkan satuan yang tidak konsisten dan data tanpa kode.
5. Impor percobaan di lingkungan pengujian.
6. Rekonsiliasi jumlah baris, total nilai, pagu, dan saldo.
7. Pemilik data memvalidasi dan menyetujui hasil.
8. Tetapkan tanggal cutover, saldo awal, periode terbuka, dan pembekuan sistem lama. Disarankan go-live pada awal tahun anggaran atau awal periode SP3B.
9. Uji pemulihan backup dan simulasi operasional.
10. Go-live dengan pemantauan dan prosedur penanganan masalah.

## 16. Risiko dan Mitigasi

| Risiko | Mitigasi |
|---|---|
| Revisi rincian tanpa persetujuan disalahgunakan | Audit log, laporan revisi per unit, opsi konfigurasi wajib verifikasi |
| Daftar standar harga tidak lengkap sehingga unit terhambat | Alur usulan standar yang cepat dengan pengecekan kecocokan otomatis |
| Penambahan item tengah tahun dipakai untuk menghindari standar yang sudah ada | Pengecekan kecocokan wajib, alasan wajib untuk kecocokan tinggi, laporan item tambahan tengah tahun per unit |
| Duplikasi item standar | Pengecekan kecocokan saat usulan dan impor; alasan wajib saat mengesahkan item mirip |
| Pemetaan rekening salah | Pemetaan berversi, validasi kelengkapan, uji SP3B dan ekspor sebelum go-live |
| Dana pergeseran terpakai sebelum disahkan | Pencadangan sejak pengajuan dan validasi ulang saat pengesahan |
| Pergeseran tidak seimbang | Transaksi atomik dan rekonsiliasi total |
| Ambang batas dihitung tidak sesuai ketentuan | Konfirmasi rumus dengan BPKAD dan uji skenario |
| Pajak tidak disetor | Status setor, pengingat, rekonsiliasi pajak |
| Data klaim/tagihan tidak konsisten dengan SIMRS | Template baku, rekonsiliasi total per periode |
| Pengesahan tidak sesuai kewenangan | Matriks kewenangan dan delegasi terdokumentasi |
| Aturan akuntansi belum final | Validasi kebijakan dan uji jurnal sebelum Fase 3 |
| Persetujuan otomatis menjadi kebiasaan atau dipersoalkan auditor | Pengingat dan laporan persetujuan otomatis, validasi ulang sebelum diterapkan, harga di atas standar dikecualikan; keabsahan ditetapkan pada D-23 dan dicantumkan dalam SOP pergeseran |
| Belanja tertahan di awal tahun atau saat pembayaran klaim BPJS terlambat | Saldo kas awal/SiLPA ikut dihitung, cadangan minimum untuk belanja wajib, dashboard posisi dana dan daftar transaksi tertahan untuk prioritisasi pimpinan |
| Beban merinci belanja gelondongan (obat, BMHP, jasa) menjadi item saat migrasi dan penyusunan | Impor Excel standar harga internal (7.4a), kandidat item otomatis pada impor RBA (7.19), migrasi bertahap per rekening, pendampingan unit |
| Ruang lingkup terlalu besar | MVP Fase 1–2, evaluasi aplikasi persediaan/aset yang sudah ada |

## 17. Keputusan

### 17.1 Keputusan yang telah ditetapkan (v1.1)

| No | Keputusan |
|---|---|
| D-1 | Jika harga/volume riil berbeda dari rincian, rincian direvisi sebelum transaksi diajukan, selama dalam jenis belanja dan unit yang sama |
| D-2 | Perubahan anggaran dipisah menjadi revisi rincian, pergeseran antarunit, dan perubahan RBA |
| D-3 | Perubahan RBA dan ambang batas BLUD masuk lingkup |
| D-4 | Sumber dana menjadi dimensi anggaran |
| D-5 | Pendapatan dan belanja BLUD disahkan melalui SP3B/SP2B |
| D-6 | Nilai pergeseran antarunit dicadangkan sejak pengajuan |
| D-7 | "Jenis belanja sama" = tingkat jenis; tersedia pemetaan rekening Pemda ke rekening BLUD |
| D-8 | Rincian baru dan pola satu-ke-banyak/banyak-ke-satu diatur dalam revisi rincian |
| D-9 | Tidak boleh ada item tanpa standar harga; usulan standar baru dicek kecocokannya sebelum disahkan perencana |
| D-10 | Harga standar yang sudah disahkan tidak berubah di tengah tahun; harga di atas standar melalui revisi rincian dengan bukti dan persetujuan perencana (diperbarui oleh D-24) |
| D-11 | Pengendalian pagu di tingkat jenis belanja |
| D-12 | Penatausahaan bendahara, utang jangka pendek, dan piutang operasional masuk lingkup |
| D-13 | Impor tagihan dan klaim dari file |
| D-14 | Ekspor untuk SIPD |
| D-15 | Delegasi/Plt, notifikasi, penomoran, cetakan resmi, dan TTE |
| D-16 | Kesamaan jenis belanja dievaluasi pada rekening Pemda hasil pemetaan; struktur kodefikasi mengikuti pola Permendagri 90/2019 dengan fitur pemetaan |
| D-17 | Edit rincian dicek saat menyimpan; jika pagu jenis belanja berubah, dikirim sebagai permintaan pergeseran antarunit yang memerlukan persetujuan, dengan opsi melanjutkan edit dan peringatan saat meninggalkan halaman |
| D-18 | Utang yang belum dibayar menjadi komitmen yang mengurangi sisa pagu |
| D-19 | Kenaikan pagu wajib diimbangi penurunan di unit lain dan sebaliknya; tanpa pasangan dalam maksimal 5 hari kerja, permintaan dinyatakan ditolak |
| D-20 | Batas waktu dihitung dalam hari kerja |
| D-21 | Pasangan lengkap dinotifikasikan ke perencana; tanpa respons 5 hari kerja, disetujui otomatis |
| D-22 | Pasangan yang memuat harga di atas standar tidak boleh disetujui otomatis; wajib ada persetujuan perencana |
| D-23 | Persetujuan otomatis dapat diterima sebagai pengesahan yang sah |
| D-24 | Item standar harga internal baru dapat ditambahkan di tengah tahun sesuai kebutuhan, termasuk saat akan belanja |
| D-25 | Usulan standar harga internal dapat diimpor dari Excel |
| D-26 | Revisi rincian seimbang berlaku langsung hanya untuk Belanja Barang dan Jasa; jenis belanja lain wajib disetujui |
| D-27 | Persetujuan yang sampai ke Pimpinan BLUD memakai tampilan sederhana *mobile-first* |
| D-28 | Seluruh rincian, termasuk belanja yang selama ini gelondongan, wajib dirinci per item standar harga; tidak ada tipe rincian paket |
| D-29 | Unit kerja/penanggung jawab menjadi master dan wajib diisi per rincian; pagu dan pergeseran antarunit tetap per unit |
| D-30 | Dokumen formal Penetapan, Pergeseran ke-n, dan Perubahan dengan cetakan Berkurang/Bertambah |
| D-31 | Impor RBA format lama masuk lingkup Fase 1 |
| D-32 | Tidak memakai anggaran kas; belanja dikendalikan oleh dana tersedia dari realisasi pendapatan (selain sisa pagu) |
| D-33 | Dokumen Pergeseran ke-n dapat dibentuk sewaktu-waktu; Perubahan hanya dalam jadwal Pemda |
| D-34 | Cadangan kas minimum diatur melalui pengaturan per tahun dan sumber dana, dengan daftar rekening wajib dan persetujuan Pimpinan BLUD |
| D-35 | Piutang yang belum dibayar, termasuk klaim BPJS terverifikasi, tidak dihitung sebagai dana tersedia |
| D-36 | Lampiran rencana penarikan dana disediakan pada dokumen DPA/DPPA BLUD dan tidak mengikat |

### 17.2 Keputusan yang masih terbuka

| No | Pertanyaan | Dampak |
|---|---|---|
| K-3 | Prioritas pembanding jika item ada di standar Pemda dan standar internal | Validasi harga |
| K-4 | Nama Pemda dan regulasi daerah acuan | Seluruh aturan |
| K-5 | Pejabat yang mengajukan, memverifikasi, dan mengesahkan setiap jenis dokumen; apakah kedua unit menyetujui pergeseran antarunit | Matriks kewenangan |
| K-6 | Rumus, pengesah, dan pelaporan ambang batas | Modul ambang batas |
| K-7 | Istilah dan format dokumen pembayaran BLUD sesuai Perkada | Penatausahaan |
| K-8 | Sumber anggaran pembayaran utang yang terbawa ke tahun berikutnya | Pengendalian pagu tahun berikutnya |
| K-9 | Apakah baris valid pada impor gagal sebagian boleh diproses | Impor |
| K-10 | Dokumen bukti harga pasar yang diterima | Revisi di atas standar |
| K-11 | Batas nilai/frekuensi pergeseran yang dilarang | Pergeseran |
| K-12 | Periode SP3B | SP3B |
| K-13 | Format Excel resmi standar harga Pemda, template standar internal, dan template klaim/tagihan | Impor |
| K-14 | Kebijakan akuntansi, bagan akun, aturan jurnal otomatis | Fase 3 |
| K-15 | Apakah persediaan dan aset sudah dikelola aplikasi lain | Lingkup Fase 3 |
| K-16 | Format laporan wajib Pemda dan format ekspor SIPD | Pelaporan |
| K-17 | Infrastruktur, autentikasi, RPO/RTO, retensi | NFR |
| K-18 | Perkiraan volume transaksi, lampiran, dan target performa | NFR |
| K-25 | Apakah persetujuan otomatis 5 hari kerja untuk pasangan pergeseran antarunit juga dibatasi hanya untuk Belanja Barang dan Jasa, agar konsisten dengan D-26? | Persetujuan otomatis |
| K-26 | Daftar dokumen dan batas nilai yang wajib sampai ke Pimpinan BLUD (bagian dari K-5) | Kotak masuk pimpinan |
| K-27 | Apakah persetujuan pimpinan dari ponsel cukup dengan konfirmasi PIN/biometrik, atau dokumen tertentu (misalnya SP3B) tetap memerlukan TTE | Keamanan/kepatuhan |
| K-30 | Daftar final unit kerja/penanggung jawab dan hierarkinya | Master unit |

## 18. Regulasi dan Kepatuhan

Daftar berikut adalah titik awal kajian dan **bukan pengganti telaah hukum/akuntansi terhadap ketentuan terbaru serta peraturan daerah yang spesifik**:

- [Permendagri Nomor 79 Tahun 2018 tentang Badan Layanan Umum Daerah](https://peraturan.bpk.go.id/Details/111301/permendagri-no-79-tahun-2018) — fleksibilitas BLUD, RBA, ambang batas, pengesahan pendapatan dan belanja BLUD.
- [PP Nomor 12 Tahun 2019 tentang Pengelolaan Keuangan Daerah](https://peraturan.bpk.go.id/Details/103888/pp-no-%2012-tahun-2019).
- [Permendagri Nomor 77 Tahun 2020 tentang Pedoman Teknis Pengelolaan Keuangan Daerah](https://peraturan.bpk.go.id/details/162792/permendagri-no-77-tahun-2020) — penatausahaan, pergeseran, dan pertanggungjawaban.
- Permendagri Nomor 90 Tahun 2019 tentang Klasifikasi, Kodefikasi, dan Nomenklatur Perencanaan Pembangunan dan Keuangan Daerah, beserta pemutakhirannya — dasar tingkat "jenis belanja" dan pemetaan rekening.
- Perpres Nomor 33 Tahun 2020 tentang Standar Harga Satuan Regional — acuan standar harga.
- Ketentuan perpajakan yang berlaku untuk pemotongan/pemungutan oleh instansi pemerintah.
- [Standar Akuntansi Pemerintahan — KSAP](https://www.ksap.org/sap/standar-akuntansi-pemerintahan/) dan [Buletin Teknis SAP](https://www.ksap.org/sap/buletin-teknis-sap/).
- Peraturan Kepala Daerah tentang pengelolaan keuangan BLUD, standar harga, dan kebijakan akuntansi Pemda.

Sebelum go-live, tim harus memverifikasi status keberlakuan dan perubahan terbaru seluruh regulasi di atas bersama BPKAD dan bagian hukum.

## 19. Ketergantungan Antarfase

- Pemetaan rekening (Fase 1) wajib selesai sebelum SP3B dan ekspor SIPD.
- Rumus sisa pagu (termasuk utang sebagai komitmen) diterapkan sejak Fase 1, meskipun modul utang baru aktif di Fase 2.
- Kebijakan akuntansi (K-14) wajib final sebelum Fase 3.
- Daftar standar harga internal (termasuk obat dan BMHP) dan master unit wajib tersedia sebelum impor RBA lama dapat diselesaikan.

## 20. Rekomendasi Tahapan Implementasi

### Fase 1 — Fondasi dan anggaran (MVP bagian 1)

- Master data, rekening, pemetaan, sumber dana, hak akses, matriks kewenangan, delegasi.
- Impor standar harga Pemda, usulan standar internal, pengecekan kecocokan.
- Master unit kerja, penyusunan dan penetapan RBA, pembentukan pagu jenis belanja, jadwal perubahan.
- Impor RBA format lama (migrasi).
- Dokumen formal Penetapan/Pergeseran ke-n/Perubahan dan cetakannya.
- Revisi rincian, pergeseran antarunit dengan pencadangan, perubahan RBA.
- Dashboard dan laporan pagu, revisi, dan pergeseran.

### Fase 2 — Penatausahaan, bendahara, dan pengesahan (MVP bagian 2)

- Transaksi pendapatan dan belanja dengan validasi anggaran, harga, dan ketersediaan dana; dashboard posisi dana.
- Dokumen pembayaran, BKU, buku pembantu, SPJ.
- Perpajakan.
- Kas dan bank.
- Utang jangka pendek, piutang operasional, impor klaim/tagihan.
- Ambang batas.
- SP3B/SP2B.

### Fase 3 — Akuntansi dan pelaporan

- Buku besar, jurnal otomatis, penutupan periode.
- Persediaan dan aset (penuh atau impor, sesuai K-15).
- Penyisihan/penghapusan piutang.
- Laporan keuangan, laporan manajemen, ekspor SIPD.

### Fase 4 — Integrasi dan optimalisasi

- Integrasi API (SIPD, SIMRS, BPJS, bank, TTE) sesuai prioritas dan kesiapan.
- Penyempurnaan dashboard dan analitik.
- Optimasi performa dan kontrol audit lanjutan.

## 21. Definisi Selesai (Definition of Done)

Fitur dianggap siap produksi apabila:

- Aturan bisnis disetujui pemilik proses dan pemangku kepentingan terkait.
- Kriteria penerimaan dan UAT yang terlacak pada matriks (bagian 14) lulus.
- Hak akses, pemisahan tugas, dan delegasi telah diuji.
- Jejak audit dan bukti persetujuan tersedia.
- Laporan dan hasil rekonsiliasi diverifikasi.
- Migrasi data disetujui pemilik data.
- Backup dan pemulihan telah diuji.
- Dokumentasi pengguna dan administrator tersedia.
- Prosedur dukungan dan penanganan insiden disepakati.
- Tidak ada cacat kritis yang belum diselesaikan.

---

**Akhir dokumen — PRD Aplikasi Keuangan BLUD RSUD, versi 1.9 (draf).**
