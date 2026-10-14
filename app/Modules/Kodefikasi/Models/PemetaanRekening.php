<?php

declare(strict_types=1);

namespace App\Modules\Kodefikasi\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kolom tabel `pemetaan_rekening` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property int $rekening_blud_id
 * @property int $rekening_pemda_id
 * @property string $berlaku
 * @property int $versi_pemetaan
 * @property int|null $dibuat_oleh
 * @property string $dibuat_pada
 */
class PemetaanRekening extends ModelBlud
{
    protected $table = 'pemetaan_rekening';

    /** @return BelongsTo<Rekening, $this> */
    public function rekeningBlud(): BelongsTo
    {
        return $this->belongsTo(Rekening::class, 'rekening_blud_id');
    }

    /** @return BelongsTo<Rekening, $this> */
    public function rekeningPemda(): BelongsTo
    {
        return $this->belongsTo(Rekening::class, 'rekening_pemda_id');
    }
}
