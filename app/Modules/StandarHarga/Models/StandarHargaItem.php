<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Models;

use App\Modules\Shared\Support\ModelBlud;

class StandarHargaItem extends ModelBlud
{
    protected $table = 'standar_harga_item';

    protected array $kolomGenerated = ['uraian_norm'];

    protected $casts = ['berlaku_mulai' => 'date', 'tambahan_tengah_tahun' => 'boolean', 'tahun' => 'integer'];
}
