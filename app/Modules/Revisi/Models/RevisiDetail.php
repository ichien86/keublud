<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Models;

use App\Modules\Shared\Support\ModelBlud;

class RevisiDetail extends ModelBlud
{
    protected $table = 'revisi_detail';

    protected $casts = ['rincian_id' => 'integer', 'rincian_row_version' => 'integer', 'sebelum' => 'array', 'sesudah' => 'array', 'di_atas_standar' => 'boolean'];
}
