<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persetujuan extends ModelBlud
{
    protected $table = 'persetujuan';

    protected $casts = ['diajukan_oleh' => 'integer', 'unit_id' => 'integer', 'definisi_id' => 'integer', 'row_version' => 'integer', 'langkah_aktif' => 'integer'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function definisi(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinisi::class, 'definisi_id');
    }

    public function aksi(): HasMany
    {
        return $this->hasMany(PersetujuanAksi::class, 'persetujuan_id')->orderBy('id');
    }
}
