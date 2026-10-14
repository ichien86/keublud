<?php

declare(strict_types=1);

namespace App\Modules\Kodefikasi\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rekening extends ModelBlud
{
    protected $table = 'rekening';

    protected array $kolomGenerated = ['jenis_id'];

    protected $casts = ['dapat_dianggarkan' => 'boolean', 'aktif' => 'boolean', 'tingkat' => 'integer'];

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(self::class, 'jenis_id');
    }
}
