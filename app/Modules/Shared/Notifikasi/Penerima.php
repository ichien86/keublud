<?php

declare(strict_types=1);

namespace App\Modules\Shared\Notifikasi;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Mencari penerima notifikasi berdasarkan permission dan unit. */
final class Penerima
{
    /** @return Collection<int, User> */
    public function denganPermission(string $permission, ?array $unitIds = null): Collection
    {
        $q = User::permission($permission)->where('aktif', true);
        if ($unitIds !== null) {
            $q->whereIn('id', DB::table('user_unit')->whereIn('unit_id', $unitIds)->select('user_id'));
        }

        return $q->get();
    }
}
