<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kolom tabel `workflow_definisi` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $objek_tipe
 * @property int $versi
 * @property bool $aktif
 */
class WorkflowDefinisi extends ModelBlud
{
    protected $table = 'workflow_definisi';

    protected $casts = ['aktif' => 'boolean'];

    /** @return HasMany<WorkflowLangkah, $this> */
    public function langkah(): HasMany
    {
        return $this->hasMany(WorkflowLangkah::class, 'definisi_id')->orderBy('urutan');
    }
}
