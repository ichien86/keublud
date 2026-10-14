<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Models;

use App\Modules\Shared\Support\ModelBlud;

/**
 * Kolom tabel `revisi_detail` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property int $paket_id
 * @property string $aksi
 * @property int|null $rincian_id
 * @property int|null $rincian_row_version
 * @property array<string, mixed>|null $sebelum
 * @property array<string, mixed>|null $sesudah
 * @property bool $di_atas_standar
 */
class RevisiDetail extends ModelBlud
{
    protected $table = 'revisi_detail';

    protected $casts = ['rincian_id' => 'integer', 'rincian_row_version' => 'integer', 'sebelum' => 'array', 'sesudah' => 'array', 'di_atas_standar' => 'boolean'];
}
