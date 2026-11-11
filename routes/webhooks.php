<?php

use App\Http\Controllers\Webhooks\ZohoCpaasWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Provider Webhooks
|--------------------------------------------------------------------------
|
| Stateless inbound callbacks from notification providers. This file is
| loaded from bootstrap/app.php under the "api" middleware group (no
| session, no CSRF) with the /webhooks URL prefix. Authentication is a
| shared-secret header validated inside each controller.
|
*/

Route::post('/zoho-cpaas', [ZohoCpaasWebhookController::class, 'store'])
    ->name('webhooks.zoho-cpaas');
