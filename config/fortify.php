<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'login',          // email atau NIP (lihat FortifyServiceProvider::authenticateUsing)
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/beranda',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    'views' => true,
    // Akun dibuat administrator (tidak ada pendaftaran mandiri). 2FA diaktifkan setelah halaman profil tersedia.
    'features' => [
        Features::updatePasswords(),
    ],
];
