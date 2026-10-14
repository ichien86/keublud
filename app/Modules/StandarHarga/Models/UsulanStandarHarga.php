<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsulanStandarHarga extends ModelBlud
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $table = 'usulan_standar_harga';

    protected $casts = ['diputuskan_pada' => 'datetime', 'dibuat_pada' => 'datetime', 'tahun' => 'integer'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(UsulanStandarBatch::class, 'batch_id');
    }

    public function itemRujukan(): BelongsTo
    {
        return $this->belongsTo(StandarHargaItem::class, 'item_rujukan_id');
    }
}
