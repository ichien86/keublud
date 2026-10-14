<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Services;

use App\Models\User;
use App\Modules\Anggaran\Models\Rba;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Errors\KesalahanDomain;
use App\Modules\Shared\Notifikasi\NotifikasiBlud;
use App\Modules\StandarHarga\Domain\NormalisasiUraian;
use App\Modules\StandarHarga\Domain\SkorKecocokan;
use App\Modules\StandarHarga\Models\StandarHargaItem;
use App\Modules\StandarHarga\Models\UsulanStandarBatch;
use App\Modules\StandarHarga\Models\UsulanStandarHarga;
use Illuminate\Support\Facades\DB;

/**
 * Usulan standar harga internal BLUD (PRD 7.4, 7.4a): formulir atau impor Excel,
 * pengecekan kecocokan saat diajukan dan saat diputuskan, penambahan item di tengah tahun.
 */
final class UsulanStandarService
{
    public const SAHKAN = 'SAHKAN';
    public const PAKAI_ITEM = 'PAKAI_ITEM';
    public const KEMBALIKAN = 'KEMBALIKAN';

    public function __construct(
        private readonly StandarHargaMatcher $matcher,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data uraian, spesifikasi?, satuan, harga, kode_barang?, kategori?, rekening_ids?, konteks?, konteks_url? */
    public function ajukan(int $tahun, array $data, User $user, ?int $batchId = null, string $asal = 'FORMULIR'): UsulanStandarHarga
    {
        if (! $user->can('standar.usul')) {
            throw new KesalahanDomain('TIDAK_BERWENANG', 'Anda tidak berwenang mengusulkan standar harga.', statusHttp: 403);
        }

        return DB::transaction(function () use ($tahun, $data, $user, $batchId, $asal) {
            $harga = Uang::dari((string) $data['harga']);
            $cocok = $this->matcher->cari($tahun, (string) $data['uraian'], $data['spesifikasi'] ?? null, (string) $data['satuan'], $harga, $data['kode_barang'] ?? null);

            /** @var UsulanStandarHarga $u */
            $u = UsulanStandarHarga::query()->forceCreate([
                'tahun' => $tahun, 'batch_id' => $batchId, 'asal' => $asal,
                'konteks' => $data['konteks'] ?? 'PENYUSUNAN', 'konteks_url' => $data['konteks_url'] ?? null,
                'kode_barang' => $data['kode_barang'] ?? null, 'uraian' => trim((string) $data['uraian']),
                'spesifikasi' => $data['spesifikasi'] ?? null, 'satuan' => trim((string) $data['satuan']),
                'harga' => $harga->keDesimal(), 'kategori' => $data['kategori'] ?? null,
                'rekening_ids' => isset($data['rekening_ids']) ? '{'.implode(',', array_map('intval', $data['rekening_ids'])).'}' : null,
                'kategori_cocok' => SkorKecocokan::kategoriTertinggi(array_column($cocok, 'kategori')),
                'status' => 'DIAJUKAN', 'diajukan_oleh' => $user->id,
            ]);
            $this->simpanKecocokan($u, $cocok, 'DIAJUKAN');

            return $u;
        });
    }

    /**
     * Keputusan perencana (FR-018): SAHKAN (alasan wajib bila ada kecocokan tinggi), PAKAI_ITEM, atau KEMBALIKAN.
     */
    public function putuskan(UsulanStandarHarga $usulan, User $user, string $keputusan, ?int $itemRujukanId = null, ?string $alasan = null): UsulanStandarHarga
    {
        if (! $user->can('standar.putuskan')) {
            throw new KesalahanDomain('TIDAK_BERWENANG', 'Hanya perencana yang dapat memutuskan usulan standar harga.', statusHttp: 403);
        }

        $hasil = DB::transaction(function () use ($usulan, $user, $keputusan, $itemRujukanId, $alasan) {
            /** @var UsulanStandarHarga $u */
            $u = UsulanStandarHarga::query()->whereKey($usulan->id)->lockForUpdate()->firstOrFail();
            if ($u->status !== 'DIAJUKAN') {
                throw new KesalahanDomain('STATUS_TIDAK_SESUAI', "Usulan berstatus {$u->status}.", statusHttp: 409);
            }
            if ((int) $u->diajukan_oleh === (int) $user->id) {
                throw new KesalahanDomain('PEMISAHAN_TUGAS', 'Pengusul tidak dapat memutuskan usulannya sendiri.', statusHttp: 403);
            }

            // Hitung ulang: menangkap item yang disahkan sejak usulan diajukan.
            $cocok = $this->matcher->cari($u->tahun, $u->uraian, $u->spesifikasi, $u->satuan, Uang::dari((string) $u->harga), $u->kode_barang);
            $kategori = SkorKecocokan::kategoriTertinggi(array_column($cocok, 'kategori'));
            $this->simpanKecocokan($u, $cocok, 'DIPUTUSKAN');

            $dasar = ['diputuskan_oleh' => $user->id, 'diputuskan_pada' => now(), 'alasan_keputusan' => $alasan, 'kategori_cocok' => $kategori];

            switch ($keputusan) {
                case self::SAHKAN:
                    $min = (int) config('blud.standar_harga.alasan_minimum_karakter', 20);
                    if ($kategori === SkorKecocokan::TINGGI && mb_strlen(trim((string) $alasan)) < $min) {
                        throw new KesalahanDomain('ALASAN_WAJIB', "Terdapat item yang sangat mirip. Jelaskan mengapa item tersebut tidak dapat dipakai (minimal {$min} karakter).",
                            ['kecocokan' => array_slice($cocok, 0, 5)]);
                    }
                    $u->forceFill($dasar + ['status' => 'DISAHKAN'])->save();
                    $this->buatItem($u);
                    break;
                case self::PAKAI_ITEM:
                    $item = StandarHargaItem::query()->where('id', $itemRujukanId)->where('tahun', $u->tahun)->where('status', 'AKTIF')->first()
                        ?? throw new KesalahanDomain('ITEM_TIDAK_DITEMUKAN', 'Item rujukan tidak ditemukan atau tidak aktif.');
                    $u->forceFill($dasar + ['status' => 'DITOLAK_PAKAI_ITEM', 'item_rujukan_id' => $item->id])->save();
                    break;
                case self::KEMBALIKAN:
                    if (mb_strlen(trim((string) $alasan)) < 10) {
                        throw new KesalahanDomain('CATATAN_WAJIB', 'Catatan pengembalian minimal 10 karakter.');
                    }
                    $u->forceFill($dasar + ['status' => 'DIKEMBALIKAN'])->save();
                    break;
                default:
                    throw new KesalahanDomain('KEPUTUSAN_TIDAK_DIKENAL', "Keputusan {$keputusan} tidak dikenal.");
            }

            $this->audit->tindakan('PUTUSKAN_USULAN_STANDAR', 'usulan_standar_harga', $u->id, $alasan, ['keputusan' => $keputusan, 'kategori' => $kategori]);
            $this->perbaruiBatch($u->batch_id);

            return $u->refresh();
        }, attempts: 3);

        DB::afterCommit(fn () => User::query()->find($hasil->diajukan_oleh)?->notify(new NotifikasiBlud(
            'USULAN_STANDAR_DIPUTUSKAN', "Usulan standar harga \"{$hasil->uraian}\": ".strtolower(str_replace('_', ' ', $hasil->status)),
            (string) ($hasil->alasan_keputusan ?? ''), $hasil->konteks_url ?? '/standar-harga/usulan/'.$hasil->uuid
        )));

        return $hasil;
    }

    /**
     * FR-105: sahkan sekaligus baris batch tanpa kecocokan tinggi. Baris yang (setelah dihitung ulang)
     * berkecocokan tinggi dilewati dan tetap menunggu keputusan per baris (UAT-48).
     *
     * @return array{disahkan: int, dilewati: int}
     */
    public function sahkanMemenuhiSyarat(UsulanStandarBatch $batch, User $user): array
    {
        $disahkan = 0;
        $dilewati = 0;
        $ids = UsulanStandarHarga::query()->where('batch_id', $batch->id)->where('status', 'DIAJUKAN')
            ->where('kategori_cocok', '<>', SkorKecocokan::TINGGI)->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            try {
                $this->putuskan(UsulanStandarHarga::query()->findOrFail($id), $user, self::SAHKAN);
                $disahkan++;
            } catch (KesalahanDomain $e) {
                if ($e->kode !== 'ALASAN_WAJIB') {
                    throw $e;
                }
                $dilewati++;
            }
        }

        return ['disahkan' => $disahkan, 'dilewati' => $dilewati];
    }

    private function buatItem(UsulanStandarHarga $u): StandarHargaItem
    {
        $tengahTahun = Rba::query()->where('tahun', $u->tahun)->where('status', 'DEFINITIF')->exists();

        /** @var StandarHargaItem */
        return StandarHargaItem::query()->forceCreate([
            'tahun' => $u->tahun, 'sumber' => 'BLUD', 'usulan_id' => $u->id, 'kode_barang' => $u->kode_barang,
            'uraian' => $u->uraian, 'spesifikasi' => $u->spesifikasi, 'satuan' => $u->satuan, 'harga' => $u->harga,
            'rekening_ids' => $u->rekening_ids, 'status' => 'AKTIF',
            'berlaku_mulai' => now()->format('Y-m-d'), 'tambahan_tengah_tahun' => $tengahTahun,
        ]);
    }

    /** @param list<array<string, mixed>> $cocok */
    private function simpanKecocokan(UsulanStandarHarga $u, array $cocok, string $tahap): void
    {
        DB::table('usulan_kecocokan')->where('usulan_id', $u->id)->where('tahap', $tahap)->delete();
        foreach (array_slice($cocok, 0, 10) as $c) {
            DB::table('usulan_kecocokan')->insert([
                'usulan_id' => $u->id, 'item_id' => $c['item_id'], 'tahap' => $tahap,
                'skor' => $c['skor'], 'rincian_skor' => json_encode($c['rincian']),
            ]);
        }
    }

    private function perbaruiBatch(?int $batchId): void
    {
        if ($batchId === null) {
            return;
        }
        $sisa = UsulanStandarHarga::query()->where('batch_id', $batchId)->where('status', 'DIAJUKAN')->count();
        UsulanStandarBatch::query()->whereKey($batchId)->update(['status' => $sisa === 0 ? 'SELESAI' : 'SEBAGIAN_DIPUTUSKAN']);
    }

    /** Kunci duplikasi baris di dalam satu file impor. */
    public static function kunciDuplikat(string $uraian, ?string $spesifikasi, string $satuan): string
    {
        return NormalisasiUraian::uraian($uraian, $spesifikasi).'|'.NormalisasiUraian::satuan($satuan);
    }
}
