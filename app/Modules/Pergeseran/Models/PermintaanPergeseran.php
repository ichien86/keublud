<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Models;

use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Revisi\Models\RevisiPaket;
use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function pagu(): BelongsTo
    {
        return $this->belongsTo(PaguJenis::class, 'pagu_jenis_id');
    }

    public function revisiPaket(): BelongsTo
    {
        return $this->belongsTo(RevisiPaket::class, 'revisi_paket_id');
    }
}
