<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use App\Modules\Shared\Support\ModelBlud;

/**
 * Kolom tabel `persetujuan_aksi` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property int $persetujuan_id
 * @property int|null $urutan_langkah
 * @property string $aksi
 * @property int|null $pelaku_id
 * @property int|null $atas_nama_id
 * @property string $kanal
 * @property string|null $metode_konfirmasi
 * @property string|null $catatan
 * @property string $dibuat_pada
 */
class PersetujuanAksi extends ModelBlud
{
    protected $table = 'persetujuan_aksi';
}
