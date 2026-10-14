-- 080: Audit log append-only dengan rantai hash (TDD 3.10)

CREATE TABLE audit_log (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  waktu         timestamptz NOT NULL DEFAULT clock_timestamp(),
  pengguna_id   bigint,
  aktor_jenis   text NOT NULL CHECK (aktor_jenis IN ('PENGGUNA','SISTEM')),
  atas_nama_id  bigint,
  tindakan      text NOT NULL,
  tabel         text,
  objek_id      bigint,
  nilai_lama    jsonb,
  nilai_baru    jsonb,
  alasan        text,
  ip            inet,
  request_id    uuid,
  hash_sebelum  bytea,
  hash          bytea NOT NULL
);
CREATE INDEX ON audit_log (tabel, objek_id);
CREATE INDEX ON audit_log (waktu);
CREATE TRIGGER trg_audit_append BEFORE UPDATE OR DELETE ON audit_log
FOR EACH ROW EXECUTE FUNCTION fn_tolak_ubah();

CREATE OR REPLACE FUNCTION fn_audit_hash_isi(a audit_log) RETURNS bytea
LANGUAGE sql STABLE
AS $$
  SELECT sha256(convert_to(concat_ws('|',
    coalesce(encode(a.hash_sebelum, 'hex'), ''), to_char(a.waktu AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US'), coalesce(a.pengguna_id::text, ''), a.aktor_jenis,
    coalesce(a.atas_nama_id::text, ''), a.tindakan, coalesce(a.tabel, ''), coalesce(a.objek_id::text, ''),
    coalesce(a.nilai_lama::text, ''), coalesce(a.nilai_baru::text, ''), coalesce(a.alasan, '')), 'UTF8'))
$$;

-- Mengisi hash berantai. Kunci advisori menserialkan penulisan audit agar rantai tidak bercabang.
CREATE OR REPLACE FUNCTION fn_audit_rantai() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  PERFORM pg_advisory_xact_lock(hashtext('audit_log_rantai'));
  SELECT hash INTO NEW.hash_sebelum FROM audit_log ORDER BY id DESC LIMIT 1;
  NEW.hash := fn_audit_hash_isi(NEW);
  RETURN NEW;
END $$;

CREATE TRIGGER trg_audit_rantai BEFORE INSERT ON audit_log
FOR EACH ROW EXECUTE FUNCTION fn_audit_rantai();

-- Pemicu audit generik untuk tabel penting.
CREATE OR REPLACE FUNCTION fn_audit() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  INSERT INTO audit_log (pengguna_id, aktor_jenis, atas_nama_id, tindakan, tabel, objek_id,
                         nilai_lama, nilai_baru, alasan, request_id, hash)
  VALUES (
    app_user_id(),
    CASE WHEN app_user_id() IS NULL THEN 'SISTEM' ELSE app_aktor_jenis() END,
    NULLIF(current_setting('app.atas_nama_id', true), '')::bigint,
    TG_OP, TG_TABLE_NAME,
    CASE WHEN TG_OP = 'DELETE' THEN OLD.id ELSE NEW.id END,
    CASE WHEN TG_OP IN ('UPDATE','DELETE') THEN to_jsonb(OLD) END,
    CASE WHEN TG_OP IN ('INSERT','UPDATE') THEN to_jsonb(NEW) END,
    NULLIF(current_setting('app.alasan', true), ''),
    NULLIF(current_setting('app.request_id', true), '')::uuid,
    '\x00'::bytea   -- ditimpa fn_audit_rantai
  );
  RETURN NULL;
END $$;

DO $$
DECLARE t text;
BEGIN
  FOREACH t IN ARRAY ARRAY['unit_kerja','rekening','pemetaan_rekening','standar_harga_item','usulan_standar_harga',
                           'rba','rba_rincian','pagu_jenis','revisi_paket','permintaan_pergeseran',
                           'pasangan_pergeseran','pasangan_anggota','persetujuan','delegasi','konfigurasi']
  LOOP
    IF t = 'pasangan_anggota' OR t = 'konfigurasi' THEN
      CONTINUE;  -- tanpa kolom id tunggal; dicatat lewat AuditService
    END IF;
    EXECUTE format('CREATE TRIGGER trg_audit AFTER INSERT OR UPDATE OR DELETE ON %I
                    FOR EACH ROW EXECUTE FUNCTION fn_audit()', t);
  END LOOP;
END $$;

-- Verifikasi rantai: mengembalikan id baris pertama yang rusak (NULL bila utuh).
CREATE OR REPLACE FUNCTION fn_audit_verifikasi() RETURNS bigint
LANGUAGE plpgsql STABLE
AS $$
DECLARE
  r audit_log%ROWTYPE;
  v_prev bytea := NULL;
BEGIN
  FOR r IN SELECT * FROM audit_log ORDER BY id LOOP
    IF r.hash_sebelum IS DISTINCT FROM v_prev OR r.hash <> fn_audit_hash_isi(r) THEN
      RETURN r.id;
    END IF;
    v_prev := r.hash;
  END LOOP;
  RETURN NULL;
END $$;
