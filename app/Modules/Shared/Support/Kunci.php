<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Penguncian baris dengan urutan id tetap untuk mencegah deadlock (TDD 4.1).
 * Wajib dipanggil di dalam DB::transaction().
 */
final class Kunci
{
    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @param  iterable<int>  $ids
     * @return Collection<int, T>
     */
    public static function baris(string $model, iterable $ids): Collection
    {
        $ids = collect($ids)->unique()->sort()->values()->all();

        /** @var Builder<T> $q */
        $q = $model::query();

        return $q->whereIn((new $model)->getKeyName(), $ids)->orderBy((new $model)->getKeyName())->lockForUpdate()->get()->keyBy('id');
    }

    /** Mengizinkan trigger fn_rincian_validasi mengubah rincian RBA definitif dalam transaksi ini. */
    public static function izinkanUbahRincian(): void
    {
        DB::select("SELECT set_config('app.izin_ubah_rincian', 'on', true)");
    }

    public static function advisori(string $nama): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$nama]);
    }
}
