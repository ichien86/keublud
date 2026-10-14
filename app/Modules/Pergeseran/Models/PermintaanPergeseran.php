<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Models;

use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Revisi\Models\RevisiPaket;
use App\Modules\Shared\Support\ModelBlud;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kolom tabel `permintaan_pergeseran` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $nomor
 * @property string $arah
 * @property int $pagu_jenis_id
 * @property string $nilai
 * @property int|null $revisi_paket_id
 * @property bool $memuat_di_atas_standar
 * @property string $status
 * @property int $diajukan_oleh
 * @property CarbonImmutable $diajukan_pada
 * @property CarbonImmutable $batas_pasangan
 * @property bool $pengingat_terkirim
 * @property string|null $alasan_tolak
 * @property int $row_version
 * @property string $diubah_pada
 */
class PermintaanPergeseran extends ModelBlud
{
    protected $table = 'permintaan_pergeseran';

    protected $casts = ['pagu_jenis_id' => 'integer', 'revisi_paket_id' => 'integer', 'diajukan_oleh' => 'integer',
        'diajukan_pada' => 'immutable_datetime',
        'batas_pasangan' => 'immutable_datetime',
        'memuat_di_atas_standar' => 'boolean',
        'pengingat_terkirim' => 'boolean',
        'row_version' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<PaguJenis, $this> */
    public function pagu(): BelongsTo
    {
        return $this->belongsTo(PaguJenis::class, 'pagu_jenis_id');
    }

    /** @return BelongsTo<RevisiPaket, $this> */
    public function revisiPaket(): BelongsTo
    {
        return $this->belongsTo(RevisiPaket::class, 'revisi_paket_id');
    }
}
