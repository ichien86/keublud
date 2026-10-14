<?php

declare(strict_types=1);

namespace App\Modules\Master\Models;

use App\Modules\Shared\Support\ModelBlud;

class SumberDana extends ModelBlud
{
    protected $table = 'sumber_dana';

    protected $casts = ['aktif' => 'boolean'];
}
