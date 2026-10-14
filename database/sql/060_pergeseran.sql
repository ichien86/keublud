-- 060: Permintaan & pasangan pergeseran antarunit (PRD 7.8, 7.8a)

CREATE TABLE permintaan_pergeseran (
  id                     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid                   uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  nomor                  text UNIQUE,
  arah                   text NOT NULL CHECK (arah IN ('TAMBAH','KURANG')),
  pagu_jenis_id          bigint NOT NULL REFERENCES pagu_jenis(id),
  nilai                  numeric(18,2) NOT NULL CHECK (nilai > 0),
  revisi_paket_id        bigint REFERENCES revisi_paket(id),
  memuat_di_atas_standar boolean NOT NULL DEFAULT false,
  status                 text NOT NULL DEFAULT 'TERBUKA'
                         CHECK (status IN ('TERBUKA','BERPASANGAN','DISAHKAN','DITOLAK','DITOLAK_OTOMATIS','DIBATALKAN')),
  diajukan_oleh          bigint NOT NULL,
  diajukan_pada          timestamptz NOT NULL DEFAULT now(),
  batas_pasangan         timestamptz NOT NULL,
  pengingat_terkirim     boolean NOT NULL DEFAULT false,
  alasan_tolak           text,
  row_version            integer NOT NULL DEFAULT 1,
  diubah_pada            timestamptz NOT NULL DEFAULT now(),
  CHECK (batas_pasangan > diajukan_pada)
);
CREATE INDEX ON permintaan_pergeseran (status, batas_pasangan);
CREATE INDEX ON permintaan_pergeseran (pagu_jenis_id);
CREATE TRIGGER trg_permintaan_rv BEFORE UPDATE ON permintaan_pergeseran FOR EACH ROW EXECUTE FUNCTION fn_row_version();

ALTER TABLE revisi_paket
  ADD CONSTRAINT revisi_permintaan_fk FOREIGN KEY (permintaan_id) REFERENCES permintaan_pergeseran(id);

CREATE TABLE pasangan_pergeseran (
  id                     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid                   uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  nomor                  text UNIQUE,
  tahun                  smallint NOT NULL,
  jenis_id               bigint NOT NULL REFERENCES rekening(id),
  sumber_dana_id         bigint NOT NULL REFERENCES sumber_dana(id),
  total_tambah           numeric(18,2) NOT NULL,
  total_kurang           numeric(18,2) NOT NULL,
  memuat_di_atas_standar boolean NOT NULL DEFAULT false,
  status                 text NOT NULL DEFAULT 'MENUNGGU_UNIT'
                         CHECK (status IN ('MENUNGGU_UNIT','MENUNGGU_PERENCANA','DISAHKAN','DISETUJUI_OTOMATIS',
                                           'DITOLAK','DITOLAK_VALIDASI')),
  dibentuk_oleh          bigint NOT NULL,
  dibentuk_pada          timestamptz NOT NULL DEFAULT now(),
  lengkap_pada           timestamptz,
  batas_pengesahan       timestamptz,
  diputuskan_oleh        bigint,              -- NULL bila DISETUJUI_OTOMATIS
  diputuskan_pada        timestamptz,
  alasan                 text,
  row_version            integer NOT NULL DEFAULT 1,
  diubah_pada            timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT pasangan_seimbang CHECK (total_tambah = total_kurang AND total_tambah > 0),
  CHECK (status NOT IN ('MENUNGGU_PERENCANA','DISAHKAN','DISETUJUI_OTOMATIS')
         OR (lengkap_pada IS NOT NULL AND batas_pengesahan IS NOT NULL)),
  CONSTRAINT pasangan_otomatis_tanpa_di_atas_standar
    CHECK (NOT (status = 'DISETUJUI_OTOMATIS' AND memuat_di_atas_standar))
);
CREATE INDEX ON pasangan_pergeseran (status, batas_pengesahan);
CREATE TRIGGER trg_pasangan_rv BEFORE UPDATE ON pasangan_pergeseran FOR EACH ROW EXECUTE FUNCTION fn_row_version();

CREATE TABLE pasangan_anggota (
  pasangan_id       bigint NOT NULL REFERENCES pasangan_pergeseran(id),
  permintaan_id     bigint NOT NULL REFERENCES permintaan_pergeseran(id),
  persetujuan_unit  text NOT NULL DEFAULT 'MENUNGGU' CHECK (persetujuan_unit IN ('MENUNGGU','SETUJU','TOLAK')),
  diputuskan_oleh   bigint,
  diputuskan_pada   timestamptz,
  PRIMARY KEY (pasangan_id, permintaan_id)
);

-- Satu permintaan hanya boleh ada di satu pasangan yang masih hidup.
-- Kolom aktif dimatikan saat pasangan ditolak agar permintaan dapat dipasangkan ulang.
ALTER TABLE pasangan_anggota ADD COLUMN aktif boolean NOT NULL DEFAULT true;
CREATE UNIQUE INDEX anggota_unik_aktif ON pasangan_anggota (permintaan_id) WHERE aktif;

-- Diperiksa saat COMMIT: anggota sejenis, sesumber dana, setahun; arah berlawanan dari unit berbeda;
-- total per arah sama dengan total di kepala pasangan.
CREATE OR REPLACE FUNCTION fn_pasangan_konsisten() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
  v_id      bigint;
  v_p       pasangan_pergeseran%ROWTYPE;
  v_salah   integer;
  v_tambah  numeric;
  v_kurang  numeric;
  v_irisan  integer;
BEGIN
  IF TG_TABLE_NAME = 'pasangan_anggota' THEN
    v_id := NEW.pasangan_id;
  ELSE
    v_id := NEW.id;
  END IF;

  SELECT * INTO v_p FROM pasangan_pergeseran WHERE id = v_id;
  IF NOT FOUND THEN RETURN NULL; END IF;

  SELECT count(*) INTO v_salah
  FROM pasangan_anggota a
  JOIN permintaan_pergeseran m ON m.id = a.permintaan_id
  JOIN pagu_jenis g ON g.id = m.pagu_jenis_id
  WHERE a.pasangan_id = v_id AND a.aktif
    AND (g.jenis_id <> v_p.jenis_id OR g.sumber_dana_id <> v_p.sumber_dana_id OR g.tahun <> v_p.tahun);
  IF v_salah > 0 THEN
    RAISE EXCEPTION 'PASANGAN_TIDAK_SEJENIS: semua anggota pasangan % harus sejenis belanja, sesumber dana, dan setahun', v_id
      USING ERRCODE = 'P0001';
  END IF;

  SELECT COALESCE(SUM(m.nilai) FILTER (WHERE m.arah = 'TAMBAH'), 0),
         COALESCE(SUM(m.nilai) FILTER (WHERE m.arah = 'KURANG'), 0)
    INTO v_tambah, v_kurang
  FROM pasangan_anggota a JOIN permintaan_pergeseran m ON m.id = a.permintaan_id
  WHERE a.pasangan_id = v_id AND a.aktif;

  IF v_p.status NOT IN ('DITOLAK','DITOLAK_VALIDASI')
     AND (v_tambah <> v_p.total_tambah OR v_kurang <> v_p.total_kurang) THEN
    RAISE EXCEPTION 'PASANGAN_TOTAL_TIDAK_SESUAI: anggota pasangan % berjumlah tambah % / kurang %, kepala % / %',
      v_id, v_tambah, v_kurang, v_p.total_tambah, v_p.total_kurang USING ERRCODE = 'P0001';
  END IF;

  SELECT count(*) INTO v_irisan
  FROM (SELECT DISTINCT g.unit_id FROM pasangan_anggota a
          JOIN permintaan_pergeseran m ON m.id = a.permintaan_id JOIN pagu_jenis g ON g.id = m.pagu_jenis_id
         WHERE a.pasangan_id = v_id AND a.aktif AND m.arah = 'TAMBAH') t
  JOIN (SELECT DISTINCT g.unit_id FROM pasangan_anggota a
          JOIN permintaan_pergeseran m ON m.id = a.permintaan_id JOIN pagu_jenis g ON g.id = m.pagu_jenis_id
         WHERE a.pasangan_id = v_id AND a.aktif AND m.arah = 'KURANG') k USING (unit_id);
  IF v_irisan > 0 THEN
    RAISE EXCEPTION 'PASANGAN_UNIT_SAMA: unit yang sama tidak boleh berada di sisi tambah dan kurang' USING ERRCODE = 'P0001';
  END IF;

  RETURN NULL;
END $$;

CREATE CONSTRAINT TRIGGER trg_pasangan_konsisten_kepala
AFTER INSERT OR UPDATE ON pasangan_pergeseran DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION fn_pasangan_konsisten();

CREATE CONSTRAINT TRIGGER trg_pasangan_konsisten_anggota
AFTER INSERT OR UPDATE ON pasangan_anggota DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION fn_pasangan_konsisten();
