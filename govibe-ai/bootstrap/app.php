<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);
        $middleware->api(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // `api/*` reponn an JSON menm si kliyan an pa mande sa — se yon API.
        // Men `expectsJson()` dwe rete tou: paj yo gen endpwen JSON pa yo
        // (vwa demo a, chat sipò a) andeyò `api/*`. San dezyèm kondisyon an,
        // yon erè validasyon sou youn nan yo voye yon redireksyon HTML bay
        // yon `fetch()` ki t ap tann JSON — epi paj la tonbe an silans.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
