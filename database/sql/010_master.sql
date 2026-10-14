-- 010: Master data, konfigurasi, kalender, delegasi, penomoran, impor
-- Kolom pengguna (dibuat_oleh, dsb.) merujuk users.id tanpa FK agar skema ini
-- dapat diuji mandiri; integritasnya dijaga aplikasi.

CREATE TABLE unit_kerja (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  kode          text NOT NULL UNIQUE,
  nama          text NOT NULL,
  jenis         text NOT NULL DEFAULT 'UNIT'
                CHECK (jenis IN ('BAGIAN','INSTALASI','POLI','RUANG','UNIT')),
  induk_id      bigint REFERENCES unit_kerja(id),
  aktif         boolean NOT NULL DEFAULT true,
  dibuat_pada   timestamptz NOT NULL DEFAULT now(),
  diubah_pada   timestamptz NOT NULL DEFAULT now(),
  row_version   integer NOT NULL DEFAULT 1,
  CHECK (induk_id IS NULL OR induk_id <> id)
);
CREATE TRIGGER trg_unit_kerja_rv BEFORE UPDATE ON unit_kerja FOR EACH ROW EXECUTE FUNCTION fn_row_version();

CREATE TABLE user_unit (
  user_id       bigint NOT NULL,
  unit_id       bigint NOT NULL REFERENCES unit_kerja(id),
  PRIMARY KEY (user_id, unit_id)
);

CREATE TABLE pemetaan_label_unit (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  label         text NOT NULL,
  label_norm    text GENERATED ALWAYS AS (lower(btrim(regexp_replace(label, '\s+', ' ', 'g')))) STORED,
  unit_id       bigint NOT NULL REFERENCES unit_kerja(id),
  UNIQUE (label_norm)
);

CREATE TABLE sumber_dana (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  kode          text NOT NULL UNIQUE,
  nama          text NOT NULL,
  kelompok      text NOT NULL CHECK (kelompok IN ('PENDAPATAN_BLUD','APBD','HIBAH','LAINNYA')),
  aktif         boolean NOT NULL DEFAULT true
);

CREATE TABLE konfigurasi (
  kunci         text PRIMARY KEY,
  nilai         jsonb NOT NULL,
  keterangan    text,
  diubah_oleh   bigint,
  diubah_pada   timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE kalender_libur (
  tanggal       date PRIMARY KEY,
  jenis         text NOT NULL CHECK (jenis IN ('LIBUR_NASIONAL','CUTI_BERSAMA','LIBUR_LOKAL')),
  keterangan    text NOT NULL
);

CREATE TABLE delegasi (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  pejabat_id    bigint NOT NULL,
  pelaksana_id  bigint NOT NULL,
  kewenangan    text[] NOT NULL,              -- daftar permission
  berlaku       tstzrange NOT NULL,
  dasar         text,
  dibuat_oleh   bigint,
  dibuat_pada   timestamptz NOT NULL DEFAULT now(),
  CHECK (pejabat_id <> pelaksana_id),
  EXCLUDE USING gist (pejabat_id WITH =, berlaku WITH &&)
);

CREATE TABLE nomor_dokumen (
  jenis         text NOT NULL,
  tahun         smallint NOT NULL,
  format        text NOT NULL,                -- token: {NO:4} {KODE} {UNIT} {BULAN_ROMAWI} {TAHUN}
  nomor_terakhir integer NOT NULL DEFAULT 0 CHECK (nomor_terakhir >= 0),
  PRIMARY KEY (jenis, tahun)
);

CREATE TABLE impor_batch (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid          uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  jenis         text NOT NULL,                -- STANDAR_PEMDA, USULAN_STANDAR, KODEFIKASI, PEMETAAN, RBA_LAMA, ...
  nama_file     text NOT NULL,
  path_file     text NOT NULL,
  sha256        text NOT NULL,
  status        text NOT NULL DEFAULT 'DIUNGGAH'
                CHECK (status IN ('DIUNGGAH','DIVALIDASI','PRATINJAU','DIKONFIRMASI','SELESAI','GAGAL','DIBATALKAN')),
  jumlah_baris  integer,
  jumlah_gagal  integer,
  dibuat_oleh   bigint NOT NULL,
  dibuat_pada   timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE impor_baris_gagal (
  impor_batch_id bigint NOT NULL REFERENCES impor_batch(id),
  baris         integer NOT NULL,
  kolom         text,
  pesan         text NOT NULL
);
CREATE INDEX ON impor_baris_gagal (impor_batch_id);

CREATE TABLE lampiran (
  id                 bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid               uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  lampiranable_type  text NOT NULL,
  lampiranable_id    bigint NOT NULL,
  jenis_dokumen      text NOT NULL,
  nama_asli          text NOT NULL,
  path               text NOT NULL,
  mime               text NOT NULL,
  ukuran             bigint NOT NULL CHECK (ukuran > 0),
  sha256             text NOT NULL,
  dibuat_oleh        bigint NOT NULL,
  dibuat_pada        timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON lampiran (lampiranable_type, lampiranable_id);
