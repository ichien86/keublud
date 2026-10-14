-- Data master demo untuk ujicoba (BUKAN data riil RSUD). Dijalankan oleh DemoSeeder.
-- Kodefikasi di bawah adalah contoh ringkas; ganti dengan Permendagri 90 + kodefikasi BLUD resmi.
SET client_min_messages = warning;

INSERT INTO unit_kerja (kode, nama, jenis) VALUES
  ('FAR','Instalasi Farmasi','INSTALASI'), ('LAB','Instalasi Laboratorium','INSTALASI'),
  ('RAD','Instalasi Radiologi','INSTALASI'), ('IGD','Instalasi Gawat Darurat','INSTALASI');
INSERT INTO sumber_dana (kode, nama, kelompok) VALUES ('JL','Jasa Layanan','PENDAPATAN_BLUD'), ('APBD','APBD','APBD');

INSERT INTO kodefikasi_versi (tahun, bagan, status) VALUES (2026,'PEMDA','AKTIF'), (2026,'BLUD','AKTIF');

-- Bagan Pemda: 5 > 5.1 > 5.1.02 > 5.1.02.99 > 5.1.02.99.99 > 5.1.02.99.99.9999 ; 5.2 > 5.2.02 > ... 9999
DO $$
DECLARE vp bigint := (SELECT id FROM kodefikasi_versi WHERE bagan='PEMDA');
        vb bigint := (SELECT id FROM kodefikasi_versi WHERE bagan='BLUD');
        a bigint; k bigint; j bigint; o bigint; ro bigint;
BEGIN
  -- PEMDA
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat) VALUES (vp,'PEMDA','5','BELANJA',1) RETURNING id INTO a;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vp,'PEMDA','5.1','BELANJA OPERASI',2,a) RETURNING id INTO k;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vp,'PEMDA','5.1.02','BELANJA BARANG DAN JASA',3,k) RETURNING id INTO j;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vp,'PEMDA','5.1.02.99','BBJ BLUD',4,j) RETURNING id INTO o;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vp,'PEMDA','5.1.02.99.99','BBJ BLUD',5,o) RETURNING id INTO ro;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id,dapat_dianggarkan) VALUES (vp,'PEMDA','5.1.02.99.99.9999','BBJ BLUD',6,ro,true);
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vp,'PEMDA','5.2','BELANJA MODAL',2,a) RETURNING id INTO k;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vp,'PEMDA','5.2.02','MODAL PERALATAN',3,k) RETURNING id INTO j;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vp,'PEMDA','5.2.02.99','MODAL BLUD',4,j) RETURNING id INTO o;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vp,'PEMDA','5.2.02.99.99','MODAL BLUD',5,o) RETURNING id INTO ro;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id,dapat_dianggarkan) VALUES (vp,'PEMDA','5.2.02.99.99.9999','MODAL BLUD',6,ro,true);
  -- BLUD
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat) VALUES (vb,'BLUD','5','BELANJA',1) RETURNING id INTO a;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.1','BELANJA OPERASI',2,a) RETURNING id INTO k;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.1.02','BELANJA BARANG DAN JASA',3,k) RETURNING id INTO j;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.1.02.01','Belanja Barang',4,j) RETURNING id INTO o;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.1.02.01.01','Barang Pakai Habis',5,o) RETURNING id INTO ro;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id,dapat_dianggarkan) VALUES (vb,'BLUD','5.1.02.01.01.0024','ATK',6,ro,true);
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id,dapat_dianggarkan) VALUES (vb,'BLUD','5.1.02.01.01.0037','Obat',6,ro,true);
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id,dapat_dianggarkan) VALUES (vb,'BLUD','5.1.02.01.01.0012','Bahan Medis Habis Pakai',6,ro,true);
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id,dapat_dianggarkan) VALUES (vb,'BLUD','5.1.02.01.01.0013','Reagen Laboratorium',6,ro,true);
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.2','BELANJA MODAL',2,a) RETURNING id INTO k;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.2.02','MODAL PERALATAN',3,k) RETURNING id INTO j;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.2.02.07','Alkes',4,j) RETURNING id INTO o;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.2.02.07.01','Alat Kedokteran',5,o) RETURNING id INTO ro;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id,dapat_dianggarkan) VALUES (vb,'BLUD','5.2.02.07.01.0001','Alat Kedokteran Umum',6,ro,true);
END $$;

CREATE OR REPLACE FUNCTION pg_temp.rek(p_bagan text, p_kode text) RETURNS bigint LANGUAGE sql AS
$$ SELECT id FROM rekening WHERE bagan = p_bagan AND kode = p_kode $$;

INSERT INTO pemetaan_rekening (rekening_blud_id, rekening_pemda_id, berlaku)
SELECT b.id, pg_temp.rek('PEMDA', CASE WHEN b.kode LIKE '5.1.02%' THEN '5.1.02.99.99.9999' ELSE '5.2.02.99.99.9999' END), '[2026-01-01,)'
  FROM rekening b WHERE b.bagan = 'BLUD' AND b.dapat_dianggarkan;

-- Standar harga Pemda (contoh): 2026 berlaku sejak 1 Januari, 2027 ditetapkan Oktober 2026 untuk penyusunan RBA 2027.
INSERT INTO impor_batch (jenis, nama_file, path_file, sha256, dibuat_oleh) VALUES ('STANDAR_PEMDA','ssh-demo.xlsx','demo','demo-2026',1), ('STANDAR_PEMDA','ssh-demo-2027.xlsx','demo','demo-2027',1);
INSERT INTO standar_harga_impor (impor_batch_id, tahun, sumber_dokumen, versi)
  SELECT id, CASE sha256 WHEN 'demo-2026' THEN 2026 ELSE 2027 END, 'Perkada SSH (contoh)', 1 FROM impor_batch WHERE sha256 LIKE 'demo-%';

INSERT INTO standar_harga_item (tahun, sumber, impor_id, kode_barang, uraian, spesifikasi, satuan, harga, berlaku_mulai)
SELECT t.tahun, 'PEMDA', i.id, v.kode, v.uraian, v.spek, v.satuan,
       CASE WHEN t.tahun = 2027 THEN round(v.harga * 1.05, -2) ELSE v.harga END,
       CASE WHEN t.tahun = 2027 THEN DATE '2026-10-01' ELSE DATE '2026-01-01' END
  FROM (VALUES (2026), (2027)) t(tahun)
  JOIN standar_harga_impor i ON i.tahun = t.tahun
 CROSS JOIN (VALUES
  ('1.1.7.01.01','Kertas HVS A4','70 gram','rim',50000),
  ('1.1.7.01.02','Kertas HVS F4','70 gram','rim',56000),
  ('1.1.7.02.01','Tinta printer','botol 70 ml','botol',95000),
  ('1.1.12.01.01','Paracetamol 500 mg','tablet, box isi 100','box',100000),
  ('1.1.12.01.02','Amoxicillin 500 mg','kapsul, box isi 100','box',175000),
  ('1.1.12.01.03','Ceftriaxone 1 g','injeksi, vial','vial',18500),
  ('1.1.12.02.01','Spuit 3 ml','steril sekali pakai, box isi 100','box',145000),
  ('1.1.12.02.02','Sarung tangan pemeriksaan','lateks, ukuran M, box isi 100','box',85000),
  ('1.1.12.03.01','Reagen hematologi 5 diff','kemasan 20 L','jerigen',4500000),
  ('1.1.12.03.02','Tabung vakum EDTA','3 ml, rak isi 100','rak',210000),
  ('1.1.12.04.01','Film rontgen digital','14 x 17 inci, box isi 100','box',3250000),
  ('3.5.1.01.01','Bed pasien elektrik','3 motor','unit',31850000),
  ('3.5.1.01.02','Monitor pasien','5 parameter','unit',42500000),
  ('3.5.1.02.01','Centrifuge laboratorium','24 tabung','unit',28750000)
 ) v(kode, uraian, spek, satuan, harga);
