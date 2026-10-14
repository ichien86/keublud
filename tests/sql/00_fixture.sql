-- Data uji minimal: dua unit, satu sumber dana, rekening Pemda & BLUD untuk jenis 5.1.02 dan 5.2.02,
-- dua item standar, RBA definitif 2026 dengan pagu Farmasi dan Laboratorium.
SET client_min_messages = warning;

INSERT INTO unit_kerja (kode, nama, jenis) VALUES ('FAR','Instalasi Farmasi','INSTALASI'), ('LAB','Instalasi Laboratorium','INSTALASI');
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
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.2','BELANJA MODAL',2,a) RETURNING id INTO k;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.2.02','MODAL PERALATAN',3,k) RETURNING id INTO j;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.2.02.07','Alkes',4,j) RETURNING id INTO o;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id) VALUES (vb,'BLUD','5.2.02.07.01','Alat Kedokteran',5,o) RETURNING id INTO ro;
  INSERT INTO rekening (versi_id,bagan,kode,uraian,tingkat,induk_id,dapat_dianggarkan) VALUES (vb,'BLUD','5.2.02.07.01.0001','Alat Kedokteran Umum',6,ro,true);
END $$;

CREATE OR REPLACE FUNCTION rek(p_bagan text, p_kode text) RETURNS bigint LANGUAGE sql AS
$$ SELECT id FROM rekening WHERE bagan = p_bagan AND kode = p_kode $$;

INSERT INTO pemetaan_rekening (rekening_blud_id, rekening_pemda_id, berlaku) VALUES
  (rek('BLUD','5.1.02.01.01.0024'), rek('PEMDA','5.1.02.99.99.9999'), '[2026-01-01,)'),
  (rek('BLUD','5.1.02.01.01.0037'), rek('PEMDA','5.1.02.99.99.9999'), '[2026-01-01,)'),
  (rek('BLUD','5.2.02.07.01.0001'), rek('PEMDA','5.2.02.99.99.9999'), '[2026-01-01,)');

-- Fixture tidak bergantung pada nilai identity (sequence tidak di-reset antar-test Laravel).
INSERT INTO impor_batch (jenis, nama_file, path_file, sha256, dibuat_oleh) VALUES ('STANDAR_PEMDA','ssh.xlsx','x','abc',1);
INSERT INTO standar_harga_impor (impor_batch_id, tahun, sumber_dokumen, versi)
  VALUES ((SELECT max(id) FROM impor_batch WHERE sha256='abc'), 2026, 'Perkada SSH 2026', 1);
INSERT INTO standar_harga_item (tahun, sumber, impor_id, kode_barang, uraian, spesifikasi, satuan, harga, berlaku_mulai) VALUES
  (2026,'PEMDA',(SELECT max(id) FROM standar_harga_impor),'1.1.7.01','Kertas HVS A4','70 gram','rim',50000,'2026-01-01'),
  (2026,'PEMDA',(SELECT max(id) FROM standar_harga_impor),'1.1.12.01','Paracetamol 500 mg','tablet','box',100000,'2026-01-01'),
  (2026,'PEMDA',(SELECT max(id) FROM standar_harga_impor),'3.5.1.01','Bed pasien elektrik','3 motor','unit',31850000,'2026-01-01');

INSERT INTO rba (tahun, versi, jenis_versi, status, dibuat_oleh) VALUES (2026, 1, 'AWAL', 'DRAF', 1);

-- Rincian disusun saat RBA masih draf.
INSERT INTO rba_rincian (rba_id, unit_id, sumber_dana_id, rekening_blud_id, rekening_pemda_id, jenis_id, item_id, uraian, volume, satuan, harga_standar, harga) VALUES
 ((SELECT id FROM rba WHERE tahun=2026 AND versi=1), (SELECT id FROM unit_kerja WHERE kode='FAR'), (SELECT id FROM sumber_dana WHERE kode='JL'), rek('BLUD','5.1.02.01.01.0037'), rek('PEMDA','5.1.02.99.99.9999'), rek('PEMDA','5.1.02'), (SELECT id FROM standar_harga_item WHERE kode_barang='1.1.12.01'), 'Paracetamol', 500, 'box', 100000, 100000),   -- 50 jt
 ((SELECT id FROM rba WHERE tahun=2026 AND versi=1), (SELECT id FROM unit_kerja WHERE kode='FAR'), (SELECT id FROM sumber_dana WHERE kode='JL'), rek('BLUD','5.1.02.01.01.0024'), rek('PEMDA','5.1.02.99.99.9999'), rek('PEMDA','5.1.02'), (SELECT id FROM standar_harga_item WHERE kode_barang='1.1.7.01'), 'Kertas', 200, 'rim', 50000, 50000),          -- 10 jt
 ((SELECT id FROM rba WHERE tahun=2026 AND versi=1), (SELECT id FROM unit_kerja WHERE kode='LAB'), (SELECT id FROM sumber_dana WHERE kode='JL'), rek('BLUD','5.1.02.01.01.0024'), rek('PEMDA','5.1.02.99.99.9999'), rek('PEMDA','5.1.02'), (SELECT id FROM standar_harga_item WHERE kode_barang='1.1.7.01'), 'Kertas Lab', 600, 'rim', 50000, 50000),     -- 30 jt
 ((SELECT id FROM rba WHERE tahun=2026 AND versi=1), (SELECT id FROM unit_kerja WHERE kode='LAB'), (SELECT id FROM sumber_dana WHERE kode='JL'), rek('BLUD','5.2.02.07.01.0001'), rek('PEMDA','5.2.02.99.99.9999'), rek('PEMDA','5.2.02'), (SELECT id FROM standar_harga_item WHERE kode_barang='3.5.1.01'), 'Bed', 2, 'unit', 31850000, 31850000);      -- 63,7 jt

-- Penetapan definitif & pembentukan pagu melalui ledger.
UPDATE rba SET status='DEFINITIF', jenis_versi='DEFINITIF', nomor_pengesahan='900/1/2026', tanggal_pengesahan='2026-01-02' WHERE tahun=2026 AND versi=1;
INSERT INTO pagu_jenis (tahun, unit_id, jenis_id, sumber_dana_id)
SELECT DISTINCT 2026, unit_id, jenis_id, sumber_dana_id FROM rba_rincian WHERE rba_id = (SELECT id FROM rba WHERE tahun=2026 AND versi=1);
SELECT fn_pagu_catat(p.id, 'PAGU', t.total, 'RBA_DEFINITIF', 1)
FROM pagu_jenis p
JOIN (SELECT unit_id, jenis_id, sumber_dana_id, SUM(nilai) total FROM rba_rincian WHERE rba_id=(SELECT id FROM rba WHERE tahun=2026 AND versi=1) GROUP BY 1,2,3) t
  USING (unit_id, jenis_id, sumber_dana_id);

CREATE OR REPLACE FUNCTION pagu(p_unit text, p_jenis text) RETURNS bigint LANGUAGE sql AS
$$ SELECT p.id FROM pagu_jenis p JOIN unit_kerja u ON u.id = p.unit_id
   WHERE u.kode = p_unit AND p.jenis_id = rek('PEMDA', p_jenis) $$;

-- Bantu uji: harapkan galat yang pesannya memuat potongan teks tertentu.
CREATE OR REPLACE FUNCTION harap_galat(p_sql text, p_pesan text, p_nama text) RETURNS text
LANGUAGE plpgsql AS $$
BEGIN
  BEGIN
    EXECUTE p_sql;
  EXCEPTION WHEN OTHERS THEN
    IF position(p_pesan IN SQLERRM) > 0 THEN
      RETURN 'PASS ' || p_nama;
    END IF;
    RETURN 'FAIL ' || p_nama || ' — galat lain: ' || SQLERRM;
  END;
  RETURN 'FAIL ' || p_nama || ' — tidak ada galat';
END $$;

CREATE OR REPLACE FUNCTION harap(p_kondisi boolean, p_nama text) RETURNS text
LANGUAGE sql AS $$ SELECT CASE WHEN p_kondisi THEN 'PASS ' ELSE 'FAIL ' END || p_nama $$;
