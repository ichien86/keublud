<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Kolom tabel `users` (dibuat dari skema database; perbarui bila skema berubah).
 *
 * @property int $id
 * @property string $name
 * @property string|null $nip
 * @property string|null $jabatan
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $pin_hash
 * @property bool $mode_sederhana
 * @property bool $aktif
 * @property string|null $remember_token
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class User extends Authenticatable
{
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = ['name', 'nip', 'jabatan', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'pin_hash'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
            'mode_sederhana' => 'boolean',
        ];
    }

    /** Kanal privat Reverb untuk notifikasi pengguna. */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'user.'.$this->id;
    }
}
