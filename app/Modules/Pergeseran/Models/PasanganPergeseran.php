<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Models;

use App\Modules\Shared\Support\ModelBlud;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Kolom tabel `pasangan_pergeseran` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $nomor
 * @property int $tahun
 * @property int $jenis_id
 * @property int $sumber_dana_id
 * @property string $total_tambah
 * @property string $total_kurang
 * @property bool $memuat_di_atas_standar
 * @property string $status
 * @property int $dibentuk_oleh
 * @property string $dibentuk_pada
 * @property CarbonImmutable|null $lengkap_pada
 * @property CarbonImmutable|null $batas_pengesahan
 * @property int|null $diputuskan_oleh
 * @property CarbonImmutable|null $diputuskan_pada
 * @property string|null $alasan
 * @property int $row_version
 * @property string $diubah_pada
 */
class PasanganPergeseran extends ModelBlud
{
    protected $table = 'pasangan_pergeseran';

    protected $casts = ['jenis_id' => 'integer', 'sumber_dana_id' => 'integer', 'tahun' => 'integer', 'dibentuk_oleh' => 'integer',
        'lengkap_pada' => 'immutable_datetime',
        'batas_pengesahan' => 'immutable_datetime',
        'diputuskan_pada' => 'immutable_datetime',
        'memuat_di_atas_standar' => 'boolean',
        'row_version' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsToMany<PermintaanPergeseran, $this> */
    public function permintaan(): BelongsToMany
    {
        return $this->belongsToMany(PermintaanPergeseran::class, 'pasangan_anggota', 'pasangan_id', 'permintaan_id')
            ->withPivot(['persetujuan_unit', 'diputuskan_oleh', 'diputuskan_pada', 'aktif'])
            ->wherePivot('aktif', true);
    }
}
