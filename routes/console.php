<?php

use App\Modules\Pergeseran\Jobs\KirimPengingatBatasWaktu;
use App\Modules\Pergeseran\Jobs\ProsesBatasPasangan;
use App\Modules\Pergeseran\Jobs\ProsesBatasPengesahan;
use App\Modules\Anggaran\Jobs\RekonsiliasiPagu;
use App\Modules\Shared\Audit\VerifikasiRantaiAudit;
use Illuminate\Support\Facades\Schedule;

// TDD 4.7 — semua job idempoten dan tidak boleh tumpang tindih.
Schedule::job(new ProsesBatasPasangan)->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::job(new ProsesBatasPengesahan)->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::job(new KirimPengingatBatasWaktu)->weekdays()->at('08:00')->timezone('Asia/Jakarta')->onOneServer();
Schedule::job(new RekonsiliasiPagu)->dailyAt('01:00')->timezone('Asia/Jakarta')->onOneServer();
Schedule::job(new VerifikasiRantaiAudit)->dailyAt('02:00')->timezone('Asia/Jakarta')->onOneServer();
