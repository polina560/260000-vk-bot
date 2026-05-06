<?php

use App\Http\Middleware\MoonshineBasicAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'moonshine.basic' => MoonshineBasicAuth::class,
        ]);
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '172.17.0.0/16',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            'host.docker.internal',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
