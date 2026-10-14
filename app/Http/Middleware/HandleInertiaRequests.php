<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Workflow\Services\KotakMasukService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => $request->user() ? [
                'id' => $request->user()->id,
                'nama' => $request->user()->name,
                'mode_sederhana' => $request->user()->mode_sederhana,
                'jabatan' => $request->user()->jabatan,
                'peran' => $request->user()->getRoleNames(),
                'izin' => $request->user()->getAllPermissions()->pluck('name'),
            ] : null,
            'menunggu' => fn () => $request->user() ? app(KotakMasukService::class)->jumlah($request->user()) : 0,
            'flash' => fn () => $request->session()->get('flash'),
            'kesalahan_domain' => fn () => $request->session()->get('kesalahan_domain'),
        ];
    }
}
