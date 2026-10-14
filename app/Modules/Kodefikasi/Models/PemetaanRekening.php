<?php

declare(strict_types=1);

namespace App\Modules\Kodefikasi\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemetaanRekening extends ModelBlud
{
    protected $table = 'pemetaan_rekening';

    public function rekeningBlud(): BelongsTo
    {
        return $this->belongsTo(Rekening::class, 'rekening_blud_id');
    }

    public function rekeningPemda(): BelongsTo
    {
        return $this->belongsTo(Rekening::class, 'rekening_pemda_id');
    }
}
