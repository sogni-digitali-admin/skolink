<?php

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application(
    realpath(__DIR__.'/../')
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

/*
|--------------------------------------------------------------------------
| Register Webhook Routes
|--------------------------------------------------------------------------
|
| Provider callbacks (e.g. Zoho CPaaS delivery receipts) are stateless:
| they must bypass session state and CSRF verification. They are grouped
| under the "api" middleware (which excludes VerifyCsrfToken) with the
| /webhooks URL prefix. Registered via a booted callback so it works with
| the Kernel-based bootstrap above without touching RouteServiceProvider.
|
*/

$app->booted(function ($app) {
    $app['router']
        ->middleware('api')
        ->prefix('webhooks')
        ->group(base_path('routes/webhooks.php'));
});

return $app;
