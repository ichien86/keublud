<?php

declare(strict_types=1);

namespace App\Modules\ImporRba\Services;

use App\Models\User;
use App\Modules\ImporRba\Domain\HasilBacaRba;
use App\Modules\ImporRba\Domain\LampiranRbaParser;
use App\Modules\Kodefikasi\Services\PemetaanService;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Errors\KesalahanDomain;
use App\Modules\StandarHarga\Models\UsulanStandarBatch;
use App\Modules\StandarHarga\Services\UsulanStandarService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Impor RBA format lama (TDD 8.4): unggah → pilih sheet & kelompok nilai → staging → rincikan ke item
 * standar harga, petakan label unit, lengkapi kodefikasi → rekonsiliasi → RBA berstatus DRAF.
 * Aturan uang tetap dijaga database: harga pecahan ≤ standar, rincian RBA wajib merujuk item aktif.
 */
final class ImporRbaService
{
    public const JENIS_BATCH = 'RBA_LAMA';

    public function __construct(
        private readonly PembacaXlsx $pembaca,
        private readonly LampiranRbaParser $parser,
        private readonly PemetaanService $pemetaan,
        private readonly UsulanStandarService $usulan,
        private readonly AuditService $audit,
    ) {}

    // ------------------------------------------------------------------ unggah & pratinjau

    /** @return array{batch_uuid: string, sheet: list<string>} */
    public function unggah(UploadedFile $file, User $user): array
    {
        $this->pastikanBoleh($user);
        $path = $file->store('impor/rba');
        $sheet = $this->pembaca->daftarSheet(Storage::path($path));
        $uuid = DB::table('impor_batch')->insertGetId([
            'jenis' => self::JENIS_BATCH, 'nama_file' => $file->getClientOriginalName(), 'path_file' => $path,
            'sha256' => hash_file('sha256', $file->getRealPath()), 'status' => 'DIUNGGAH', 'dibuat_oleh' => $user->id,
        ], 'uuid');

        return ['batch_uuid' => (string) $uuid, 'sheet' => $sheet];
    }

    /** Membaca satu sheet tanpa menyimpan, untuk memilih kelompok nilai dan melihat rekonsiliasi awal. */
    public function pratinjau(string $batchUuid, string $sheet, int $kelompok, User $user): array
    {
        $this->pastikanBoleh($user);
        $h = $this->baca($this->batch($batchUuid), $sheet, $kelompok);

        return [
            'galat' => $h->galat,
            'kelompok' => $h->kelompok,
            'kelompok_dipakai' => $h->kelompokDipakai,
            'kepala' => $h->kepala,
            'tahun' => isset($h->kepala['Tahun Anggaran']) ? (int) $h->kepala['Tahun Anggaran']['kode'] : null,
            'jumlah_baris' => count($h->baris),
            'jumlah_gelondongan' => count(array_filter($h->baris, fn ($b) => $b->gelondongan)),
            'jumlah_nol' => count($h->nol),
            'total' => array_reduce($h->baris, fn (Uang $t, $b) => $t->tambah($b->jumlah), Uang::nol())->keDesimal(),
            'rekonsiliasi' => array_map(fn ($r) => [
                'kode' => $r['kode'], 'uraian' => $r['uraian'], 'file' => $r['file']->keDesimal(),
                'terbaca' => $r['terbaca']->keDesimal(), 'cocok' => $r['cocok'],
            ], $h->rekonsiliasi()),
            'peringatan' => array_slice($h->peringatan, 0, 50),
        ];
    }

    /** Menyimpan hasil baca ke staging. */
    public function mulai(string $batchUuid, string $sheet, int $kelompok, int $tahun, int $sumberDanaId, User $user): string
    {
        $this->pastikanBoleh($user);
        $batch = $this->batch($batchUuid);
        $h = $this->baca($batch, $sheet, $kelompok);
        if ($h->galat !== null) {
            throw new KesalahanDomain('FORMAT_TIDAK_DIKENALI', $h->galat);
        }
        if ($h->baris === []) {
            throw new KesalahanDomain('TIDAK_ADA_BARIS', 'Tidak ada komponen bernilai pada sheet dan kelompok nilai ini.');
        }

        return DB::transaction(function () use ($batch, $sheet, $tahun, $sumberDanaId, $user, $h) {
            $imporId = DB::table('impor_rba')->insertGetId([
                'impor_batch_id' => $batch->id, 'tahun' => $tahun, 'sheet' => $sheet, 'kelompok' => (string) $h->kelompokDipakai,
                'sumber_dana_id' => $sumberDanaId, 'kepala' => json_encode($h->kepala), 'kodefikasi' => json_encode($h->rekening),
                'peringatan' => $this->arrayPg($h->peringatan), 'dibuat_oleh' => $user->id,
            ]);
            foreach ($h->totalRekening as $kode => $total) {
                DB::table('impor_rba_rekening')->insert([
                    'impor_rba_id' => $imporId, 'kode_pemda' => $kode, 'uraian' => $h->uraianRekening[$kode] ?? '', 'total_file' => $total->keDesimal(),
                ]);
            }
            foreach ($h->baris as $b) {
                DB::table('staging_rba')->insert([
                    'impor_rba_id' => $imporId, 'baris_sumber' => $b->baris, 'kode_blud' => $b->kodeBlud, 'kode_pemda' => $b->kodePemda,
                    'komponen' => $b->komponen, 'rincian' => $b->rincian, 'volume' => $b->volume, 'satuan' => $b->satuan,
                    'harga' => $b->harga?->keDesimal(), 'nilai_sumber' => $b->jumlah->keDesimal(), 'gelondongan' => $b->gelondongan,
                    'label_unit' => $b->label, 'petunjuk' => $this->arrayPg($b->petunjuk), 'peringatan' => $this->arrayPg($b->peringatan),
                ]);
            }
            DB::table('impor_batch')->where('id', $batch->id)->update(['status' => 'PRATINJAU', 'jumlah_baris' => count($h->baris)]);
            $this->audit->tindakan('IMPOR_RBA_MULAI', 'impor_rba', $imporId, "{$batch->nama_file} / {$sheet}", ['tahun' => $tahun]);
            $this->segarkan($imporId);

            return (string) DB::table('impor_rba')->where('id', $imporId)->value('uuid');
        });
    }

    // ------------------------------------------------------------------ penyelesaian staging

    /**
     * Menghitung ulang rekening, unit dari label, item dari usulan paket yang sudah disahkan, dan status setiap baris.
     */
    public function segarkan(int $imporId): void
    {
        $impor = $this->impor($imporId);
        DB::update(
            "UPDATE staging_rba s SET rekening_blud_id = r.id
               FROM rekening r WHERE s.impor_rba_id = ? AND r.bagan = 'BLUD' AND r.kode = s.kode_blud AND r.tingkat = 6 AND r.aktif
                AND s.rekening_blud_id IS DISTINCT FROM r.id",
            [$imporId]
        );
        DB::update(
            "UPDATE staging_rba s SET unit_id = p.unit_id
               FROM pemetaan_label_unit p
              WHERE s.impor_rba_id = ? AND s.unit_id IS NULL AND s.label_unit IS NOT NULL
                AND p.label_norm = lower(btrim(regexp_replace(s.label_unit, '\\s+', ' ', 'g')))",
            [$imporId]
        );

        // Usulan item paket yang sudah disahkan → satu item pecahan otomatis.
        $siap = DB::select(
            "SELECT s.id, s.nilai_sumber::text AS nilai, s.unit_id, i.id AS item_id, i.uraian, i.harga::text AS harga
               FROM staging_rba s JOIN standar_harga_item i ON i.usulan_id = s.usulan_id AND i.status = 'AKTIF'
              WHERE s.impor_rba_id = ? AND NOT EXISTS (SELECT 1 FROM staging_rba_item x WHERE x.staging_id = s.id)",
            [$imporId]
        );
        foreach ($siap as $s) {
            $volume = $this->volumeDari(Uang::dari($s->nilai), Uang::dari($s->harga));
            if ($volume !== null) {
                DB::table('staging_rba_item')->insert(['staging_id' => $s->id, 'item_id' => $s->item_id, 'uraian' => $s->uraian, 'volume' => $volume, 'harga' => $s->harga]);
            }
        }

        DB::update(
            "UPDATE staging_rba s SET status = x.status_baru
               FROM (
                 SELECT s.id,
                        CASE WHEN s.status = 'DIABAIKAN' THEN 'DIABAIKAN'
                             WHEN s.rekening_blud_id IS NULL THEN 'GAGAL'
                             WHEN coalesce(t.total, 0) <> s.nilai_sumber THEN 'PERLU_DIRINCI'
                             WHEN t.tanpa_unit > 0 AND s.unit_id IS NULL THEN 'PERLU_UNIT'
                             ELSE 'SIAP' END AS status_baru
                   FROM staging_rba s
                   LEFT JOIN (SELECT staging_id, sum(nilai) AS total, count(*) FILTER (WHERE unit_id IS NULL) AS tanpa_unit
                                FROM staging_rba_item GROUP BY staging_id) t ON t.staging_id = s.id
                  WHERE s.impor_rba_id = ?
               ) x
              WHERE x.id = s.id AND s.status IS DISTINCT FROM x.status_baru",
            [$imporId]
        );
        unset($impor);
    }

    /**
     * Melengkapi bagan rekening dari kodefikasi yang tertulis di file (rekening Pemda, rekening BLUD,
     * dan pemetaan BLUD → Pemda tingkat 6). Rekening yang sudah ada tidak diubah.
     *
     * @return array{pemda: int, blud: int, pemetaan: int}
     */
    public function lengkapiKodefikasi(int $imporId, User $user): array
    {
        if (! $user->can('kodefikasi.kelola')) {
            throw new KesalahanDomain('TIDAK_BERWENANG', 'Melengkapi kodefikasi memerlukan kewenangan pengelola kodefikasi (administrator).', statusHttp: 403);
        }
        $impor = $this->impor($imporId);
        $file = json_decode((string) $impor->kodefikasi, true) ?: ['PEMDA' => [], 'BLUD' => []];

        return DB::transaction(function () use ($impor, $file) {
            $tambah = ['pemda' => 0, 'blud' => 0, 'pemetaan' => 0];
            $versi = ['PEMDA' => $this->versi('PEMDA', (int) $impor->tahun), 'BLUD' => $this->versi('BLUD', (int) $impor->tahun)];

            foreach ($file['PEMDA'] as $kode => $r) {
                $tambah['pemda'] += $this->pastikanRekening($versi['PEMDA'], 'PEMDA', (string) $kode, $r['uraian'], $file['PEMDA']);
            }
            foreach ($file['BLUD'] as $kode => $r) {
                // tingkat 1–3 BLUD meniru bagan Pemda
                $tambah['blud'] += $this->pastikanRekening($versi['BLUD'], 'BLUD', (string) $kode, $r['uraian'], $file['BLUD'] + $file['PEMDA']);
            }
            foreach ($file['BLUD'] as $kode => $r) {
                if (($r['tingkat'] ?? 0) !== 6 || empty($r['pemda'])) {
                    continue;
                }
                $blud = DB::table('rekening')->where('versi_id', $versi['BLUD'])->where('kode', $kode)->value('id');
                $pemda = DB::table('rekening')->where('versi_id', $versi['PEMDA'])->where('kode', $r['pemda'])->value('id');
                if ($blud && $pemda && ! DB::table('pemetaan_rekening')->where('rekening_blud_id', $blud)->exists()) {
                    DB::table('rekening')->whereIn('id', [$blud, $pemda])->update(['dapat_dianggarkan' => true]);
                    DB::table('pemetaan_rekening')->insert(['rekening_blud_id' => $blud, 'rekening_pemda_id' => $pemda, 'berlaku' => '['.$impor->tahun.'-01-01,)']);
                    $tambah['pemetaan']++;
                }
            }
            $this->audit->tindakan('IMPOR_RBA_KODEFIKASI', 'impor_rba', (int) $impor->id, null, $tambah);
            $this->segarkan((int) $impor->id);

            return $tambah;
        });
    }

    public function petakanLabel(int $imporId, string $label, ?int $unitId, ?string $unitBaru, User $user): void
    {
        $this->pastikanBoleh($user);
        DB::transaction(function () use ($imporId, $label, $unitId, $unitBaru) {
            if ($unitId === null) {
                $nama = trim((string) $unitBaru) ?: $label;
                $kode = mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $nama) ?: 'UNIT', 0, 8)).'-'.random_int(100, 999);
                $unitId = (int) DB::table('unit_kerja')->insertGetId(['kode' => $kode, 'nama' => $nama, 'jenis' => 'UNIT']);
            }
            DB::statement(
                'INSERT INTO pemetaan_label_unit (label, unit_id) VALUES (?, ?)
                 ON CONFLICT (label_norm) DO UPDATE SET unit_id = EXCLUDED.unit_id',
                [$label, $unitId]
            );
            // Label baru berlaku untuk baris yang unitnya belum ditetapkan manual.
            DB::update("UPDATE staging_rba SET unit_id = ? WHERE impor_rba_id = ? AND lower(btrim(regexp_replace(label_unit, '\\s+', ' ', 'g'))) = lower(btrim(regexp_replace(?, '\\s+', ' ', 'g')))",
                [$unitId, $imporId, $label]);
            $this->segarkan($imporId);
        });
    }

    /**
     * Mengganti pecahan item satu baris staging.
     *
     * @param  list<array{item_id:int, volume:string, harga?:string|null, unit_id?:int|null}>  $item
     */
    public function simpanItem(int $stagingId, array $item, ?int $unitId, User $user): void
    {
        $this->pastikanBoleh($user);
        $s = DB::table('staging_rba as s')->join('impor_rba as i', 'i.id', '=', 's.impor_rba_id')
            ->where('s.id', $stagingId)->first(['s.*', 'i.tahun', 'i.status as status_impor']);
        if ($s === null || $s->status_impor !== 'STAGING') {
            throw new KesalahanDomain('STAGING_TIDAK_AKTIF', 'Baris impor tidak ditemukan atau impor sudah selesai.', statusHttp: 409);
        }

        DB::transaction(function () use ($s, $item, $unitId) {
            DB::table('staging_rba_item')->where('staging_id', $s->id)->delete();
            foreach ($item as $i) {
                $std = DB::table('standar_harga_item')->where('id', $i['item_id'])->first();
                if ($std === null || $std->status !== 'AKTIF' || (int) $std->tahun !== (int) $s->tahun) {
                    throw new KesalahanDomain('ITEM_TIDAK_BERLAKU', "Item standar harus aktif untuk tahun {$s->tahun}.");
                }
                $harga = Uang::dari((string) ($i['harga'] ?? $std->harga));
                if ($harga->lebihBesar(Uang::dari((string) $std->harga))) {
                    throw new KesalahanDomain('HARGA_DI_ATAS_STANDAR', "Harga {$std->uraian} melebihi harga standar ".Uang::dari((string) $std->harga)->format().'.');
                }
                if (! preg_match('/^\d+(\.\d{1,4})?$/', (string) $i['volume']) || (float) $i['volume'] <= 0) {
                    throw new KesalahanDomain('VOLUME_TIDAK_VALID', 'Volume harus lebih dari 0 dengan maksimal 4 desimal.');
                }
                DB::table('staging_rba_item')->insert([
                    'staging_id' => $s->id, 'item_id' => $std->id, 'uraian' => $std->uraian, 'volume' => $i['volume'],
                    'harga' => $harga->keDesimal(), 'unit_id' => $i['unit_id'] ?? null,
                ]);
            }
            DB::table('staging_rba')->where('id', $s->id)->update(['unit_id' => $unitId ?? $s->unit_id]);
            $this->segarkan((int) $s->impor_rba_id);
        });
    }

    public function abaikan(int $stagingId, ?string $alasan, User $user): void
    {
        $this->pastikanBoleh($user);
        $s = DB::table('staging_rba')->where('id', $stagingId)->first() ?? throw new KesalahanDomain('TIDAK_DITEMUKAN', 'Baris tidak ditemukan.', statusHttp: 404);
        if ($alasan === null) {
            DB::table('staging_rba')->where('id', $stagingId)->update(['status' => 'PERLU_DIRINCI', 'alasan_abaikan' => null]);
        } else {
            if (mb_strlen(trim($alasan)) < 10) {
                throw new KesalahanDomain('ALASAN_WAJIB', 'Alasan mengabaikan baris minimal 10 karakter.');
            }
            DB::table('staging_rba')->where('id', $stagingId)->update(['status' => 'DIABAIKAN', 'alasan_abaikan' => trim($alasan)]);
        }
        $this->segarkan((int) $s->impor_rba_id);
    }

    /**
     * Mengajukan baris yang belum dirinci sebagai usulan item standar harga BLUD (satu usulan per baris).
     * Baris gelondongan (1 tahun/paket) diusulkan bersatuan "paket" seharga nilai baris; baris bervolume
     * (mis. 1 unit Lasotronik) diusulkan dengan satuan dan harga satuannya. Setelah perencana lain mengesahkan,
     * baris terisi otomatis (segarkan()). Pemisahan tugas tetap berlaku: pengusul tidak dapat mengesahkan.
     *
     * @return array{diajukan: int}
     */
    public function ajukanUsulan(int $imporId, User $user): array
    {
        $this->pastikanBoleh($user);
        $impor = $this->impor($imporId);
        if ($impor->status !== 'STAGING') {
            throw new KesalahanDomain('STAGING_TIDAK_AKTIF', 'Impor ini sudah selesai atau dibatalkan.', statusHttp: 409);
        }
        $baris = DB::table('staging_rba')->where('impor_rba_id', $imporId)->whereNull('usulan_id')
            ->whereIn('status', ['PERLU_DIRINCI', 'PERLU_UNIT'])
            ->whereNotExists(fn ($q) => $q->from('staging_rba_item')->whereColumn('staging_id', 'staging_rba.id'))
            ->orderBy('baris_sumber')->get();
        if ($baris->isEmpty()) {
            return ['diajukan' => 0];
        }

        return DB::transaction(function () use ($impor, $baris, $user) {
            $batchId = $impor->usulan_batch_id ?? UsulanStandarBatch::query()->forceCreate([
                'impor_batch_id' => $impor->impor_batch_id, 'tahun' => $impor->tahun, 'diajukan_oleh' => $user->id, 'status' => 'DIAJUKAN',
            ])->id;
            DB::table('impor_rba')->where('id', $impor->id)->update(['usulan_batch_id' => $batchId]);

            foreach ($baris as $b) {
                $nilai = Uang::dari((string) $b->nilai_sumber);
                $harga = $b->harga !== null ? Uang::dari((string) $b->harga) : null;
                $persatuan = ! $b->gelondongan && $b->volume !== null && $b->satuan && $harga !== null && $harga->positif()
                    && $harga->kaliVolume((string) $b->volume)->sama($nilai);
                $u = $this->usulan->ajukan((int) $impor->tahun, [
                    'uraian' => mb_substr($b->rincian ? self::bagianAkhir($b->rincian) : $b->komponen, 0, 500),
                    'spesifikasi' => ($persatuan ? '' : 'Paket '.($b->satuan ?: 'tahunan').' — ')
                        ."impor RBA {$impor->sheet} baris {$b->baris_sumber}, rekening {$b->kode_blud}",
                    'satuan' => $persatuan ? (string) $b->satuan : 'paket',
                    'harga' => $persatuan ? $harga->keDesimal() : $nilai->keDesimal(),
                    'kategori' => 'Impor RBA', 'konteks' => 'PENYUSUNAN', 'konteks_url' => '/impor-rba/'.$impor->uuid,
                    'rekening_ids' => $b->rekening_blud_id ? [(int) $b->rekening_blud_id] : null,
                ], $user, (int) $batchId, 'IMPOR');
                DB::table('staging_rba')->where('id', $b->id)->update(['usulan_id' => $u->id]);
            }
            DB::update('UPDATE usulan_standar_batch SET jumlah_baris = (SELECT count(*) FROM usulan_standar_harga WHERE batch_id = ?) WHERE id = ?', [$batchId, $batchId]);

            return ['diajukan' => $baris->count()];
        });
    }

    /** Menetapkan unit untuk semua baris yang belum memiliki unit (label kosong atau belum dipetakan). */
    public function unitMassal(int $imporId, int $unitId, User $user): int
    {
        $this->pastikanBoleh($user);
        $n = DB::table('staging_rba')->where('impor_rba_id', $imporId)->whereNull('unit_id')->update(['unit_id' => $unitId]);
        $this->segarkan($imporId);

        return $n;
    }

    private static function bagianAkhir(string $jalur): string
    {
        $bagian = explode(' › ', $jalur);

        return (string) end($bagian);
    }

    // ------------------------------------------------------------------ rekonsiliasi & pembentukan RBA

    /** @return list<array{kode: string, uraian: string, file: string, staging: string, diabaikan: string, cocok: bool}> */
    public function rekonsiliasi(int $imporId): array
    {
        return array_map(fn ($r) => [
            'kode' => $r->kode_pemda, 'uraian' => $r->uraian, 'file' => $r->total_file, 'staging' => $r->staging,
            'diabaikan' => $r->diabaikan, 'cocok' => Uang::dari($r->total_file)->sama(Uang::dari($r->staging)->tambah(Uang::dari($r->diabaikan))),
        ], DB::select(
            "SELECT k.kode_pemda, k.uraian, k.total_file::text,
                    coalesce((SELECT sum(i.nilai) FROM staging_rba s JOIN staging_rba_item i ON i.staging_id = s.id
                               WHERE s.impor_rba_id = k.impor_rba_id AND s.kode_pemda = k.kode_pemda AND s.status <> 'DIABAIKAN'), 0)::text AS staging,
                    coalesce((SELECT sum(s.nilai_sumber) FROM staging_rba s
                               WHERE s.impor_rba_id = k.impor_rba_id AND s.kode_pemda = k.kode_pemda AND s.status = 'DIABAIKAN'), 0)::text AS diabaikan
               FROM impor_rba_rekening k WHERE k.impor_rba_id = ? ORDER BY k.kode_pemda",
            [$imporId]
        ));
    }

    /** Membentuk RBA DRAF dari staging yang seluruhnya SIAP/DIABAIKAN dan rekonsiliasinya cocok. */
    public function bentukRba(int $imporId, User $user): int
    {
        $this->pastikanBoleh($user);

        return DB::transaction(function () use ($imporId, $user) {
            $impor = DB::table('impor_rba')->where('id', $imporId)->lockForUpdate()->first();
            if ($impor === null || $impor->status !== 'STAGING') {
                throw new KesalahanDomain('STAGING_TIDAK_AKTIF', 'Impor ini sudah selesai atau dibatalkan.', statusHttp: 409);
            }
            $this->segarkan($imporId);
            $belum = DB::table('staging_rba')->where('impor_rba_id', $imporId)->whereNotIn('status', ['SIAP', 'DIABAIKAN'])->count();
            if ($belum > 0) {
                throw new KesalahanDomain('STAGING_BELUM_SIAP', "Masih ada {$belum} baris yang belum siap (perlu dirinci, perlu unit, atau rekening belum dikenal).");
            }
            $selisih = array_filter($this->rekonsiliasi($imporId), fn ($r) => ! $r['cocok']);
            if ($selisih !== []) {
                throw new KesalahanDomain('REKONSILIASI_TIDAK_COCOK', 'Total per rekening belum sama dengan file: '
                    .implode('; ', array_map(fn ($r) => $r['kode'], $selisih)).'.', ['selisih' => array_values($selisih)]);
            }
            if (DB::table('rba')->where('tahun', $impor->tahun)->where('status', 'DEFINITIF')->exists()) {
                throw new KesalahanDomain('SUDAH_ADA_DEFINITIF', "Tahun {$impor->tahun} sudah memiliki RBA definitif. Pilih tahun lain atau gunakan alur perubahan RBA.");
            }

            $versi = (int) DB::table('rba')->where('tahun', $impor->tahun)->max('versi') + 1;
            $rbaId = DB::table('rba')->insertGetId([
                'tahun' => $impor->tahun, 'versi' => $versi, 'jenis_versi' => 'AWAL', 'status' => 'DRAF', 'dibuat_oleh' => $user->id,
            ]);
            $tanggal = new \DateTimeImmutable($impor->tahun.'-01-01');
            $item = DB::table('staging_rba_item as i')->join('staging_rba as s', 's.id', '=', 'i.staging_id')
                ->join('standar_harga_item as h', 'h.id', '=', 'i.item_id')
                ->where('s.impor_rba_id', $imporId)->where('s.status', 'SIAP')->orderBy('s.baris_sumber')->orderBy('i.id')
                ->get(['i.*', 's.rekening_blud_id', 's.unit_id as unit_baris', 's.label_unit', 'h.satuan', 'h.harga as harga_standar']);
            foreach ($item as $i) {
                $map = $this->pemetaan->resolusi((int) $i->rekening_blud_id, $tanggal);
                DB::table('rba_rincian')->insert([
                    'rba_id' => $rbaId, 'unit_id' => $i->unit_id ?? $i->unit_baris, 'sumber_dana_id' => $impor->sumber_dana_id,
                    'rekening_blud_id' => $i->rekening_blud_id, 'rekening_pemda_id' => $map['rekening_pemda_id'], 'jenis_id' => $map['jenis_id'],
                    'item_id' => $i->item_id, 'uraian' => $i->uraian, 'volume' => $i->volume, 'satuan' => $i->satuan,
                    'harga_standar' => $i->harga_standar, 'harga' => $i->harga, 'label_lama' => $i->label_unit,
                ]);
            }
            DB::table('impor_rba')->where('id', $imporId)->update(['status' => 'SELESAI', 'rba_id' => $rbaId]);
            DB::table('impor_batch')->where('id', $impor->impor_batch_id)->update(['status' => 'SELESAI']);
            $this->audit->tindakan('IMPOR_RBA_SELESAI', 'rba', $rbaId, null, ['impor_rba_id' => $imporId, 'jumlah_rincian' => $item->count()]);

            return $rbaId;
        }, attempts: 3);
    }

    public function batalkan(int $imporId, User $user): void
    {
        $this->pastikanBoleh($user);
        DB::table('impor_rba')->where('id', $imporId)->where('status', 'STAGING')->update(['status' => 'DIBATALKAN']);
    }

    // ------------------------------------------------------------------ bantuan

    private function baca(object $batch, string $sheet, int $kelompok): HasilBacaRba
    {
        $path = Storage::path($batch->path_file);
        if (! in_array($sheet, $this->pembaca->daftarSheet($path), true)) {
            throw new KesalahanDomain('SHEET_TIDAK_ADA', "Sheet \"{$sheet}\" tidak ada di file.");
        }

        return $this->parser->baca($this->pembaca->baca($path, $sheet), $kelompok);
    }

    private function batch(string $uuid): object
    {
        return DB::table('impor_batch')->where('uuid', $uuid)->where('jenis', self::JENIS_BATCH)->first()
            ?? throw new KesalahanDomain('TIDAK_DITEMUKAN', 'File impor tidak ditemukan.', statusHttp: 404);
    }

    private function impor(int $id): object
    {
        return DB::table('impor_rba')->where('id', $id)->first()
            ?? throw new KesalahanDomain('TIDAK_DITEMUKAN', 'Impor RBA tidak ditemukan.', statusHttp: 404);
    }

    private function pastikanBoleh(User $user): void
    {
        if (! $user->can('rba.impor')) {
            throw new KesalahanDomain('TIDAK_BERWENANG', 'Anda tidak berwenang mengimpor RBA.', statusHttp: 403);
        }
    }

    private function versi(string $bagan, int $tahun): int
    {
        $id = DB::table('kodefikasi_versi')->where('bagan', $bagan)->where('tahun', '<=', $tahun)->where('status', 'AKTIF')->orderByDesc('tahun')->value('id');

        return (int) ($id ?? DB::table('kodefikasi_versi')->insertGetId(['tahun' => $tahun, 'bagan' => $bagan, 'status' => 'AKTIF', 'keterangan' => 'Dibuat dari impor RBA']));
    }

    /**
     * Membuat rekening beserta induknya bila belum ada. Uraian induk yang tidak tertulis di file diberi tanda.
     *
     * @param  array<string, array{uraian: string}>  $kamus
     */
    private function pastikanRekening(int $versiId, string $bagan, string $kode, string $uraian, array $kamus): int
    {
        if (DB::table('rekening')->where('versi_id', $versiId)->where('kode', $kode)->exists()) {
            return 0;
        }
        $bagian = explode('.', $kode);
        $dibuat = 0;
        $indukId = null;
        if (count($bagian) > 1) {
            $kodeInduk = implode('.', array_slice($bagian, 0, -1));
            $dibuat += $this->pastikanRekening($versiId, $bagan, $kodeInduk, $kamus[$kodeInduk]['uraian'] ?? "Rekening {$kodeInduk} (lengkapi uraian)", $kamus);
            $indukId = DB::table('rekening')->where('versi_id', $versiId)->where('kode', $kodeInduk)->value('id');
        }
        DB::table('rekening')->insert([
            'versi_id' => $versiId, 'bagan' => $bagan, 'kode' => $kode, 'uraian' => $uraian !== '' ? $uraian : "Rekening {$kode}",
            'tingkat' => count($bagian), 'induk_id' => $indukId,
        ]);

        return $dibuat + 1;
    }

    private function volumeDari(Uang $nilai, Uang $harga): ?string
    {
        if ($harga->adalahNol() || $nilai->sen % $harga->sen !== 0) {
            return null;
        }

        return (string) intdiv($nilai->sen, $harga->sen);
    }

    /** @param list<string> $nilai */
    private function arrayPg(array $nilai): string
    {
        return '{'.implode(',', array_map(fn ($v) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $v).'"', $nilai)).'}';
    }
}
