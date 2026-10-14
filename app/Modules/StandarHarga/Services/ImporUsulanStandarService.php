<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Services;

use App\Models\User;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Errors\KesalahanDomain;
use App\Modules\StandarHarga\Domain\SkorKecocokan;
use App\Modules\StandarHarga\Models\UsulanStandarBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Impor Excel usulan standar harga internal (PRD 7.4a, FR-104).
 * Kolom template (baris pertama = judul): kode_barang, uraian, spesifikasi, satuan, harga, kategori, rekening, referensi_bukti
 */
final class ImporUsulanStandarService
{
    public const KOLOM = ['kode_barang', 'uraian', 'spesifikasi', 'satuan', 'harga', 'kategori', 'rekening', 'referensi_bukti'];

    public function __construct(
        private readonly UsulanStandarService $usulan,
        private readonly StandarHargaMatcher $matcher,
    ) {}

    /**
     * Membaca file, memvalidasi per baris, menjalankan pencocokan, lalu mengembalikan pratinjau berkategori.
     *
     * @return array{batch_uuid: string, ringkasan: array<string, int>, baris: list<array<string, mixed>>}
     */
    public function pratinjau(UploadedFile $file, int $tahun, User $user): array
    {
        $lembar = Excel::toArray(null, $file)[0] ?? [];
        $judul = array_map(static fn ($v) => strtolower(trim((string) $v)), array_shift($lembar) ?? []);
        $hilang = array_diff(['uraian', 'satuan', 'harga'], $judul);
        if ($hilang !== []) {
            throw new KesalahanDomain('TEMPLATE_TIDAK_SESUAI', 'Kolom wajib tidak ditemukan: '.implode(', ', $hilang),
                tindakan: ['label' => 'Unduh template', 'url' => '/standar-harga/usulan/template']);
        }
        $idx = array_flip($judul);
        $sha = hash_file('sha256', $file->getRealPath());
        $path = $file->store('impor/usulan-standar');

        return DB::transaction(function () use ($lembar, $idx, $tahun, $user, $sha, $path, $file) {
            $batchId = DB::table('impor_batch')->insertGetId([
                'jenis' => 'USULAN_STANDAR', 'nama_file' => $file->getClientOriginalName(), 'path_file' => $path,
                'sha256' => $sha, 'status' => 'PRATINJAU', 'jumlah_baris' => count($lembar), 'dibuat_oleh' => $user->id,
            ]);
            $batch = UsulanStandarBatch::query()->forceCreate(['impor_batch_id' => $batchId, 'tahun' => $tahun, 'diajukan_oleh' => $user->id, 'status' => 'DRAF']);

            $hasil = [];
            $sudah = [];
            $hitung = ['TANPA' => 0, 'RENDAH' => 0, 'SEDANG' => 0, 'TINGGI' => 0, 'GAGAL' => 0];

            foreach ($lembar as $i => $row) {
                $nomor = $i + 2;
                $ambil = static fn (string $k) => isset($idx[$k]) ? trim((string) ($row[$idx[$k]] ?? '')) : '';
                $data = ['kode_barang' => $ambil('kode_barang') ?: null, 'uraian' => $ambil('uraian'), 'spesifikasi' => $ambil('spesifikasi') ?: null,
                         'satuan' => $ambil('satuan'), 'harga' => str_replace([',', ' '], ['', ''], $ambil('harga')), 'kategori' => $ambil('kategori') ?: null];

                $galat = $this->validasiBaris($data, $sudah);
                if ($galat !== null) {
                    DB::table('impor_baris_gagal')->insert(['impor_batch_id' => $batchId, 'baris' => $nomor, 'pesan' => $galat]);
                    $hasil[] = ['baris' => $nomor, 'kategori' => 'GAGAL', 'pesan' => $galat] + $data;
                    $hitung['GAGAL']++;

                    continue;
                }
                $cocok = $this->matcher->cari($tahun, $data['uraian'], $data['spesifikasi'], $data['satuan'], Uang::dari($data['harga']), $data['kode_barang']);
                $kategori = SkorKecocokan::kategoriTertinggi(array_column($cocok, 'kategori'));
                $hitung[$kategori]++;
                $hasil[] = ['baris' => $nomor, 'kategori' => $kategori, 'kecocokan' => array_slice($cocok, 0, 3)] + $data;
            }

            DB::table('impor_batch')->where('id', $batchId)->update(['jumlah_gagal' => $hitung['GAGAL']]);
            $batch->forceFill([
                'jumlah_baris' => count($lembar) - $hitung['GAGAL'],
                'jumlah_tanpa_cocok' => $hitung['TANPA'] + $hitung['RENDAH'], 'jumlah_cocok_sedang' => $hitung['SEDANG'], 'jumlah_cocok_tinggi' => $hitung['TINGGI'],
            ])->save();

            return ['batch_uuid' => $batch->uuid, 'ringkasan' => $hitung, 'baris' => $hasil];
        });
    }

    /**
     * Mengajukan baris valid dari pratinjau sebagai satu batch. $baris = baris pratinjau yang dipertahankan pengusul.
     *
     * @param list<array<string, mixed>> $baris
     */
    public function ajukan(UsulanStandarBatch $batch, array $baris, User $user): UsulanStandarBatch
    {
        if ($batch->status !== 'DRAF' || (int) $batch->diajukan_oleh !== (int) $user->id) {
            throw new KesalahanDomain('STATUS_TIDAK_SESUAI', 'Batch tidak dapat diajukan.', statusHttp: 409);
        }

        return DB::transaction(function () use ($batch, $baris, $user) {
            $sudah = [];
            foreach ($baris as $b) {
                if (($galat = $this->validasiBaris($b, $sudah)) !== null) {
                    throw new KesalahanDomain('BARIS_TIDAK_VALID', "Baris {$b['baris']}: {$galat}");
                }
                $this->usulan->ajukan($batch->tahun, $b + ['konteks' => 'PENYUSUNAN'], $user, $batch->id, 'IMPOR');
            }
            $batch->forceFill(['status' => 'DIAJUKAN', 'jumlah_baris' => count($baris)])->save();
            DB::table('impor_batch')->where('id', $batch->impor_batch_id)->update(['status' => 'DIKONFIRMASI']);

            return $batch->refresh();
        });
    }

    /** @param array<string, true> $sudah */
    private function validasiBaris(array $data, array &$sudah): ?string
    {
        if (($data['uraian'] ?? '') === '') {
            return 'Uraian wajib diisi.';
        }
        if (($data['satuan'] ?? '') === '') {
            return 'Satuan wajib diisi.';
        }
        if (! preg_match('/^\d+(\.\d{1,2})?$/', (string) ($data['harga'] ?? '')) || Uang::dari((string) $data['harga'])->sen <= 0) {
            return 'Harga harus angka lebih dari 0 (maksimal 2 desimal).';
        }
        $kunci = UsulanStandarService::kunciDuplikat($data['uraian'], $data['spesifikasi'] ?? null, $data['satuan']);
        if (isset($sudah[$kunci])) {
            return 'Duplikat dengan baris lain di file yang sama.';
        }
        $sudah[$kunci] = true;

        return null;
    }
}
