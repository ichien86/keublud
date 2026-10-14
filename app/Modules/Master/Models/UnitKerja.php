<?php

declare(strict_types=1);

namespace App\Modules\Master\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitKerja extends ModelBlud
{
    protected $table = 'unit_kerja';

    protected $casts = ['aktif' => 'boolean'];

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id');
    }
}
