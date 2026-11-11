<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Receives signed provider callbacks. This endpoint is registered without
 * the web/auth middleware stack (providers are not logged-in users); the
 * gateway verifies the signature header before anything is trusted.
 */
class PaymentWebhookController extends Controller
{
    /**
     * Handle a provider webhook. Always answers 200 for well-formed
     * requests so providers do not retry storms; unknown references and
     * replays are logged in payment_events.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $driver
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, $driver)
    {
        try {
            $gateway = app(PaymentManager::class)->driver($driver);
        } catch (InvalidArgumentException $e) {
            abort(404);
        }

        try {
            $gateway->handleWebhook($request);
        } catch (Throwable $e) {
            Log::error('Payment webhook failed', [
                'driver' => $driver,
                'error'  => $e->getMessage(),
            ]);

            return response()->json(['received' => false], 400);
        }

        return response()->json(['received' => true]);
    }
}
