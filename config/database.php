<?php

/*
 * Hanya koneksi pgsql yang ditimpa; konfigurasi lain mengikuti bawaan framework.
 * Zona waktu sesi database diset Asia/Jakarta agar perhitungan tanggal (hari kerja, periode) konsisten.
 */

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'keuangan_blud'),
            'username' => env('DB_USERNAME', 'keuangan_blud'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'timezone' => env('DB_TIMEZONE', 'Asia/Jakarta'),
        ],
    ],
];
