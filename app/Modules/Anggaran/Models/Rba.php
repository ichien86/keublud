<?php

declare(strict_types=1);

namespace App\Modules\Anggaran\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rba extends ModelBlud
{
    protected $table = 'rba';

    protected $casts = ['tanggal_pengesahan' => 'date', 'tahun' => 'integer', 'versi' => 'integer'];

    public function rincian(): HasMany
    {
        return $this->hasMany(RbaRincian::class, 'rba_id');
    }

    public static function definitif(int $tahun): ?self
    {
        return static::query()->where('tahun', $tahun)->where('status', 'DEFINITIF')->first();
    }
}
