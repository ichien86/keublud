<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use App\Modules\Shared\Support\ModelBlud;

/**
 * Kolom tabel `workflow_langkah` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property int $definisi_id
 * @property int $urutan
 * @property string $nama
 * @property string $permission
 * @property string $cakupan
 * @property string|null $nilai_minimum
 * @property string|null $nilai_maksimum
 * @property bool $boleh_otomatis
 */
class WorkflowLangkah extends ModelBlud
{
    protected $table = 'workflow_langkah';

    protected $casts = ['boleh_otomatis' => 'boolean', 'urutan' => 'integer'];
}
