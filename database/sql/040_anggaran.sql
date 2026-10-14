-- 040: RBA, rincian, pagu jenis, dan ledger pagu
-- Pagu dikendalikan per unit x jenis belanja (rekening Pemda tingkat 3) x sumber dana x tahun.

CREATE TABLE rba (
  id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid                uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  tahun               smallint NOT NULL,
  versi               integer NOT NULL,
  jenis_versi         text NOT NULL CHECK (jenis_versi IN ('AWAL','DEFINITIF','PERUBAHAN')),
  status              text NOT NULL DEFAULT 'DRAF'
                      CHECK (status IN ('DRAF','DIAJUKAN','DIKEMBALIKAN','DIVERIFIKASI','DISETUJUI_INTERNAL',
                                        'MENUNGGU_PENETAPAN','DEFINITIF','DIGANTIKAN')),
  target_pendapatan   numeric(18,2),
  persen_ambang_batas numeric(5,2) CHECK (persen_ambang_batas BETWEEN 0 AND 100),
  nomor_pengesahan    text,
  tanggal_pengesahan  date,
  dibuat_oleh         bigint NOT NULL,
  dibuat_pada         timestamptz NOT NULL DEFAULT now(),
  diubah_pada         timestamptz NOT NULL DEFAULT now(),
  row_version         integer NOT NULL DEFAULT 1,
  UNIQUE (tahun, versi),
  CHECK (status <> 'DEFINITIF' OR (nomor_pengesahan IS NOT NULL AND tanggal_pengesahan IS NOT NULL))
);
CREATE UNIQUE INDEX rba_satu_berlaku ON rba (tahun) WHERE status = 'DEFINITIF';
CREATE TRIGGER trg_rba_rv BEFORE UPDATE ON rba FOR EACH ROW EXECUTE FUNCTION fn_row_version();

CREATE TABLE rba_rincian (
  id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  rba_id              bigint NOT NULL REFERENCES rba(id),
  unit_id             bigint NOT NULL REFERENCES unit_kerja(id),     -- wajib (PRD 7.16)
  sumber_dana_id      bigint NOT NULL REFERENCES sumber_dana(id),
  sub_kegiatan_id     bigint REFERENCES nomenklatur(id),
  rekening_blud_id    bigint NOT NULL REFERENCES rekening(id),
  rekening_pemda_id   bigint NOT NULL REFERENCES rekening(id),       -- snapshot hasil pemetaan
  jenis_id            bigint NOT NULL REFERENCES rekening(id),       -- jenis rekening Pemda (tingkat 3)
  item_id             bigint NOT NULL REFERENCES standar_harga_item(id),
  uraian              text NOT NULL,
  volume              numeric(18,4) NOT NULL CHECK (volume > 0),
  satuan              text NOT NULL,
  harga_standar       numeric(18,2) NOT NULL CHECK (harga_standar > 0),
  harga               numeric(18,2) NOT NULL CHECK (harga > 0),
  nilai               numeric(18,2) GENERATED ALWAYS AS (round(volume * harga, 2)) STORED,
  di_atas_standar     boolean GENERATED ALWAYS AS (harga > harga_standar) STORED,
  dasar_harga         text NOT NULL DEFAULT 'STANDAR'
                      CHECK (dasar_harga IN ('STANDAR','DISETUJUI_DI_ATAS_STANDAR')),
  realisasi           numeric(18,2) NOT NULL DEFAULT 0 CHECK (realisasi >= 0),
  komitmen            numeric(18,2) NOT NULL DEFAULT 0 CHECK (komitmen >= 0),
  terkunci_paket_id   bigint,                                        -- FK ditambahkan di 050
  terkunci_transaksi  boolean NOT NULL DEFAULT false,
  aktif               boolean NOT NULL DEFAULT true,
  label_lama          text,                                          -- label penanggung jawab dari impor
  dibuat_pada         timestamptz NOT NULL DEFAULT now(),
  diubah_pada         timestamptz NOT NULL DEFAULT now(),
  row_version         integer NOT NULL DEFAULT 1,
  CONSTRAINT rincian_harga_sah CHECK (NOT di_atas_standar OR dasar_harga = 'DISETUJUI_DI_ATAS_STANDAR'),
  CONSTRAINT rincian_tidak_di_bawah_pemakaian CHECK (NOT aktif OR nilai >= realisasi + komitmen),
  CONSTRAINT rincian_nonaktif_tanpa_pemakaian CHECK (aktif OR (realisasi = 0 AND komitmen = 0))
);
CREATE INDEX rincian_pagu_idx ON rba_rincian (rba_id, unit_id, jenis_id, sumber_dana_id) WHERE aktif;
CREATE TRIGGER trg_rincian_rv BEFORE UPDATE ON rba_rincian FOR EACH ROW EXECUTE FUNCTION fn_row_version();
CREATE TRIGGER trg_rincian_tanpa_hapus BEFORE DELETE ON rba_rincian FOR EACH ROW EXECUTE FUNCTION fn_tolak_ubah();

-- Konsistensi rincian: jenis harus tingkat 3 dan sama dengan jenis rekening Pemda;
-- item standar harus aktif, setahun dengan RBA, dan berlaku pada tanggal penyimpanan;
-- RBA yang sudah DEFINITIF hanya dapat diubah melalui aplikasi dengan izin revisi.
CREATE OR REPLACE FUNCTION fn_rincian_validasi() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
  v_rba    rba%ROWTYPE;
  v_item   standar_harga_item%ROWTYPE;
BEGIN
  SELECT * INTO v_rba FROM rba WHERE id = NEW.rba_id;

  IF (SELECT jenis_id FROM rekening WHERE id = NEW.rekening_pemda_id) IS DISTINCT FROM NEW.jenis_id THEN
    RAISE EXCEPTION 'RINCIAN_JENIS_TIDAK_SESUAI: jenis rincian harus jenis rekening Pemda' USING ERRCODE = 'P0001';
  END IF;

  IF TG_OP = 'INSERT' OR NEW.item_id <> OLD.item_id OR NEW.harga_standar <> OLD.harga_standar THEN
    SELECT * INTO v_item FROM standar_harga_item WHERE id = NEW.item_id;
    IF v_item.status <> 'AKTIF' OR v_item.tahun <> v_rba.tahun THEN
      RAISE EXCEPTION 'ITEM_TIDAK_BERLAKU: item standar % tidak aktif untuk tahun %', NEW.item_id, v_rba.tahun
        USING ERRCODE = 'P0001';
    END IF;
    IF v_item.berlaku_mulai > current_date THEN
      RAISE EXCEPTION 'ITEM_BELUM_BERLAKU: item standar % berlaku mulai %', NEW.item_id, v_item.berlaku_mulai
        USING ERRCODE = 'P0001';
    END IF;
    IF NEW.harga_standar <> v_item.harga THEN
      RAISE EXCEPTION 'HARGA_STANDAR_TIDAK_SESUAI: harga standar rincian harus sama dengan harga item (%)', v_item.harga
        USING ERRCODE = 'P0001';
    END IF;
  END IF;

  -- Pada penyusunan (belum definitif) harga di atas standar tidak pernah diizinkan.
  IF v_rba.status NOT IN ('DEFINITIF','DIGANTIKAN') AND NEW.harga > NEW.harga_standar THEN
    RAISE EXCEPTION 'HARGA_DI_ATAS_STANDAR: pada penyusunan RBA harga tidak boleh melebihi standar (%)', NEW.harga_standar
      USING ERRCODE = 'P0001';
  END IF;

  -- Perubahan isi rincian RBA definitif wajib melalui revisi/pergeseran (flag diset service dalam transaksi).
  IF v_rba.status = 'DEFINITIF'
     AND COALESCE(current_setting('app.izin_ubah_rincian', true), '') <> 'on'
     AND (TG_OP = 'INSERT'
          OR (NEW.volume, NEW.harga, NEW.item_id, NEW.unit_id, NEW.rekening_blud_id, NEW.aktif)
             IS DISTINCT FROM (OLD.volume, OLD.harga, OLD.item_id, OLD.unit_id, OLD.rekening_blud_id, OLD.aktif)) THEN
    RAISE EXCEPTION 'RBA_DEFINITIF_TERKUNCI: rincian RBA definitif hanya dapat diubah melalui revisi rincian atau pergeseran'
      USING ERRCODE = 'P0001';
  END IF;

  RETURN NEW;
END $$;

CREATE TRIGGER trg_rincian_validasi BEFORE INSERT OR UPDATE ON rba_rincian
FOR EACH ROW EXECUTE FUNCTION fn_rincian_validasi();

CREATE TABLE pagu_jenis (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun           smallint NOT NULL,
  unit_id         bigint NOT NULL REFERENCES unit_kerja(id),
  jenis_id        bigint NOT NULL REFERENCES rekening(id),
  sumber_dana_id  bigint NOT NULL REFERENCES sumber_dana(id),
  pagu_baseline   numeric(18,2) NOT NULL DEFAULT 0,
  pagu_berlaku    numeric(18,2) NOT NULL DEFAULT 0,
  realisasi       numeric(18,2) NOT NULL DEFAULT 0,
  komitmen        numeric(18,2) NOT NULL DEFAULT 0,
  tercadang       numeric(18,2) NOT NULL DEFAULT 0,
  sisa            numeric(18,2) GENERATED ALWAYS AS (pagu_berlaku - realisasi - komitmen - tercadang) STORED,
  row_version     integer NOT NULL DEFAULT 1,
  UNIQUE (tahun, unit_id, jenis_id, sumber_dana_id),
  CONSTRAINT pagu_sisa_tidak_negatif CHECK (pagu_berlaku - realisasi - komitmen - tercadang >= 0),
  CONSTRAINT pagu_nilai_positif CHECK (pagu_berlaku >= 0 AND realisasi >= 0 AND komitmen >= 0 AND tercadang >= 0)
);

CREATE TABLE pagu_mutasi (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  pagu_jenis_id   bigint NOT NULL REFERENCES pagu_jenis(id),
  komponen        text NOT NULL CHECK (komponen IN ('PAGU','REALISASI','KOMITMEN','TERCADANG')),
  nilai           numeric(18,2) NOT NULL CHECK (nilai <> 0),
  sumber_tipe     text NOT NULL,
  sumber_id       bigint NOT NULL,
  keterangan      text,
  dibuat_oleh     bigint,                       -- NULL = sistem
  dibuat_pada     timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON pagu_mutasi (pagu_jenis_id, dibuat_pada);
CREATE INDEX ON pagu_mutasi (sumber_tipe, sumber_id);
CREATE TRIGGER trg_pagu_mutasi_append BEFORE UPDATE OR DELETE ON pagu_mutasi
FOR EACH ROW EXECUTE FUNCTION fn_tolak_ubah();

-- Satu-satunya jalan mengubah agregat pagu: catat mutasi + perbarui agregat dalam transaksi pemanggil.
-- Pemanggil wajib sudah mengunci baris pagu_jenis (SELECT ... FOR UPDATE, urut id).
CREATE OR REPLACE FUNCTION fn_pagu_catat(
  p_pagu_jenis_id bigint, p_komponen text, p_nilai numeric,
  p_sumber_tipe text, p_sumber_id bigint, p_keterangan text DEFAULT NULL
) RETURNS bigint
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_id bigint;
BEGIN
  IF p_nilai = 0 THEN
    RETURN NULL;
  END IF;

  INSERT INTO pagu_mutasi (pagu_jenis_id, komponen, nilai, sumber_tipe, sumber_id, keterangan, dibuat_oleh)
  VALUES (p_pagu_jenis_id, p_komponen, p_nilai, p_sumber_tipe, p_sumber_id, p_keterangan, app_user_id())
  RETURNING id INTO v_id;

  PERFORM set_config('app.izin_ubah_pagu', 'on', true);
  UPDATE pagu_jenis SET
    pagu_berlaku = pagu_berlaku + CASE WHEN p_komponen = 'PAGU'      THEN p_nilai ELSE 0 END,
    realisasi    = realisasi    + CASE WHEN p_komponen = 'REALISASI' THEN p_nilai ELSE 0 END,
    komitmen     = komitmen     + CASE WHEN p_komponen = 'KOMITMEN'  THEN p_nilai ELSE 0 END,
    tercadang    = tercadang    + CASE WHEN p_komponen = 'TERCADANG' THEN p_nilai ELSE 0 END,
    row_version  = row_version + 1
  WHERE id = p_pagu_jenis_id;
  PERFORM set_config('app.izin_ubah_pagu', 'off', true);

  IF NOT FOUND THEN
    RAISE EXCEPTION 'Pagu jenis % tidak ditemukan', p_pagu_jenis_id;
  END IF;
  RETURN v_id;
END $$;

-- Agregat pagu hanya boleh berubah lewat fn_pagu_catat (kecuali saat pembentukan baris baru).
CREATE OR REPLACE FUNCTION fn_pagu_jaga_agregat() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  IF (NEW.pagu_berlaku, NEW.realisasi, NEW.komitmen, NEW.tercadang)
       IS DISTINCT FROM (OLD.pagu_berlaku, OLD.realisasi, OLD.komitmen, OLD.tercadang)
     AND COALESCE(current_setting('app.izin_ubah_pagu', true), '') <> 'on' THEN
    RAISE EXCEPTION 'PAGU_HANYA_LEWAT_LEDGER: agregat pagu hanya dapat diubah melalui fn_pagu_catat'
      USING ERRCODE = 'P0001';
  END IF;
  IF NEW.pagu_baseline IS DISTINCT FROM OLD.pagu_baseline THEN
    RAISE EXCEPTION 'pagu_baseline tidak dapat diubah' USING ERRCODE = 'P0001';
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_pagu_jaga_agregat BEFORE UPDATE ON pagu_jenis
FOR EACH ROW EXECUTE FUNCTION fn_pagu_jaga_agregat();

CREATE OR REPLACE FUNCTION fn_pagu_baru_kosong() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  IF NEW.pagu_berlaku <> 0 OR NEW.realisasi <> 0 OR NEW.komitmen <> 0 OR NEW.tercadang <> 0 THEN
    RAISE EXCEPTION 'Baris pagu baru harus dibuat bernilai 0 lalu diisi melalui fn_pagu_catat' USING ERRCODE = 'P0001';
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_pagu_baru_kosong BEFORE INSERT ON pagu_jenis
FOR EACH ROW EXECUTE FUNCTION fn_pagu_baru_kosong();

-- Rekonsiliasi: agregat vs ledger, dan total rincian aktif vs pagu berlaku.
CREATE OR REPLACE FUNCTION fn_pagu_rekonsiliasi(p_tahun smallint)
RETURNS TABLE (pagu_jenis_id bigint, masalah text, nilai_agregat numeric, nilai_hitung numeric)
LANGUAGE sql STABLE
AS $$
  WITH l AS (
    SELECT pagu_jenis_id,
      SUM(nilai) FILTER (WHERE komponen = 'PAGU')      AS pagu,
      SUM(nilai) FILTER (WHERE komponen = 'REALISASI') AS realisasi,
      SUM(nilai) FILTER (WHERE komponen = 'KOMITMEN')  AS komitmen,
      SUM(nilai) FILTER (WHERE komponen = 'TERCADANG') AS tercadang
    FROM pagu_mutasi GROUP BY pagu_jenis_id
  ), r AS (
    SELECT p.id, SUM(rr.nilai) AS total_rincian
    FROM pagu_jenis p
    JOIN rba b ON b.tahun = p.tahun AND b.status = 'DEFINITIF'
    LEFT JOIN rba_rincian rr ON rr.rba_id = b.id AND rr.aktif AND rr.unit_id = p.unit_id
         AND rr.jenis_id = p.jenis_id AND rr.sumber_dana_id = p.sumber_dana_id
    WHERE p.tahun = p_tahun
    GROUP BY p.id
  )
  SELECT p.id, 'PAGU_VS_LEDGER', p.pagu_berlaku, COALESCE(l.pagu, 0)
    FROM pagu_jenis p LEFT JOIN l ON l.pagu_jenis_id = p.id
   WHERE p.tahun = p_tahun AND p.pagu_berlaku <> COALESCE(l.pagu, 0)
  UNION ALL
  SELECT p.id, 'REALISASI_VS_LEDGER', p.realisasi, COALESCE(l.realisasi, 0)
    FROM pagu_jenis p LEFT JOIN l ON l.pagu_jenis_id = p.id
   WHERE p.tahun = p_tahun AND p.realisasi <> COALESCE(l.realisasi, 0)
  UNION ALL
  SELECT p.id, 'KOMITMEN_VS_LEDGER', p.komitmen, COALESCE(l.komitmen, 0)
    FROM pagu_jenis p LEFT JOIN l ON l.pagu_jenis_id = p.id
   WHERE p.tahun = p_tahun AND p.komitmen <> COALESCE(l.komitmen, 0)
  UNION ALL
  SELECT p.id, 'TERCADANG_VS_LEDGER', p.tercadang, COALESCE(l.tercadang, 0)
    FROM pagu_jenis p LEFT JOIN l ON l.pagu_jenis_id = p.id
   WHERE p.tahun = p_tahun AND p.tercadang <> COALESCE(l.tercadang, 0)
  UNION ALL
  SELECT p.id, 'RINCIAN_VS_PAGU', p.pagu_berlaku, COALESCE(r.total_rincian, 0)
    FROM pagu_jenis p JOIN r ON r.id = p.id
   WHERE p.pagu_berlaku <> COALESCE(r.total_rincian, 0)
$$;
