<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Models;

use App\Modules\Shared\Support\ModelBlud;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kolom tabel `usulan_standar_harga` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tahun
 * @property int|null $batch_id
 * @property string $asal
 * @property string $konteks
 * @property string|null $konteks_url
 * @property string|null $kode_barang
 * @property string $uraian
 * @property string|null $spesifikasi
 * @property string $satuan
 * @property string $harga
 * @property string|null $kategori
 * @property string|null $rekening_ids
 * @property string|null $kategori_cocok
 * @property string $status
 * @property int|null $item_rujukan_id
 * @property string|null $alasan_keputusan
 * @property int $diajukan_oleh
 * @property int|null $diputuskan_oleh
 * @property CarbonImmutable|null $diputuskan_pada
 * @property CarbonImmutable $dibuat_pada
 */
class UsulanStandarHarga extends ModelBlud
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $table = 'usulan_standar_harga';

    protected $casts = ['diputuskan_pada' => 'datetime', 'dibuat_pada' => 'datetime', 'tahun' => 'integer'];

    /** @return BelongsTo<UsulanStandarBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(UsulanStandarBatch::class, 'batch_id');
    }

    /** @return BelongsTo<StandarHargaItem, $this> */
    public function itemRujukan(): BelongsTo
    {
        return $this->belongsTo(StandarHargaItem::class, 'item_rujukan_id');
    }
}
