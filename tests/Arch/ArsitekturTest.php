<?php

// Batas antarmodul ditegakkan otomatis (TDD 2.3, bagian 16).

arch('domain murni tidak bergantung pada Laravel')
    ->expect([
        'App\Modules\Shared\Domain',
        'App\Modules\Revisi\Domain',
        'App\Modules\Pergeseran\Domain',
        'App\Modules\StandarHarga\Domain',
        'App\Modules\ImporRba\Domain',
    ])
    ->not->toUse(['Illuminate', 'Laravel']);

arch('PaguLedger tidak dapat diturunkan atau diganti diam-diam')
    ->expect('App\Modules\Anggaran\Services\PaguLedger')
    ->toBeFinal();

arch('controller tidak mengakses ledger pagu secara langsung')
    ->expect([
        'App\Modules\Revisi\Http',
        'App\Modules\Pergeseran\Http',
        'App\Modules\StandarHarga\Http',
    ])
    ->not->toUse('App\Modules\Anggaran\Services\PaguLedger');

arch('tidak ada debug tertinggal')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
