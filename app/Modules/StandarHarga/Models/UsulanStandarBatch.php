<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsulanStandarBatch extends ModelBlud
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $table = 'usulan_standar_batch';

    public function usulan(): HasMany
    {
        return $this->hasMany(UsulanStandarHarga::class, 'batch_id');
    }
}
