<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kolom tabel `usulan_standar_batch` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $uuid
 * @property int $impor_batch_id
 * @property int $tahun
 * @property int $diajukan_oleh
 * @property int $jumlah_baris
 * @property int $jumlah_tanpa_cocok
 * @property int $jumlah_cocok_sedang
 * @property int $jumlah_cocok_tinggi
 * @property string $status
 * @property int|null $bukti_lampiran_id
 * @property string $dibuat_pada
 */
class UsulanStandarBatch extends ModelBlud
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $table = 'usulan_standar_batch';

    /** @return HasMany<UsulanStandarHarga, $this> */
    public function usulan(): HasMany
    {
        return $this->hasMany(UsulanStandarHarga::class, 'batch_id');
    }
}
