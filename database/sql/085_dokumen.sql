-- 085: Dokumen anggaran formal, rencana penarikan (tidak mengikat), jadwal perubahan

CREATE TABLE konfigurasi_dokumen (
  tahun              smallint PRIMARY KEY,
  kepala             jsonb NOT NULL,     -- urusan, bidang, unit organisasi, sub unit, program, kegiatan, sub kegiatan, waktu, lokasi
  penanda_tangan     jsonb NOT NULL      -- nama, nip, jabatan
);

CREATE TABLE dokumen_anggaran (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid            uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  tahun           smallint NOT NULL,
  jenis           text NOT NULL CHECK (jenis IN ('PENETAPAN','PERGESERAN','PERUBAHAN')),
  urutan          smallint NOT NULL CHECK (urutan >= 0),
  nomor           text UNIQUE,
  pembanding_id   bigint REFERENCES dokumen_anggaran(id),
  status          text NOT NULL DEFAULT 'DRAF' CHECK (status IN ('DRAF','DIAJUKAN','DISAHKAN','DIBATALKAN')),
  diambil_pada    timestamptz NOT NULL DEFAULT now(),
  disahkan_pada   timestamptz,
  total           numeric(18,2) NOT NULL,
  dibuat_oleh     bigint NOT NULL,
  UNIQUE (tahun, jenis, urutan),
  CHECK (jenis = 'PENETAPAN' OR pembanding_id IS NOT NULL)
);

CREATE TABLE dokumen_anggaran_rincian (
  dokumen_id        bigint NOT NULL REFERENCES dokumen_anggaran(id),
  rincian_id        bigint NOT NULL,
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
CREATE TRIGGER trg_dok_rincian_append BEFORE UPDATE OR DELETE ON dokumen_anggaran_rincian
FOR EACH ROW EXECUTE FUNCTION fn_tolak_ubah();

CREATE TABLE rencana_penarikan (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  pagu_jenis_id   bigint NOT NULL REFERENCES pagu_jenis(id),
  triwulan        smallint NOT NULL CHECK (triwulan BETWEEN 1 AND 4),
  nilai           numeric(18,2) NOT NULL CHECK (nilai >= 0),
  UNIQUE (pagu_jenis_id, triwulan)
);

CREATE TABLE dokumen_anggaran_rencana_penarikan (
  dokumen_id      bigint NOT NULL REFERENCES dokumen_anggaran(id),
  pagu_jenis_id   bigint NOT NULL,
  triwulan        smallint NOT NULL,
  nilai           numeric(18,2) NOT NULL,
  PRIMARY KEY (dokumen_id, pagu_jenis_id, triwulan)
);

-- Pagu yang rencana penarikannya belum berjumlah sama dengan pagu berlaku (tidak mengikat transaksi).
CREATE OR REPLACE FUNCTION fn_rencana_penarikan_belum_sesuai(p_tahun smallint)
RETURNS TABLE (pagu_jenis_id bigint, pagu_berlaku numeric, total_rencana numeric)
LANGUAGE sql STABLE
AS $$
  SELECT p.id, p.pagu_berlaku, COALESCE(SUM(r.nilai), 0)
  FROM pagu_jenis p LEFT JOIN rencana_penarikan r ON r.pagu_jenis_id = p.id
  WHERE p.tahun = p_tahun
  GROUP BY p.id, p.pagu_berlaku
  HAVING p.pagu_berlaku <> COALESCE(SUM(r.nilai), 0)
$$;

CREATE TABLE jadwal_perubahan (
  id        bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun     smallint NOT NULL,
  berlaku   tstzrange NOT NULL,
  dasar     text NOT NULL,
  EXCLUDE USING gist (tahun WITH =, berlaku WITH &&)
);
