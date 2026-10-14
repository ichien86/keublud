<?php

declare(strict_types=1);

namespace App\Modules\Master\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kolom tabel `unit_kerja` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property string $jenis
 * @property int|null $induk_id
 * @property bool $aktif
 * @property string $dibuat_pada
 * @property string $diubah_pada
 * @property int $row_version
 */
class UnitKerja extends ModelBlud
{
    protected $table = 'unit_kerja';

    protected $casts = ['aktif' => 'boolean'];

    /** @return BelongsTo<self, $this> */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    /** @return HasMany<self, $this> */
    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id');
    }
}
