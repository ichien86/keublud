<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinisi extends ModelBlud
{
    protected $table = 'workflow_definisi';

    protected $casts = ['aktif' => 'boolean'];

    public function langkah(): HasMany
    {
        return $this->hasMany(WorkflowLangkah::class, 'definisi_id')->orderBy('urutan');
    }
}
