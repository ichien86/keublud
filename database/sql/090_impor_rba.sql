-- 090: Impor RBA format lama (TDD 8.4): staging, pemecahan ke item standar harga, rekonsiliasi.

CREATE TABLE impor_rba (
  id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid             uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  impor_batch_id   bigint NOT NULL UNIQUE REFERENCES impor_batch(id),
  tahun            smallint NOT NULL,
  sheet            text NOT NULL,
  kelompok         text NOT NULL,                 -- judul kelompok nilai yang dipakai (Penetapan/Perubahan/...)
  sumber_dana_id   bigint NOT NULL REFERENCES sumber_dana(id),
  kepala           jsonb NOT NULL DEFAULT '{}',
  kodefikasi       jsonb NOT NULL DEFAULT '{}',   -- rekening yang tertulis di file (bahan melengkapi bagan)
  peringatan       text[] NOT NULL DEFAULT '{}',
  status           text NOT NULL DEFAULT 'STAGING' CHECK (status IN ('STAGING','SELESAI','DIBATALKAN')),
  rba_id           bigint REFERENCES rba(id),
  usulan_batch_id  bigint REFERENCES usulan_standar_batch(id),
  dibuat_oleh      bigint NOT NULL,
  dibuat_pada      timestamptz NOT NULL DEFAULT now(),
  diubah_pada      timestamptz NOT NULL DEFAULT now(),
  row_version      integer NOT NULL DEFAULT 1,
  CHECK (status <> 'SELESAI' OR rba_id IS NOT NULL)
);
CREATE TRIGGER trg_impor_rba_rv BEFORE UPDATE ON impor_rba FOR EACH ROW EXECUTE FUNCTION fn_row_version();

-- Total per rekening Pemda menurut file, pembanding rekonsiliasi.
CREATE TABLE impor_rba_rekening (
  impor_rba_id     bigint NOT NULL REFERENCES impor_rba(id),
  kode_pemda       text NOT NULL,
  uraian           text NOT NULL DEFAULT '',
  total_file       numeric(18,2) NOT NULL,
  PRIMARY KEY (impor_rba_id, kode_pemda)
);

CREATE TABLE staging_rba (
  id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  impor_rba_id     bigint NOT NULL REFERENCES impor_rba(id),
  baris_sumber     integer NOT NULL,
  kode_blud        text NOT NULL,
  kode_pemda       text,
  rekening_blud_id bigint REFERENCES rekening(id),
  komponen         text NOT NULL,
  rincian          text,
  volume           numeric(18,4),
  satuan           text,
  harga            numeric(18,2),
  nilai_sumber     numeric(18,2) NOT NULL CHECK (nilai_sumber > 0),
  gelondongan      boolean NOT NULL DEFAULT false,
  label_unit       text,
  unit_id          bigint REFERENCES unit_kerja(id),
  usulan_id        bigint REFERENCES usulan_standar_harga(id),   -- usulan item paket untuk baris gelondongan
  status           text NOT NULL DEFAULT 'PERLU_DIRINCI'
                   CHECK (status IN ('PERLU_DIRINCI','PERLU_UNIT','SIAP','DIABAIKAN','GAGAL')),
  alasan_abaikan   text,
  petunjuk         text[] NOT NULL DEFAULT '{}',
  peringatan       text[] NOT NULL DEFAULT '{}',
  diubah_pada      timestamptz NOT NULL DEFAULT now(),
  row_version      integer NOT NULL DEFAULT 1,
  CHECK (status <> 'DIABAIKAN' OR length(btrim(coalesce(alasan_abaikan, ''))) >= 10)
);
CREATE INDEX ON staging_rba (impor_rba_id, status);
CREATE TRIGGER trg_staging_rba_rv BEFORE UPDATE ON staging_rba FOR EACH ROW EXECUTE FUNCTION fn_row_version();

-- Hasil pemecahan baris gelondongan menjadi item standar harga (TDD 8.4 butir 3).
CREATE TABLE staging_rba_item (
  id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  staging_id       bigint NOT NULL REFERENCES staging_rba(id) ON DELETE CASCADE,
  item_id          bigint NOT NULL REFERENCES standar_harga_item(id),
  uraian           text NOT NULL,
  volume           numeric(18,4) NOT NULL CHECK (volume > 0),
  harga            numeric(18,2) NOT NULL CHECK (harga > 0),
  nilai            numeric(18,2) GENERATED ALWAYS AS (round(volume * harga, 2)) STORED,
  unit_id          bigint REFERENCES unit_kerja(id)
);
CREATE INDEX ON staging_rba_item (staging_id);

-- Harga pecahan tidak boleh melebihi harga item standar (aturan penyusunan RBA, PRD 7.4).
CREATE OR REPLACE FUNCTION fn_staging_item_harga() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  IF NEW.harga > (SELECT harga FROM standar_harga_item WHERE id = NEW.item_id) THEN
    RAISE EXCEPTION 'HARGA_DI_ATAS_STANDAR: harga pecahan melebihi harga item standar' USING ERRCODE = 'P0001';
  END IF;
  RETURN NEW;
END $$;
CREATE TRIGGER trg_staging_item_harga BEFORE INSERT OR UPDATE ON staging_rba_item
FOR EACH ROW EXECUTE FUNCTION fn_staging_item_harga();
