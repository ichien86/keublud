<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use App\Modules\Shared\Support\ModelBlud;

class WorkflowLangkah extends ModelBlud
{
    protected $table = 'workflow_langkah';

    protected $casts = ['boleh_otomatis' => 'boolean', 'urutan' => 'integer'];
}
