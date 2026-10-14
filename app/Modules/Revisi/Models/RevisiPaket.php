<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Models;

use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Pergeseran\Models\PermintaanPergeseran;
use App\Modules\Shared\Support\ModelBlud;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kolom tabel `revisi_paket` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $nomor
 * @property int $pagu_jenis_id
 * @property string $status
 * @property string|null $alasan_menunggu
 * @property string $total_sebelum
 * @property string $total_sesudah
 * @property string|null $selisih
 * @property bool $memuat_di_atas_standar
 * @property int|null $permintaan_id
 * @property string|null $alasan
 * @property int $dibuat_oleh
 * @property string $dibuat_pada
 * @property string $diubah_pada
 * @property CarbonImmutable|null $diterapkan_pada
 * @property int $row_version
 */
class RevisiPaket extends ModelBlud
{
    protected $table = 'revisi_paket';

    protected array $kolomGenerated = ['selisih'];

    protected $casts = ['pagu_jenis_id' => 'integer', 'dibuat_oleh' => 'integer', 'permintaan_id' => 'integer', 'memuat_di_atas_standar' => 'boolean', 'diterapkan_pada' => 'datetime', 'row_version' => 'integer'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<PaguJenis, $this> */
    public function pagu(): BelongsTo
    {
        return $this->belongsTo(PaguJenis::class, 'pagu_jenis_id');
    }

    /** @return HasMany<RevisiDetail, $this> */
    public function detail(): HasMany
    {
        return $this->hasMany(RevisiDetail::class, 'paket_id');
    }

    /** @return BelongsTo<PermintaanPergeseran, $this> */
    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(PermintaanPergeseran::class, 'permintaan_id');
    }
}
