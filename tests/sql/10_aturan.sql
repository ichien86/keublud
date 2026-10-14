-- Uji aturan yang dijaga database. Setiap baris keluaran diawali PASS atau FAIL.
\pset tuples_only on
\pset format unaligned

-- ===== Kodefikasi & pemetaan =====
SELECT harap(fn_kode_jenis(rek('BLUD','5.1.02.01.01.0037')) = '5.1.02', 'jenis_id terisi otomatis dari leluhur tingkat 3');

SELECT harap_galat(
  $$INSERT INTO pemetaan_rekening (rekening_blud_id, rekening_pemda_id, berlaku)
    VALUES (rek('BLUD','5.1.02.01.01'), rek('PEMDA','5.1.02.99.99.9999'), '[2027-01-01,)')$$,
  'PEMETAAN_TINGKAT_TIDAK_SETARA', 'UAT-36 pemetaan tingkat tidak setara ditolak');

SELECT harap_galat(
  $$INSERT INTO pemetaan_rekening (rekening_blud_id, rekening_pemda_id, berlaku)
    VALUES (rek('BLUD','5.1.02.01.01.0024'), rek('PEMDA','5.1.02.99.99.9999'), '[2026-06-01,)')$$,
  'conflicting key value', 'pemetaan tumpang tindih untuk rekening yang sama ditolak');

SELECT harap_galat(
  $$UPDATE pemetaan_rekening SET rekening_pemda_id = rek('PEMDA','5.2.02.99.99.9999')
    WHERE rekening_blud_id = rek('BLUD','5.1.02.01.01.0037')$$,
  'PEMETAAN_BEDA_JENIS', 'pemetaan lintas jenis belanja ditolak');

SELECT harap((SELECT count(*) FROM pemetaan_rekening WHERE rekening_pemda_id = rek('PEMDA','5.1.02.99.99.9999')) = 2,
  'UAT-55 pemetaan banyak-ke-satu diizinkan');

-- ===== Pagu =====
SELECT harap((SELECT pagu_berlaku FROM pagu_jenis WHERE id = pagu('FAR','5.1.02')) = 60000000, 'pagu Farmasi BBJ = 60 jt');
SELECT harap(NOT EXISTS (SELECT 1 FROM fn_pagu_rekonsiliasi(2026::smallint)), 'rekonsiliasi pagu awal bersih');

SELECT harap_galat($$UPDATE pagu_jenis SET pagu_berlaku = pagu_berlaku + 1 WHERE id = pagu('FAR','5.1.02')$$,
  'PAGU_HANYA_LEWAT_LEDGER', 'agregat pagu tidak dapat diubah langsung');

SELECT harap_galat($$UPDATE pagu_mutasi SET nilai = 1 WHERE id = 1$$, 'append-only', 'ledger pagu append-only');

SELECT harap_galat($$SELECT fn_pagu_catat(pagu('FAR','5.1.02'), 'KOMITMEN', 60000001, 'TRANSAKSI', 99)$$,
  'pagu_sisa_tidak_negatif', 'UAT-21 komitmen melebihi sisa pagu ditolak');

-- UAT-14/15: pencadangan mengurangi sisa
BEGIN;
SELECT fn_pagu_catat(pagu('FAR','5.1.02'), 'TERCADANG', 30000000, 'PERMINTAAN_KURANG', 1);
SELECT harap((SELECT sisa FROM pagu_jenis WHERE id = pagu('FAR','5.1.02')) = 30000000, 'UAT-14 pencadangan mengurangi sisa');
SELECT harap_galat($$SELECT fn_pagu_catat(pagu('FAR','5.1.02'), 'KOMITMEN', 35000000, 'TRANSAKSI', 98)$$,
  'pagu_sisa_tidak_negatif', 'UAT-15 transaksi atas nilai tercadang ditolak');
ROLLBACK;

-- ===== Rincian =====
SELECT harap_galat($$UPDATE rba_rincian SET volume = 400 WHERE id = 1$$,
  'RBA_DEFINITIF_TERKUNCI', 'rincian RBA definitif tidak dapat diedit langsung');

BEGIN;
SELECT set_config('app.izin_ubah_rincian', 'on', true);
SELECT harap_galat($$UPDATE rba_rincian SET harga = 120000 WHERE id = 1$$,
  'rincian_harga_sah', 'harga di atas standar tanpa persetujuan ditolak');
UPDATE rba_rincian SET harga = 120000, dasar_harga = 'DISETUJUI_DI_ATAS_STANDAR', volume = 400 WHERE id = 1;
SELECT harap((SELECT nilai FROM rba_rincian WHERE id = 1) = 48000000, 'harga di atas standar dengan persetujuan diterima');
ROLLBACK;

BEGIN;
SELECT set_config('app.izin_ubah_rincian', 'on', true);
SELECT set_config('app.izin_ubah_pagu', 'on', true);
UPDATE rba_rincian SET komitmen = 8000000 WHERE id = 2;   -- realisasi+komitmen 8 jt pada rincian 10 jt
SELECT harap_galat($$UPDATE rba_rincian SET volume = 140 WHERE id = 2$$,
  'rincian_tidak_di_bawah_pemakaian', 'UAT-13 revisi di bawah realisasi+komitmen ditolak');
ROLLBACK;

SELECT harap_galat(
  $$INSERT INTO rba (tahun, versi, jenis_versi, status, dibuat_oleh) VALUES (2027,1,'AWAL','DRAF',1);
    INSERT INTO standar_harga_item (tahun,sumber,impor_id,kode_barang,uraian,satuan,harga,berlaku_mulai)
      VALUES (2027,'PEMDA',1,'X1','Pena','buah',3000,'2026-01-01');
    INSERT INTO rba_rincian (rba_id, unit_id, sumber_dana_id, rekening_blud_id, rekening_pemda_id, jenis_id, item_id, uraian, volume, satuan, harga_standar, harga)
      VALUES (currval('rba_id_seq'), 1, 1, rek('BLUD','5.1.02.01.01.0024'), rek('PEMDA','5.1.02.99.99.9999'), rek('PEMDA','5.1.02'),
              currval('standar_harga_item_id_seq'), 'Pena', 10, 'buah', 3000, 3300)$$,
  'HARGA_DI_ATAS_STANDAR', 'UAT-06 harga di atas standar saat penyusunan ditolak');

SELECT harap_galat(
  $$SELECT set_config('app.izin_ubah_rincian','on',true);
    INSERT INTO rba_rincian (rba_id, sumber_dana_id, rekening_blud_id, rekening_pemda_id, jenis_id, item_id, uraian, volume, satuan, harga_standar, harga)
    VALUES (1, 1, rek('BLUD','5.1.02.01.01.0024'), rek('PEMDA','5.1.02.99.99.9999'), rek('PEMDA','5.1.02'), 1, 'x', 1, 'rim', 50000, 50000)$$,
  'unit_id', 'UAT-51 rincian tanpa unit ditolak');

SELECT harap_galat(
  $$SELECT set_config('app.izin_ubah_rincian','on',true);
    INSERT INTO rba_rincian (rba_id, unit_id, sumber_dana_id, rekening_blud_id, rekening_pemda_id, jenis_id, item_id, uraian, volume, satuan, harga_standar, harga)
    VALUES (1, 1, 1, rek('BLUD','5.1.02.01.01.0024'), rek('PEMDA','5.1.02.99.99.9999'), rek('PEMDA','5.2.02'), 1, 'x', 1, 'rim', 50000, 50000)$$,
  'RINCIAN_JENIS_TIDAK_SESUAI', 'rincian dengan jenis yang tidak sesuai rekening Pemda ditolak');

SELECT harap_galat($$DELETE FROM rba_rincian WHERE id = 1$$, 'append-only', 'rincian tidak dapat dihapus fisik');

-- ===== Standar harga =====
SELECT harap_galat($$UPDATE standar_harga_item SET harga = 55000 WHERE id = 1$$,
  'STANDAR_HARGA_TERKUNCI', 'harga standar terkunci setelah RBA definitif');

INSERT INTO impor_batch (jenis, nama_file, path_file, sha256, dibuat_oleh) VALUES ('USULAN_STANDAR','-','-','-',1);
INSERT INTO usulan_standar_harga (tahun, uraian, satuan, harga, status, diajukan_oleh) VALUES (2026,'Reagen X','kit',750000,'DISAHKAN',1);
INSERT INTO standar_harga_item (tahun, sumber, usulan_id, uraian, satuan, harga, berlaku_mulai, tambahan_tengah_tahun)
VALUES (2026, 'BLUD', currval('usulan_standar_harga_id_seq'), 'Reagen X', 'kit', 750000, current_date, true);
SELECT harap((SELECT tambahan_tengah_tahun FROM standar_harga_item WHERE uraian = 'Reagen X'),
  'UAT-46 item baru dapat ditambahkan di tengah tahun');

SELECT harap(
  (SELECT similarity(uraian_norm, 'kertas a4 70 gram') FROM standar_harga_item WHERE id = 1) > 0.3,
  'pencarian trigram menemukan item mirip');

-- ===== Pasangan pergeseran =====
INSERT INTO permintaan_pergeseran (arah, pagu_jenis_id, nilai, diajukan_oleh, batas_pasangan)
VALUES ('TAMBAH', pagu('FAR','5.1.02'), 5000000, 1, now() + interval '7 days'),
       ('KURANG', pagu('LAB','5.1.02'), 3000000, 2, now() + interval '7 days'),
       ('KURANG', pagu('LAB','5.2.02'), 5000000, 2, now() + interval '7 days'),
       ('KURANG', pagu('FAR','5.1.02'), 1000000, 1, now() + interval '7 days');

SELECT harap_galat(
  $$INSERT INTO pasangan_pergeseran (tahun, jenis_id, sumber_dana_id, total_tambah, total_kurang, dibentuk_oleh)
    VALUES (2026, rek('PEMDA','5.1.02'), 1, 5000000, 3000000, 1)$$,
  'pasangan_seimbang', 'UAT-39 pasangan dengan total tidak sama ditolak');

SELECT harap_galat(
  $$INSERT INTO pasangan_pergeseran (tahun, jenis_id, sumber_dana_id, total_tambah, total_kurang, dibentuk_oleh)
    VALUES (2026, rek('PEMDA','5.1.02'), 1, 5000000, 5000000, 1);
    INSERT INTO pasangan_anggota (pasangan_id, permintaan_id) VALUES (currval('pasangan_pergeseran_id_seq'), 1), (currval('pasangan_pergeseran_id_seq'), 3);
    SET CONSTRAINTS ALL IMMEDIATE$$,
  'PASANGAN_TIDAK_SEJENIS', 'pasangan lintas jenis belanja ditolak');

SELECT harap_galat(
  $$INSERT INTO pasangan_pergeseran (tahun, jenis_id, sumber_dana_id, total_tambah, total_kurang, dibentuk_oleh)
    VALUES (2026, rek('PEMDA','5.1.02'), 1, 5000000, 1000000, 1);
    SET CONSTRAINTS ALL IMMEDIATE$$,
  'pasangan_seimbang', 'kepala pasangan tidak seimbang ditolak');

SELECT harap_galat(
  $$INSERT INTO pasangan_pergeseran (tahun, jenis_id, sumber_dana_id, total_tambah, total_kurang, dibentuk_oleh)
    VALUES (2026, rek('PEMDA','5.1.02'), 1, 3000000, 3000000, 1);
    INSERT INTO pasangan_anggota (pasangan_id, permintaan_id) VALUES (currval('pasangan_pergeseran_id_seq'), 1);
    SET CONSTRAINTS ALL IMMEDIATE$$,
  'PASANGAN_TOTAL_TIDAK_SESUAI', 'anggota tidak sesuai total kepala ditolak saat commit');

BEGIN;
INSERT INTO permintaan_pergeseran (arah, pagu_jenis_id, nilai, diajukan_oleh, batas_pasangan)
VALUES ('KURANG', pagu('LAB','5.1.02'), 2000000, 2, now() + interval '7 days');
INSERT INTO pasangan_pergeseran (tahun, jenis_id, sumber_dana_id, total_tambah, total_kurang, dibentuk_oleh)
VALUES (2026, rek('PEMDA','5.1.02'), 1, 5000000, 5000000, 1);
INSERT INTO pasangan_anggota (pasangan_id, permintaan_id)
VALUES (currval('pasangan_pergeseran_id_seq'), 1), (currval('pasangan_pergeseran_id_seq'), 2),
       (currval('pasangan_pergeseran_id_seq'), currval('permintaan_pergeseran_id_seq'));
SET CONSTRAINTS ALL IMMEDIATE;
SELECT harap(true, 'UAT-38 pasangan satu-ke-banyak seimbang diterima');
SET CONSTRAINTS ALL DEFERRED;
SELECT harap_galat(
  $$INSERT INTO pasangan_pergeseran (tahun, jenis_id, sumber_dana_id, total_tambah, total_kurang, dibentuk_oleh)
    VALUES (2026, rek('PEMDA','5.1.02'), 1, 3000000, 3000000, 1);
    INSERT INTO pasangan_anggota (pasangan_id, permintaan_id) VALUES (currval('pasangan_pergeseran_id_seq'), 2)$$,
  'anggota_unik_aktif', 'satu permintaan tidak dapat masuk dua pasangan aktif');
ROLLBACK;

SELECT harap_galat(
  $$INSERT INTO pasangan_pergeseran (tahun, jenis_id, sumber_dana_id, total_tambah, total_kurang, dibentuk_oleh)
    VALUES (2026, rek('PEMDA','5.1.02'), 1, 1000000, 1000000, 1);
    INSERT INTO permintaan_pergeseran (arah, pagu_jenis_id, nilai, diajukan_oleh, batas_pasangan)
    VALUES ('TAMBAH', pagu('FAR','5.1.02'), 1000000, 1, now() + interval '7 days');
    INSERT INTO pasangan_anggota (pasangan_id, permintaan_id)
    VALUES (currval('pasangan_pergeseran_id_seq'), 4), (currval('pasangan_pergeseran_id_seq'), currval('permintaan_pergeseran_id_seq'));
    SET CONSTRAINTS ALL IMMEDIATE$$,
  'PASANGAN_UNIT_SAMA', 'unit yang sama di sisi tambah dan kurang ditolak');

SELECT harap_galat(
  $$INSERT INTO pasangan_pergeseran (tahun, jenis_id, sumber_dana_id, total_tambah, total_kurang, dibentuk_oleh,
       memuat_di_atas_standar, status, lengkap_pada, batas_pengesahan)
    VALUES (2026, rek('PEMDA','5.1.02'), 1, 1, 1, 1, true, 'DISETUJUI_OTOMATIS', now(), now())$$,
  'pasangan_otomatis_tanpa_di_atas_standar', 'UAT-45 persetujuan otomatis untuk harga di atas standar ditolak database');

-- ===== Workflow =====
INSERT INTO workflow_definisi (objek_tipe, versi) VALUES ('UJI', 1);
INSERT INTO persetujuan (objek_tipe, objek_id, definisi_id, diajukan_oleh) VALUES ('UJI', 1, currval('workflow_definisi_id_seq'), 1);
SELECT harap_galat(
  $$INSERT INTO persetujuan_aksi (persetujuan_id, aksi, pelaku_id, catatan) VALUES (currval('persetujuan_id_seq'), 'TOLAK', 2, 'kurang')$$,
  'persetujuan_aksi_check', 'UAT-50 menolak tanpa catatan memadai ditolak');

-- ===== Audit =====
SELECT harap((SELECT count(*) FROM audit_log WHERE tabel = 'pagu_jenis') > 0, 'perubahan pagu tercatat di audit log');
SELECT harap(fn_audit_verifikasi() IS NULL, 'rantai hash audit utuh');
SELECT harap_galat($$DELETE FROM audit_log WHERE id = 1$$, 'append-only', 'audit log tidak dapat dihapus');

-- ===== Rencana penarikan (tidak mengikat) =====
SELECT harap((SELECT count(*) FROM fn_rencana_penarikan_belum_sesuai(2026::smallint)) = 3,
  'UAT-60 pagu tanpa rencana penarikan terdeteksi');

-- ===== Alur penuh pergeseran antarunit (urutan mutasi sama dengan PasanganService::sahkan) =====
BEGIN;
-- Farmasi menambah 3 jt pada rincian 2 (Kertas), Lab mengurangi 3 jt pada rincian 3 (Kertas Lab).
INSERT INTO permintaan_pergeseran (arah, pagu_jenis_id, nilai, diajukan_oleh, batas_pasangan)
VALUES ('TAMBAH', pagu('FAR','5.1.02'), 3000000, 1, now() + interval '7 days'),
       ('KURANG', pagu('LAB','5.1.02'), 3000000, 2, now() + interval '7 days');
SELECT fn_pagu_catat(pagu('LAB','5.1.02'), 'TERCADANG', 3000000, 'PERMINTAAN_KURANG', currval('permintaan_pergeseran_id_seq'));
SELECT harap((SELECT sisa FROM pagu_jenis WHERE id = pagu('LAB','5.1.02')) = 27000000, 'alur: pencadangan unit asal');
INSERT INTO pasangan_pergeseran (tahun, jenis_id, sumber_dana_id, total_tambah, total_kurang, dibentuk_oleh, status, lengkap_pada, batas_pengesahan)
VALUES (2026, rek('PEMDA','5.1.02'), 1, 3000000, 3000000, 9, 'MENUNGGU_PERENCANA', now(), now() + interval '7 days');
INSERT INTO pasangan_anggota (pasangan_id, permintaan_id, persetujuan_unit)
VALUES (currval('pasangan_pergeseran_id_seq'), currval('permintaan_pergeseran_id_seq') - 1, 'SETUJU'),
       (currval('pasangan_pergeseran_id_seq'), currval('permintaan_pergeseran_id_seq'), 'SETUJU');
-- pengesahan
SELECT fn_pagu_catat(pagu('LAB','5.1.02'), 'TERCADANG', -3000000, 'PASANGAN', currval('pasangan_pergeseran_id_seq'));
SELECT fn_pagu_catat(pagu('LAB','5.1.02'), 'PAGU', -3000000, 'PASANGAN', currval('pasangan_pergeseran_id_seq'));
SELECT fn_pagu_catat(pagu('FAR','5.1.02'), 'PAGU', 3000000, 'PASANGAN', currval('pasangan_pergeseran_id_seq'));
SELECT set_config('app.izin_ubah_rincian', 'on', true);
UPDATE rba_rincian SET volume = 260 WHERE id = 2;   -- 10 jt -> 13 jt
UPDATE rba_rincian SET volume = 540 WHERE id = 3;   -- 30 jt -> 27 jt
UPDATE pasangan_pergeseran SET status = 'DISAHKAN', diputuskan_oleh = 9, diputuskan_pada = now() WHERE id = currval('pasangan_pergeseran_id_seq');
SET CONSTRAINTS ALL IMMEDIATE;
SELECT harap((SELECT sum(pagu_berlaku) FROM pagu_jenis WHERE jenis_id = rek('PEMDA','5.1.02')) = 90000000, 'UAT-16 total pagu tidak berubah setelah pergeseran');
SELECT harap((SELECT pagu_berlaku FROM pagu_jenis WHERE id = pagu('FAR','5.1.02')) = 63000000
         AND (SELECT pagu_berlaku FROM pagu_jenis WHERE id = pagu('LAB','5.1.02')) = 27000000, 'UAT-16 pagu asal turun, penerima naik');
SELECT harap(NOT EXISTS (SELECT 1 FROM fn_pagu_rekonsiliasi(2026::smallint)), 'UAT-35 rincian dan ledger tetap rekonsiliasi setelah pergeseran');
ROLLBACK;

SELECT harap_galat(
  $$INSERT INTO persetujuan_aksi (persetujuan_id, aksi, pelaku_id, kanal) VALUES (currval('persetujuan_id_seq'), 'SETUJUI', NULL, 'WEB')$$,
  'persetujuan_aksi_check', 'aksi tanpa pelaku hanya untuk sistem');
INSERT INTO persetujuan_aksi (persetujuan_id, aksi, pelaku_id, kanal, catatan) VALUES (currval('persetujuan_id_seq'), 'BATALKAN', NULL, 'SISTEM', 'validasi ulang gagal');
SELECT harap(true, 'sistem dapat membatalkan persetujuan saat validasi ulang gagal');
