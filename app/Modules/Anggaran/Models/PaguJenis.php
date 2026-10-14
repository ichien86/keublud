<?php

declare(strict_types=1);

namespace App\Modules\Anggaran\Models;

use App\Modules\Kodefikasi\Models\Rekening;
use App\Modules\Master\Models\SumberDana;
use App\Modules\Master\Models\UnitKerja;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Support\ModelBlud;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agregat pagu per unit x jenis x sumber dana. Kolom nilai hanya berubah lewat PaguLedger (fn_pagu_catat).
 *
 * @property int $id
 * @property int $tahun
 * @property int $unit_id
 * @property int $jenis_id
 * @property int $sumber_dana_id
 * @property string $pagu_baseline
 * @property string $pagu_berlaku
 * @property string $realisasi
 * @property string $komitmen
 * @property string $tercadang
 * @property string|null $sisa
 * @property int $row_version
 */
class PaguJenis extends ModelBlud
{
    protected $table = 'pagu_jenis';

    protected array $kolomGenerated = ['sisa'];

    protected $casts = ['tahun' => 'integer', 'unit_id' => 'integer', 'jenis_id' => 'integer', 'sumber_dana_id' => 'integer'];

    protected $guarded = ['id', 'pagu_berlaku', 'realisasi', 'komitmen', 'tercadang', 'sisa', 'pagu_baseline'];

    /** @return BelongsTo<UnitKerja, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_id');
    }

    /** @return BelongsTo<Rekening, $this> */
    public function jenis(): BelongsTo
    {
        return $this->belongsTo(Rekening::class, 'jenis_id');
    }

    /** @return BelongsTo<SumberDana, $this> */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class, 'sumber_dana_id');
    }

    public function paguBerlaku(): Uang
    {
        return Uang::dari((string) $this->pagu_berlaku);
    }

    public function sisaUang(): Uang
    {
        return Uang::dari((string) $this->sisa);
    }
}
