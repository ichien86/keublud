<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Models;

use App\Modules\Shared\Support\ModelBlud;
use Carbon\CarbonImmutable;

/**
 * Kolom tabel `standar_harga_item` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property int $tahun
 * @property string $sumber
 * @property int|null $impor_id
 * @property int|null $usulan_id
 * @property string|null $kode_barang
 * @property string $uraian
 * @property string|null $spesifikasi
 * @property string $satuan
 * @property string $harga
 * @property string|null $rekening_ids
 * @property string|null $uraian_norm
 * @property string $status
 * @property CarbonImmutable $berlaku_mulai
 * @property bool $tambahan_tengah_tahun
 * @property string $dibuat_pada
 */
class StandarHargaItem extends ModelBlud
{
    protected $table = 'standar_harga_item';

    protected array $kolomGenerated = ['uraian_norm'];

    protected $casts = ['berlaku_mulai' => 'date', 'tambahan_tengah_tahun' => 'boolean', 'tahun' => 'integer'];
}
