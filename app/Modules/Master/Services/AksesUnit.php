<?php

declare(strict_types=1);

namespace App\Modules\Master\Services;

use Illuminate\Support\Facades\DB;

/** Cakupan unit pengguna (NFR-001: pembatasan data per unit). */
final class AksesUnit
{
    /** @return list<int> */
    public function unitPengguna(int $userId): array
    {
        return DB::table('user_unit')->where('user_id', $userId)->pluck('unit_id')->map(fn ($v) => (int) $v)->all();
    }

    public function milik(int $userId, int $unitId): bool
    {
        return DB::table('user_unit')->where('user_id', $userId)->where('unit_id', $unitId)->exists();
    }
}
