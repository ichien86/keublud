-- 020: Kodefikasi rekening & nomenklatur (pola Permendagri 90/2019) dan pemetaan

CREATE TABLE kodefikasi_versi (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun         smallint NOT NULL,
  bagan         text NOT NULL CHECK (bagan IN ('PEMDA','BLUD','NOMENKLATUR')),
  keterangan    text,
  status        text NOT NULL DEFAULT 'DRAF' CHECK (status IN ('DRAF','AKTIF','ARSIP')),
  UNIQUE (tahun, bagan)
);

-- tingkat: 1 Akun, 2 Kelompok, 3 Jenis, 4 Objek, 5 Rincian Objek, 6 Sub Rincian Objek
CREATE TABLE rekening (
  id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  versi_id          bigint NOT NULL REFERENCES kodefikasi_versi(id),
  bagan             text NOT NULL CHECK (bagan IN ('PEMDA','BLUD')),
  kode              text NOT NULL,
  uraian            text NOT NULL,
  tingkat           smallint NOT NULL CHECK (tingkat BETWEEN 1 AND 6),
  induk_id          bigint REFERENCES rekening(id),
  jenis_id          bigint REFERENCES rekening(id),   -- leluhur tingkat 3; diisi pemicu
  dapat_dianggarkan boolean NOT NULL DEFAULT false,
  aktif             boolean NOT NULL DEFAULT true,
  UNIQUE (versi_id, kode),
  CHECK (tingkat = 1 OR induk_id IS NOT NULL),
  CHECK (NOT dapat_dianggarkan OR tingkat = 6)
);
CREATE INDEX ON rekening (versi_id, tingkat);
CREATE INDEX ON rekening (jenis_id);

CREATE OR REPLACE FUNCTION fn_rekening_jenis() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
  v_induk rekening%ROWTYPE;
BEGIN
  IF NEW.induk_id IS NOT NULL THEN
    SELECT * INTO v_induk FROM rekening WHERE id = NEW.induk_id;
    IF v_induk.tingkat <> NEW.tingkat - 1 THEN
      RAISE EXCEPTION 'Induk rekening % harus bertingkat %, bukan %', NEW.kode, NEW.tingkat - 1, v_induk.tingkat;
    END IF;
    IF v_induk.versi_id <> NEW.versi_id OR v_induk.bagan <> NEW.bagan THEN
      RAISE EXCEPTION 'Induk rekening % harus berada pada versi dan bagan yang sama', NEW.kode;
    END IF;
  END IF;

  IF NEW.tingkat < 3 THEN
    NEW.jenis_id := NULL;
  ELSIF NEW.tingkat = 3 THEN
    NEW.jenis_id := NEW.id;
  ELSE
    NEW.jenis_id := v_induk.jenis_id;
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_rekening_jenis BEFORE INSERT OR UPDATE OF induk_id, tingkat ON rekening
FOR EACH ROW EXECUTE FUNCTION fn_rekening_jenis();

-- Kode jenis (tingkat 3) suatu rekening, mis. '5.1.02'.
CREATE OR REPLACE FUNCTION fn_kode_jenis(p_rekening_id bigint) RETURNS text
LANGUAGE sql STABLE
AS $$ SELECT j.kode FROM rekening r JOIN rekening j ON j.id = r.jenis_id WHERE r.id = p_rekening_id $$;

-- tingkat: 1 Urusan, 2 Bidang Urusan, 3 Program, 4 Kegiatan, 5 Sub Kegiatan
CREATE TABLE nomenklatur (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  versi_id      bigint NOT NULL REFERENCES kodefikasi_versi(id),
  kode          text NOT NULL,
  uraian        text NOT NULL,
  tingkat       smallint NOT NULL CHECK (tingkat BETWEEN 1 AND 5),
  induk_id      bigint REFERENCES nomenklatur(id),
  UNIQUE (versi_id, kode)
);

-- Pemetaan rekening BLUD -> rekening Pemda. Banyak-ke-satu diizinkan
-- (mis. 5.1.02.01.01.0037 & 5.1.02.01.01.0024 -> 5.1.02.99.99.9999).
CREATE TABLE pemetaan_rekening (
  id                 bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  rekening_blud_id   bigint NOT NULL REFERENCES rekening(id),
  rekening_pemda_id  bigint NOT NULL REFERENCES rekening(id),
  berlaku            daterange NOT NULL,
  versi_pemetaan     integer NOT NULL DEFAULT 1,
  dibuat_oleh        bigint,
  dibuat_pada        timestamptz NOT NULL DEFAULT now(),
  EXCLUDE USING gist (rekening_blud_id WITH =, berlaku WITH &&)
);
CREATE INDEX ON pemetaan_rekening (rekening_pemda_id);

CREATE OR REPLACE FUNCTION fn_pemetaan_validasi() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
  b rekening%ROWTYPE;
  p rekening%ROWTYPE;
BEGIN
  SELECT * INTO b FROM rekening WHERE id = NEW.rekening_blud_id;
  SELECT * INTO p FROM rekening WHERE id = NEW.rekening_pemda_id;
  IF b.bagan <> 'BLUD' OR p.bagan <> 'PEMDA' THEN
    RAISE EXCEPTION 'Pemetaan harus dari rekening BLUD ke rekening Pemda' USING ERRCODE = 'P0001';
  END IF;
  IF b.tingkat <> p.tingkat THEN
    RAISE EXCEPTION 'PEMETAAN_TINGKAT_TIDAK_SETARA: rekening % (tingkat %) tidak dapat dipetakan ke % (tingkat %)',
      b.kode, b.tingkat, p.kode, p.tingkat USING ERRCODE = 'P0001';
  END IF;
  IF fn_kode_jenis(b.id) IS DISTINCT FROM fn_kode_jenis(p.id) THEN
    RAISE EXCEPTION 'PEMETAAN_BEDA_JENIS: rekening % (jenis %) tidak boleh dipetakan ke % (jenis %)',
      b.kode, fn_kode_jenis(b.id), p.kode, fn_kode_jenis(p.id) USING ERRCODE = 'P0001';
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_pemetaan_validasi BEFORE INSERT OR UPDATE ON pemetaan_rekening
FOR EACH ROW EXECUTE FUNCTION fn_pemetaan_validasi();

-- Rekening Pemda yang berlaku untuk rekening BLUD pada tanggal tertentu.
CREATE OR REPLACE FUNCTION fn_rekening_pemda(p_rekening_blud_id bigint, p_tanggal date) RETURNS bigint
LANGUAGE sql STABLE
AS $$
  SELECT rekening_pemda_id FROM pemetaan_rekening
  WHERE rekening_blud_id = p_rekening_blud_id AND berlaku @> p_tanggal
$$;
