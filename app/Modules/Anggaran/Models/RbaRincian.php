<?php

declare(strict_types=1);

namespace App\Modules\Anggaran\Models;

use App\Modules\Master\Models\UnitKerja;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Support\ModelBlud;
use App\Modules\StandarHarga\Models\StandarHargaItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RbaRincian extends ModelBlud
{
    protected $table = 'rba_rincian';

    protected array $kolomGenerated = ['nilai', 'di_atas_standar'];

    protected $casts = ['rba_id' => 'integer', 'unit_id' => 'integer', 'jenis_id' => 'integer', 'sumber_dana_id' => 'integer', 'item_id' => 'integer', 'terkunci_paket_id' => 'integer', 'aktif' => 'boolean', 'terkunci_transaksi' => 'boolean', 'di_atas_standar' => 'boolean', 'row_version' => 'integer'];

    public function rba(): BelongsTo
    {
        return $this->belongsTo(Rba::class, 'rba_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StandarHargaItem::class, 'item_id');
    }

    public function nilaiUang(): Uang
    {
        return Uang::dari((string) $this->nilai);
    }
}
