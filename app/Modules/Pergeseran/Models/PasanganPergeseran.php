<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PasanganPergeseran extends ModelBlud
{
    protected $table = 'pasangan_pergeseran';

    protected $casts = ['jenis_id' => 'integer', 'sumber_dana_id' => 'integer', 'tahun' => 'integer', 'dibentuk_oleh' => 'integer',
        'lengkap_pada' => 'immutable_datetime',
        'batas_pengesahan' => 'immutable_datetime',
        'diputuskan_pada' => 'immutable_datetime',
        'memuat_di_atas_standar' => 'boolean',
        'row_version' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function permintaan(): BelongsToMany
    {
        return $this->belongsToMany(PermintaanPergeseran::class, 'pasangan_anggota', 'pasangan_id', 'permintaan_id')
            ->withPivot(['persetujuan_unit', 'diputuskan_oleh', 'diputuskan_pada', 'aktif'])
            ->wherePivot('aktif', true);
    }
}
