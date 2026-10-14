-- 070: Mesin persetujuan generik (TDD bagian 5)

CREATE TABLE workflow_definisi (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  objek_tipe    text NOT NULL,      -- RBA, REVISI_DI_ATAS_STANDAR, REVISI_PERLU_PERSETUJUAN, USULAN_STANDAR,
                                    -- PASANGAN_PERGESERAN, PERUBAHAN_RBA, DOKUMEN_ANGGARAN, ...
  versi         integer NOT NULL,
  aktif         boolean NOT NULL DEFAULT true,
  UNIQUE (objek_tipe, versi)
);
CREATE UNIQUE INDEX workflow_satu_aktif ON workflow_definisi (objek_tipe) WHERE aktif;

CREATE TABLE workflow_langkah (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  definisi_id     bigint NOT NULL REFERENCES workflow_definisi(id),
  urutan          smallint NOT NULL,
  nama            text NOT NULL,
  permission      text NOT NULL,
  cakupan         text NOT NULL CHECK (cakupan IN ('UNIT_PENGUSUL','UNIT_ANGGOTA','GLOBAL')),
  nilai_minimum   numeric(18,2),
  nilai_maksimum  numeric(18,2),
  boleh_otomatis  boolean NOT NULL DEFAULT false,
  UNIQUE (definisi_id, urutan)
);

CREATE TABLE persetujuan (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  uuid            uuid NOT NULL UNIQUE DEFAULT gen_random_uuid(),
  objek_tipe      text NOT NULL,
  objek_id        bigint NOT NULL,
  definisi_id     bigint NOT NULL REFERENCES workflow_definisi(id),
  langkah_aktif   smallint,
  nilai           numeric(18,2),
  unit_id         bigint REFERENCES unit_kerja(id),
  status          text NOT NULL DEFAULT 'BERJALAN'
                  CHECK (status IN ('BERJALAN','DISETUJUI','DITOLAK','DIKEMBALIKAN','DIBATALKAN')),
  diajukan_oleh   bigint NOT NULL,
  dibuat_pada     timestamptz NOT NULL DEFAULT now(),
  row_version     integer NOT NULL DEFAULT 1,
  diubah_pada     timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX persetujuan_satu_berjalan ON persetujuan (objek_tipe, objek_id) WHERE status = 'BERJALAN';
CREATE TRIGGER trg_persetujuan_rv BEFORE UPDATE ON persetujuan FOR EACH ROW EXECUTE FUNCTION fn_row_version();

CREATE TABLE persetujuan_aksi (
  id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  persetujuan_id  bigint NOT NULL REFERENCES persetujuan(id),
  urutan_langkah  smallint,
  aksi            text NOT NULL CHECK (aksi IN ('AJUKAN','SETUJUI','TOLAK','KEMBALIKAN','SETUJUI_OTOMATIS','BATALKAN')),
  pelaku_id       bigint,                  -- NULL untuk SETUJUI_OTOMATIS
  atas_nama_id    bigint,                  -- pejabat definitif bila pelaku adalah delegasi
  kanal           text NOT NULL DEFAULT 'WEB' CHECK (kanal IN ('WEB','MOBILE','SISTEM')),
  metode_konfirmasi text CHECK (metode_konfirmasi IN ('WEBAUTHN','PIN')),
  catatan         text,
  dibuat_pada     timestamptz NOT NULL DEFAULT now(),
  CHECK (aksi NOT IN ('TOLAK','KEMBALIKAN') OR length(btrim(coalesce(catatan, ''))) >= 10),
  CHECK (aksi <> 'SETUJUI_OTOMATIS' OR (pelaku_id IS NULL AND kanal = 'SISTEM')),
  CHECK (pelaku_id IS NOT NULL OR kanal = 'SISTEM')
);
CREATE INDEX ON persetujuan_aksi (persetujuan_id);
CREATE TRIGGER trg_persetujuan_aksi_append BEFORE UPDATE OR DELETE ON persetujuan_aksi
FOR EACH ROW EXECUTE FUNCTION fn_tolak_ubah();
