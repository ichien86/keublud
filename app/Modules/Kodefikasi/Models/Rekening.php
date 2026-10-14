<?php

declare(strict_types=1);

namespace App\Modules\Kodefikasi\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kolom tabel `rekening` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property int $versi_id
 * @property string $bagan
 * @property string $kode
 * @property string $uraian
 * @property int $tingkat
 * @property int|null $induk_id
 * @property int|null $jenis_id
 * @property bool $dapat_dianggarkan
 * @property bool $aktif
 */
class Rekening extends ModelBlud
{
    protected $table = 'rekening';

    protected array $kolomGenerated = ['jenis_id'];

    protected $casts = ['dapat_dianggarkan' => 'boolean', 'aktif' => 'boolean', 'tingkat' => 'integer'];

    /** @return BelongsTo<self, $this> */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    /** @return BelongsTo<self, $this> */
    public function jenis(): BelongsTo
    {
        return $this->belongsTo(self::class, 'jenis_id');
    }
}
