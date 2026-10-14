<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kolom tabel `persetujuan` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $uuid
 * @property string $objek_tipe
 * @property int $objek_id
 * @property int $definisi_id
 * @property int|null $langkah_aktif
 * @property string|null $nilai
 * @property int|null $unit_id
 * @property string $status
 * @property int $diajukan_oleh
 * @property string $dibuat_pada
 * @property int $row_version
 * @property string $diubah_pada
 */
class Persetujuan extends ModelBlud
{
    protected $table = 'persetujuan';

    protected $casts = ['diajukan_oleh' => 'integer', 'unit_id' => 'integer', 'definisi_id' => 'integer', 'row_version' => 'integer', 'langkah_aktif' => 'integer'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<WorkflowDefinisi, $this> */
    public function definisi(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinisi::class, 'definisi_id');
    }

    /** @return HasMany<PersetujuanAksi, $this> */
    public function aksi(): HasMany
    {
        return $this->hasMany(PersetujuanAksi::class, 'persetujuan_id')->orderBy('id');
    }
}
