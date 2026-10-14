<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use App\Modules\Shared\Domain\KalenderKerja;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Menyusun KalenderKerja dari tabel kalender_libur (dikelola administrator). */
final class Kalender
{
    public function kerja(): KalenderKerja
    {
        $libur = Cache::remember('blud.kalender_libur', 3600, static fn () => DB::table('kalender_libur')
            ->pluck('tanggal')->map(static fn ($t) => substr((string) $t, 0, 10))->all());

        return new KalenderKerja($libur, (string) config('blud.zona_waktu', 'Asia/Jakarta'));
    }

    public function lupakan(): void
    {
        Cache::forget('blud.kalender_libur');
    }
}
