<?php

declare(strict_types=1);

namespace App\Modules\Anggaran\Models;

use App\Modules\Master\Models\UnitKerja;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Support\ModelBlud;
use App\Modules\StandarHarga\Models\StandarHargaItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kolom tabel `rba_rincian` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property int $rba_id
 * @property int $unit_id
 * @property int $sumber_dana_id
 * @property int|null $sub_kegiatan_id
 * @property int $rekening_blud_id
 * @property int $rekening_pemda_id
 * @property int $jenis_id
 * @property int $item_id
 * @property string $uraian
 * @property string $volume
 * @property string $satuan
 * @property string $harga_standar
 * @property string $harga
 * @property string|null $nilai
 * @property bool|null $di_atas_standar
 * @property string $dasar_harga
 * @property string $realisasi
 * @property string $komitmen
 * @property int|null $terkunci_paket_id
 * @property bool $terkunci_transaksi
 * @property bool $aktif
 * @property string|null $label_lama
 * @property string $dibuat_pada
 * @property string $diubah_pada
 * @property int $row_version
 */
class RbaRincian extends ModelBlud
{
    protected $table = 'rba_rincian';

    protected array $kolomGenerated = ['nilai', 'di_atas_standar'];

    protected $casts = ['rba_id' => 'integer', 'unit_id' => 'integer', 'jenis_id' => 'integer', 'sumber_dana_id' => 'integer', 'item_id' => 'integer', 'terkunci_paket_id' => 'integer', 'aktif' => 'boolean', 'terkunci_transaksi' => 'boolean', 'di_atas_standar' => 'boolean', 'row_version' => 'integer'];

    /** @return BelongsTo<Rba, $this> */
    public function rba(): BelongsTo
    {
        return $this->belongsTo(Rba::class, 'rba_id');
    }

    /** @return BelongsTo<UnitKerja, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_id');
    }

    /** @return BelongsTo<StandarHargaItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(StandarHargaItem::class, 'item_id');
    }

    public function nilaiUang(): Uang
    {
        return Uang::dari((string) $this->nilai);
    }
}
