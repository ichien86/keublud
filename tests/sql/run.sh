#!/usr/bin/env bash
# Menjalankan uji skema database pada PostgreSQL kosong.
# Pemakaian: PGHOST=... PGPORT=... PGUSER=... tests/sql/run.sh
set -euo pipefail
cd "$(dirname "$0")/../.."

DB="${DB_UJI:-blud_uji_sql}"
dropdb --if-exists "$DB" >/dev/null
createdb "$DB"

for f in database/sql/*.sql; do
  psql -X -q -v ON_ERROR_STOP=1 -d "$DB" -f "$f"
done
psql -X -q -v ON_ERROR_STOP=1 -d "$DB" -f tests/sql/00_fixture.sql >/dev/null

HASIL=$(psql -X -q -d "$DB" -f tests/sql/10_aturan.sql 2>&1 | grep -E '^(PASS|FAIL)' || true)
echo "$HASIL"
LULUS=$(grep -c '^PASS' <<<"$HASIL" || true)
GAGAL=$(grep -c '^FAIL' <<<"$HASIL" || true)
echo "----"
echo "Lulus: $LULUS  Gagal: $GAGAL"
[ "$GAGAL" -eq 0 ]
