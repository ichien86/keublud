<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Models;

use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Pergeseran\Models\PermintaanPergeseran;
use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RevisiPaket extends ModelBlud
{
    protected $table = 'revisi_paket';

    protected array $kolomGenerated = ['selisih'];

    protected $casts = ['pagu_jenis_id' => 'integer', 'dibuat_oleh' => 'integer', 'permintaan_id' => 'integer', 'memuat_di_atas_standar' => 'boolean', 'diterapkan_pada' => 'datetime', 'row_version' => 'integer'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function pagu(): BelongsTo
    {
        return $this->belongsTo(PaguJenis::class, 'pagu_jenis_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(RevisiDetail::class, 'paket_id');
    }

    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(PermintaanPergeseran::class, 'permintaan_id');
    }
}
