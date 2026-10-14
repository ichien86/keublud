<?php

declare(strict_types=1);

namespace App\Modules\Master\Models;

use App\Modules\Shared\Support\ModelBlud;

/**
 * Kolom tabel `sumber_dana` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property string $kelompok
 * @property bool $aktif
 */
class SumberDana extends ModelBlud
{
    protected $table = 'sumber_dana';

    protected $casts = ['aktif' => 'boolean'];
}
