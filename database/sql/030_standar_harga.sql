-- 030: Standar harga Pemda & internal BLUD, usulan, pengecekan kecocokan

CREATE TABLE standar_harga_impor (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  impor_batch_id  bigint NOT NULL REFERENCES impor_batch(id),
  tahun           smallint NOT NULL,
  sumber_dokumen  text NOT NULL,       -- mis. nomor Perkada SSH
  versi           integer NOT NULL,
  dikonfirmasi_oleh bigint,
  dikonfirmasi_pada timestamptz,
  UNIQUE (tahun, versi)
);

CREATE TABLE usulan_standar_batch (
  id                   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid                 uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  impor_batch_id       bigint NOT NULL REFERENCES impor_batch(id),
  tahun                smallint NOT NULL,
  diajukan_oleh        bigint NOT NULL,
  jumlah_baris         integer NOT NULL DEFAULT 0,
  jumlah_tanpa_cocok   integer NOT NULL DEFAULT 0,
  jumlah_cocok_sedang  integer NOT NULL DEFAULT 0,
  jumlah_cocok_tinggi  integer NOT NULL DEFAULT 0,
  status               text NOT NULL DEFAULT 'DRAF'
                       CHECK (status IN ('DRAF','DIAJUKAN','SEBAGIAN_DIPUTUSKAN','SELESAI')),
  bukti_lampiran_id    bigint REFERENCES lampiran(id),
  dibuat_pada          timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE usulan_standar_harga (
  id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid              uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  tahun             smallint NOT NULL,
  batch_id          bigint REFERENCES usulan_standar_batch(id),
  asal              text NOT NULL DEFAULT 'FORMULIR' CHECK (asal IN ('FORMULIR','IMPOR')),
  konteks           text NOT NULL DEFAULT 'PENYUSUNAN' CHECK (konteks IN ('PENYUSUNAN','REVISI','TRANSAKSI')),
  konteks_url       text,                -- halaman asal untuk kembali setelah disahkan
  kode_barang       text,
  uraian            text NOT NULL,
  spesifikasi       text,
  satuan            text NOT NULL,
  harga             numeric(18,2) NOT NULL CHECK (harga > 0),
  kategori          text,
  rekening_ids      bigint[],
  kategori_cocok    text CHECK (kategori_cocok IN ('TANPA','RENDAH','SEDANG','TINGGI')),
  status            text NOT NULL DEFAULT 'DRAF'
                    CHECK (status IN ('DRAF','DIAJUKAN','DIKEMBALIKAN','DITOLAK_PAKAI_ITEM','DISAHKAN')),
  item_rujukan_id   bigint,              -- item yang dipakai bila DITOLAK_PAKAI_ITEM
  alasan_keputusan  text,
  diajukan_oleh     bigint NOT NULL,
  diputuskan_oleh   bigint,
  diputuskan_pada   timestamptz,
  dibuat_pada       timestamptz NOT NULL DEFAULT now(),
  CHECK (status <> 'DITOLAK_PAKAI_ITEM' OR item_rujukan_id IS NOT NULL)
);
CREATE INDEX ON usulan_standar_harga (status, tahun);
CREATE INDEX ON usulan_standar_harga (batch_id);

CREATE TABLE standar_harga_item (
  id                    bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tahun                 smallint NOT NULL,
  sumber                text NOT NULL CHECK (sumber IN ('PEMDA','BLUD')),
  impor_id              bigint REFERENCES standar_harga_impor(id),
  usulan_id             bigint REFERENCES usulan_standar_harga(id),
  kode_barang           text,
  uraian                text NOT NULL,
  spesifikasi           text,
  satuan                text NOT NULL,
  harga                 numeric(18,2) NOT NULL CHECK (harga > 0),
  rekening_ids          bigint[],
  uraian_norm           text GENERATED ALWAYS AS
                          (lower(unaccent_immutable(uraian || ' ' || coalesce(spesifikasi, '')))) STORED,
  status                text NOT NULL DEFAULT 'AKTIF' CHECK (status IN ('AKTIF','NONAKTIF')),
  berlaku_mulai         date NOT NULL,
  tambahan_tengah_tahun boolean NOT NULL DEFAULT false,
  dibuat_pada           timestamptz NOT NULL DEFAULT now(),
  CHECK ((sumber = 'PEMDA' AND impor_id IS NOT NULL) OR (sumber = 'BLUD' AND usulan_id IS NOT NULL))
);
CREATE UNIQUE INDEX standar_unik_kode ON standar_harga_item (tahun, sumber, kode_barang, satuan) WHERE kode_barang IS NOT NULL;
CREATE INDEX standar_uraian_trgm ON standar_harga_item USING gin (uraian_norm gin_trgm_ops);
CREATE INDEX ON standar_harga_item (tahun, status);

ALTER TABLE usulan_standar_harga
  ADD CONSTRAINT usulan_item_rujukan_fk FOREIGN KEY (item_rujukan_id) REFERENCES standar_harga_item(id);

CREATE TABLE usulan_kecocokan (
  usulan_id      bigint NOT NULL REFERENCES usulan_standar_harga(id),
  item_id        bigint NOT NULL REFERENCES standar_harga_item(id),
  tahap          text NOT NULL CHECK (tahap IN ('DIAJUKAN','DIPUTUSKAN')),
  skor           numeric(5,4) NOT NULL CHECK (skor BETWEEN 0 AND 1),
  rincian_skor   jsonb NOT NULL,
  PRIMARY KEY (usulan_id, item_id, tahap)
);

-- Harga, satuan, dan spesifikasi item terkunci setelah RBA tahun tersebut definitif.
-- Menambah item baru di tengah tahun tetap diizinkan (PRD 7.4 butir 7).
CREATE OR REPLACE FUNCTION fn_standar_kunci_harga() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  IF (NEW.harga, NEW.satuan, NEW.spesifikasi) IS DISTINCT FROM (OLD.harga, OLD.satuan, OLD.spesifikasi)
     AND EXISTS (SELECT 1 FROM rba WHERE tahun = OLD.tahun AND status IN ('DEFINITIF','DIGANTIKAN')) THEN
    RAISE EXCEPTION 'STANDAR_HARGA_TERKUNCI: harga/satuan/spesifikasi item % tidak dapat diubah setelah RBA % definitif',
      OLD.id, OLD.tahun USING ERRCODE = 'P0001';
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_standar_kunci_harga BEFORE UPDATE ON standar_harga_item
FOR EACH ROW EXECUTE FUNCTION fn_standar_kunci_harga();

CREATE TRIGGER trg_standar_tanpa_hapus BEFORE DELETE ON standar_harga_item
FOR EACH ROW EXECUTE FUNCTION fn_tolak_ubah();
