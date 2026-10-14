<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetKonteksAudit;
use App\Modules\Shared\Errors\KesalahanDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            SetKonteksAudit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Kesalahan aturan bisnis dikembalikan dalam format seragam (TDD 6.1).
        $exceptions->render(function (KesalahanDomain $e, Request $request) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json($e->toArray(), $e->status());
            }

            return back()->withErrors(['domain' => $e->getMessage()])->with('kesalahan_domain', $e->toArray());
        });
    })->create();
