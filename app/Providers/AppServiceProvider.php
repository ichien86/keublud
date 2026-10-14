<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Revisi\Domain\KalkulatorRevisi;
use App\Modules\Pergeseran\Domain\ValidatorPasangan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Carbon\CarbonImmutable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KalkulatorRevisi::class);
        $this->app->singleton(ValidatorPasangan::class);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! $this->app->isProduction());
        // Tidak ada hapus fisik data keuangan; penghapusan model hanya untuk tabel non-keuangan.
        Model::preventSilentlyDiscardingAttributes();
    }
}
