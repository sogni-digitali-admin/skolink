<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PushSubscriptionController extends Controller
{
    /**
     * Store (or update) a browser web push subscription for the
     * authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint'    => 'required|string|max:500',
            'keys'        => 'nullable|array',
            'keys.p256dh' => 'nullable|string',
            'keys.auth'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $request->user()->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
            $request->input('contentEncoding')
        );

        return response()->json([
            'success' => true,
            'message' => 'Push subscription stored.',
        ], 201);
    }

    /**
     * Expose the VAPID public key so the PWA can subscribe to Web Push.
     * Both casings are returned at the top level because the PWA reads the
     * response body directly (no envelope unwrap).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function vapidPublicKey()
    {
        $key = config('webpush.vapid.public_key');

        return response()->json([
            'success'    => true,
            'message'    => 'VAPID Public Key',
            'publicKey'  => $key,
            'public_key' => $key,
            'data'       => ['publicKey' => $key],
        ], 200);
    }

    /**
     * Remove a browser web push subscription for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $request->user()->deletePushSubscription($request->input('endpoint'));

        return response()->json([
            'success' => true,
            'message' => 'Push subscription removed.',
        ], 200);
    }
}
