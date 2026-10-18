<?php

use App\Http\Middleware\AuthenticateExternalApiClient;
use App\Http\Middleware\AuthenticateWpConnector;
use App\Http\Middleware\EnsureExternalApiClientCan;
use App\Http\Middleware\ServeCustomerAppOnCustomDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'external-api.auth' => AuthenticateExternalApiClient::class,
            'external-api.can' => EnsureExternalApiClientCan::class,
            'wp-connector.auth' => AuthenticateWpConnector::class,
        ]);

        // Must run before routing resolves "/" to the default welcome
        // view, so a connected custom domain (see WebAppDomainSetting)
        // can show the customer وب‌اپ at its own clean root instead.
        $middleware->web(prepend: [ServeCustomerAppOnCustomDomain::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
