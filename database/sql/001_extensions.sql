-- 001: Ekstensi dan fungsi bantu umum
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- unaccent() tidak IMMUTABLE sehingga tidak dapat dipakai di kolom generated/indeks.
CREATE OR REPLACE FUNCTION unaccent_immutable(text) RETURNS text
LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$;

-- Konteks pelaku untuk audit, diisi middleware aplikasi via set_config().
CREATE OR REPLACE FUNCTION app_user_id() RETURNS bigint
LANGUAGE sql STABLE
AS $$ SELECT NULLIF(current_setting('app.user_id', true), '')::bigint $$;

CREATE OR REPLACE FUNCTION app_aktor_jenis() RETURNS text
LANGUAGE sql STABLE
AS $$ SELECT COALESCE(NULLIF(current_setting('app.aktor_jenis', true), ''), 'SISTEM') $$;

-- Pemicu umum: menolak UPDATE/DELETE pada tabel append-only.
CREATE OR REPLACE FUNCTION fn_tolak_ubah() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  RAISE EXCEPTION 'Tabel % bersifat append-only; % tidak diizinkan', TG_TABLE_NAME, TG_OP
    USING ERRCODE = 'P0001';
END $$;

-- Pemicu umum: menaikkan row_version dan diubah_pada.
CREATE OR REPLACE FUNCTION fn_row_version() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  NEW.row_version := OLD.row_version + 1;
  NEW.diubah_pada := now();
  RETURN NEW;
END $$;
