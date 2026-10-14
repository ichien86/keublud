<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Basis model BLUD: tabel memakai kolom waktu berbahasa Indonesia yang diisi database,
 * dan kolom generated (nilai, sisa, selisih, ...) tidak pernah ditulis dari aplikasi.
 */
abstract class ModelBlud extends Model
{
    public $timestamps = false;

    /** timestamptz: selalu kirim offset agar tidak bergantung zona waktu sesi database. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $guarded = ['id'];

    /** @var list<string> kolom GENERATED yang tidak boleh dikirim saat insert/update */
    protected array $kolomGenerated = [];

    /**
     * Banyak kolom diisi default database (uuid, status, waktu, kolom generated). Setelah insert,
     * muat ulang baris agar objek yang dikembalikan service lengkap (mis. uuid untuk URL).
     */
    protected static function booted(): void
    {
        static::created(static function (self $model): void {
            $model->refresh();
        });
    }

    /** Atribut untuk update tanpa kolom generated (insert tidak pernah mengisi kolom tersebut). */
    public function getDirty(): array
    {
        return array_diff_key(parent::getDirty(), array_flip($this->kolomGenerated));
    }
}
