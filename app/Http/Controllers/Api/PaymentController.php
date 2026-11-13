<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeePayment;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Payments\PaymentSession;
use App\Payments\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Initiate a parent payment against a child's outstanding fee invoice.
     *
     * PWA contract (pwa/src/services/types.ts): body {student_id, amount,
     * currency, method}; response data {reference, checkoutUrl?, status?}.
     *
     * The client amount is NEVER trusted: one payment settles one invoice
     * (oldest outstanding first) and the amount is recomputed server-side
     * from the invoice's outstanding cents. Idempotent: a pending payment
     * for the same invoice (or the same client reference) is returned
     * instead of creating a duplicate.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id'      => 'required|integer',
            'fee_payment_id'  => 'nullable|integer',
            'method'          => 'nullable|string|max:50',
            'amount'          => 'nullable|numeric|min:0',
            'currency'        => 'nullable|string|size:3',
            'reference'       => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $parent       = $request->user();
        $children_ids = $parent->children->pluck('student_id')->map(function ($id) {
            return (int) $id;
        })->toArray();

        if (! in_array((int) $request->input('student_id'), $children_ids)) {
            return response()->json([
                'success'   =>  false,
                'message'   =>  'This student is not linked to your account.',
            ], 403);
        }

        $student = User::find($request->input('student_id'));

        if (! $student) {
            return response()->json([
                'success'   =>  false,
                'message'   =>  'Student not found.',
            ], 404);
        }

        $invoice = $this->resolveInvoice($request, $student);

        if (! $invoice instanceof FeePayment) {
            return $invoice;
        }

        $payment = $this->findExistingPayment($request, $invoice, $student);
        $reused  = (bool) $payment;

        try {
            $gateway = app(PaymentManager::class)->forSchool($student->school_id);

            if (! $payment) {
                $payment = Payment::create([
                    'school_id'      => $student->school_id,
                    'user_id'        => $student->id,
                    'fee_payment_id' => $invoice->id,
                    'amount_cents'   => $invoice->outstanding_cents,
                    'currency'       => Money::currency(),
                    'gateway'        => $gateway->driver(),
                    'method'         => $request->input('method'),
                    'status'         => Payment::STATUS_PENDING,
                    'meta'           => array_filter([
                        'client_reference' => $request->input('reference'),
                        'initiated_by'     => $parent->id,
                        'channel'          => 'parent_app',
                    ]),
                ]);
            }

            $session = $gateway->initiate($payment);
        } catch (\RuntimeException $e) {
            if (! $reused && $payment && $payment->exists) {
                $payment->delete();
            }

            return response()->json([
                'success'   =>  false,
                'message'   =>  $e->getMessage(),
            ], 422);
        }

        $data = [
            'reference'   => $payment->provider_reference,
            'checkoutUrl' => $session->type === PaymentSession::TYPE_REDIRECT ? $session->redirectUrl : null,
            'status'      => $payment->status,
            'amount'      => $payment->amount_cents / 100,
            'amountCents' => $payment->amount_cents,
            'currency'    => $payment->currency,
            'gateway'     => $payment->gateway,
            'method'      => $payment->method,
            'invoiceId'   => $invoice->id,
        ];

        if ($session->type === PaymentSession::TYPE_MANUAL) {
            $data['instructions'] = 'Pay at the school office or by Juice/bank transfer quoting the payment reference.';
            $data['methods']      = $session->data['methods'] ?? [];
        }

        return response()->json([
            'success'   =>  true,
            'message'   =>  $reused ? 'Existing pending payment returned.' : 'Payment initiated.',
            'data'      =>  $data,
        ], $reused ? 200 : 201);
    }

    /**
     * Pick the invoice to settle: the requested one, or the oldest with an
     * outstanding balance. Returns a JsonResponse error on failure.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $student
     * @return \App\Models\FeePayment|\Illuminate\Http\JsonResponse
     */
    protected function resolveInvoice(Request $request, User $student)
    {
        if ($request->filled('fee_payment_id')) {
            $invoice = FeePayment::where('id', $request->input('fee_payment_id'))
                ->where('user_id', $student->id)
                ->first();

            if (! $invoice) {
                return response()->json([
                    'success'   =>  false,
                    'message'   =>  'Invoice not found for this student.',
                ], 404);
            }

            if ($invoice->status === FeePayment::STATUS_PAID || $invoice->outstanding_cents <= 0) {
                return response()->json([
                    'success'   =>  false,
                    'message'   =>  'This invoice is already settled.',
                ], 422);
            }

            return $invoice;
        }

        $invoice = FeePayment::where('user_id', $student->id)
            ->whereIn('status', [FeePayment::STATUS_PENDING, FeePayment::STATUS_PARTIAL])
            ->orderBy('id')
            ->get()
            ->first(function ($invoice) {
                return $invoice->outstanding_cents > 0;
            });

        if (! $invoice) {
            return response()->json([
                'success'   =>  false,
                'message'   =>  'No outstanding fees for this student.',
            ], 422);
        }

        return $invoice;
    }

    /**
     * Idempotency lookup: an already-pending payment for this invoice, or
     * one created earlier with the same client reference.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\FeePayment  $invoice
     * @param  \App\Models\User  $student
     * @return \App\Models\Payment|null
     */
    protected function findExistingPayment(Request $request, FeePayment $invoice, User $student)
    {
        $query = Payment::where('user_id', $student->id)
            ->where('status', Payment::STATUS_PENDING);

        $byInvoice = (clone $query)->where('fee_payment_id', $invoice->id)->latest('id')->first();

        if ($byInvoice) {
            return $byInvoice;
        }

        if ($request->filled('reference')) {
            return $query->where('meta->client_reference', $request->input('reference'))->latest('id')->first();
        }

        return null;
    }
}
