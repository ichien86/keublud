<?php

declare(strict_types=1);

namespace App\Modules\Anggaran\Models;

use App\Modules\Shared\Support\ModelBlud;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kolom tabel `rba` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tahun
 * @property int $versi
 * @property string $jenis_versi
 * @property string $status
 * @property string|null $target_pendapatan
 * @property string|null $persen_ambang_batas
 * @property string|null $nomor_pengesahan
 * @property CarbonImmutable|null $tanggal_pengesahan
 * @property int $dibuat_oleh
 * @property string $dibuat_pada
 * @property string $diubah_pada
 * @property int $row_version
 */
class Rba extends ModelBlud
{
    protected $table = 'rba';

    protected $casts = ['tanggal_pengesahan' => 'date', 'tahun' => 'integer', 'versi' => 'integer'];

    /** @return HasMany<RbaRincian, $this> */
    public function rincian(): HasMany
    {
        return $this->hasMany(RbaRincian::class, 'rba_id');
    }

    public static function definitif(int $tahun): ?self
    {
        return static::query()->where('tahun', $tahun)->where('status', 'DEFINITIF')->first();
    }
}
