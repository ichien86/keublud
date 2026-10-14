-- 050: Paket revisi rincian (PRD 7.7)

CREATE TABLE revisi_paket (
  id                     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid                   uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  nomor                  text UNIQUE,
  pagu_jenis_id          bigint NOT NULL REFERENCES pagu_jenis(id),
  status                 text NOT NULL DEFAULT 'DRAF'
                         CHECK (status IN ('DRAF','BERLAKU','MENUNGGU_PERENCANA','MENUNGGU_PERGESERAN',
                                           'DITOLAK','GUGUR','DIBATALKAN')),
  alasan_menunggu        text CHECK (alasan_menunggu IN ('DI_ATAS_STANDAR','JENIS_PERLU_PERSETUJUAN')),
  total_sebelum          numeric(18,2) NOT NULL DEFAULT 0,
  total_sesudah          numeric(18,2) NOT NULL DEFAULT 0,
  selisih                numeric(18,2) GENERATED ALWAYS AS (total_sesudah - total_sebelum) STORED,
  memuat_di_atas_standar boolean NOT NULL DEFAULT false,
  permintaan_id          bigint,                 -- FK ditambahkan di 060
  alasan                 text,
  dibuat_oleh            bigint NOT NULL,
  dibuat_pada            timestamptz NOT NULL DEFAULT now(),
  diubah_pada            timestamptz NOT NULL DEFAULT now(),
  diterapkan_pada        timestamptz,
  row_version            integer NOT NULL DEFAULT 1,
  CHECK (status <> 'MENUNGGU_PERGESERAN' OR permintaan_id IS NOT NULL),
  CHECK (status <> 'MENUNGGU_PERENCANA' OR alasan_menunggu IS NOT NULL)
);
CREATE UNIQUE INDEX revisi_satu_draf ON revisi_paket (pagu_jenis_id, dibuat_oleh) WHERE status = 'DRAF';
CREATE INDEX ON revisi_paket (status);
CREATE TRIGGER trg_revisi_paket_rv BEFORE UPDATE ON revisi_paket FOR EACH ROW EXECUTE FUNCTION fn_row_version();

CREATE TABLE revisi_detail (
  id                   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  paket_id             bigint NOT NULL REFERENCES revisi_paket(id),
  aksi                 text NOT NULL CHECK (aksi IN ('TAMBAH','UBAH','HAPUS')),
  rincian_id           bigint REFERENCES rba_rincian(id),
  rincian_row_version  integer,
  sebelum              jsonb,
  sesudah              jsonb,
  di_atas_standar      boolean NOT NULL DEFAULT false,
  CHECK ((aksi = 'TAMBAH' AND rincian_id IS NULL AND sesudah IS NOT NULL)
      OR (aksi = 'UBAH'   AND rincian_id IS NOT NULL AND sesudah IS NOT NULL)
      OR (aksi = 'HAPUS'  AND rincian_id IS NOT NULL))
);
CREATE INDEX ON revisi_detail (paket_id);
CREATE UNIQUE INDEX revisi_detail_unik_rincian ON revisi_detail (paket_id, rincian_id) WHERE rincian_id IS NOT NULL;

ALTER TABLE rba_rincian
  ADD CONSTRAINT rincian_terkunci_paket_fk FOREIGN KEY (terkunci_paket_id) REFERENCES revisi_paket(id);
