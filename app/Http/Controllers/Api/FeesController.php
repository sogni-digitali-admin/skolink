<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Http\Controllers\Api;

use App\Http\Resources\API\FeePayment as FeePaymentResource;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Helpers\SiteHelper;
use App\Models\FeePayment;
use App\Models\Payment;
use App\Models\User;
use App\Models\Fee;
use App\Payments\Support\Money;
use Exception;

class FeesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function paid($student_id)
    {
        //
        $school_id = Auth::user()->school_id;
        $academic_year = SiteHelper::getAcademicYear($school_id);
        $student = User::where('id',$student_id)->first();
        $fee = FeePayment::whereHas('fee',function($query) use ($school_id,$academic_year)
                {
                    $query->where([
                        ['school_id',$school_id],
                        ['academic_year_id',$academic_year->id],
                        ['paid_on','!=',null]
                    ])->orWhere([
                        ['standardLink_id',$student->studentAcademicLatest->standardLink_id],
                        ['paid_on','!=',null]
                    ]);
                })->where('user_id',$student_id)->get();
        $fee  = FeePaymentResource::collection($fee);

        return response()->json([
            'success'   =>  true,
            'message'   =>  'Fees Payment List',
            'data'      =>  $fee
        ],200);
    }

    public function unpaid($student_id)
    {
        //
        $school_id = Auth::user()->school_id;
        $academic_year = SiteHelper::getAcademicYear($school_id);
        $student = User::where('id',$student_id)->first();
        /*$fees = Fee::where([
                ['school_id',$school_id],
                ['academic_year_id',$academic_year->id],
                ['due_date','<',date('Y-m-d')],
                ['standardLink_id',null]
            ])->orWhere([
                ['school_id',$school_id],
                ['academic_year_id',$academic_year->id],
                ['due_date','<',date('Y-m-d')],
                ['standardLink_id',$student->studentAcademicLatest->standardLink_id]
            ])->get();

        $array = [];
        $i = 0;
        foreach ($fees as $fee) 
        {
            $payment = FeePayment::where([['fee_id',$fee->id],['user_id',$student_id]])->exists();
            if(!$payment)
            {
                $array[$i]['name']      = $fee->name;
                $array[$i]['term']      = $fee->term;
                $array[$i]['amount']    = $fee->amount;
                $array[$i]['dueDate']   = date('d M Y',strtotime($fee->due_date));
                $i++;
            }
        }*/
        $fee = FeePayment::whereHas('fee',function($query) use ($school_id,$academic_year)
                {
                    $query->where([
                        ['school_id',$school_id],
                        ['academic_year_id',$academic_year->id],
                        ['paid_on',null]
                    ])->orWhere([['standardLink_id',$student->studentAcademicLatest->standardLink_id],['paid_on',null]]);
                })->where('user_id',$student_id)->get();
        $fee  = FeePaymentResource::collection($fee);

        return response()->json([
            'success'   =>  true,
            'message'   =>  'Unpaid Fees List',
            'data'      =>  $fee
        ],200);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        $fee = FeePayment::where('id',$id)->get();
        $fee  = FeePaymentResource::collection($fee);

        return response()->json([
            'success'   =>  true,
            'message'   =>  'Fees Payment',
            'data'      =>  $fee
        ],200);
    }

    /**
     * Fee account for one of the authenticated parent's children:
     * outstanding balance, invoice lines and payment history.
     *
     * PWA contract (pwa/src/services/types.ts FeeAccount): amounts are
     * MAJOR units (e.g. 1500.00); integer-cent twins are included for
     * exactness. Lines carry status due|paid|overdue.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $student_id
     * @return \Illuminate\Http\Response
     */
    public function account(Request $request, $student_id)
    {
        $children_ids = $request->user()->children->pluck('student_id')->map(function ($id) {
            return (int) $id;
        })->toArray();

        if (! in_array((int) $student_id, $children_ids)) {
            return response()->json([
                'success'   =>  false,
                'message'   =>  'This student is not linked to your account.',
            ], 403);
        }

        $currency = Money::currency();

        $invoices = FeePayment::with('fee')
            ->where('user_id', $student_id)
            ->whereHas('fee', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->orderBy('id')
            ->get();

        $lines = [];

        foreach ($invoices as $invoice) {
            $this->refreshInvoice($invoice);

            $status = 'due';
            if ($invoice->status === FeePayment::STATUS_PAID) {
                $status = 'paid';
            } elseif ($invoice->fee && $invoice->fee->end_date && $invoice->fee->end_date->isPast()) {
                $status = 'overdue';
            }

            $lines[] = [
                'id'          => $invoice->id,
                'label'       => $invoice->fee ? $invoice->fee->name : 'Fee',
                'term'        => $invoice->fee ? $invoice->fee->term : null,
                'amount'      => $invoice->amount_due_cents / 100,
                'amountCents' => $invoice->amount_due_cents,
                'paidCents'   => $invoice->amount_paid_cents,
                'currency'    => $currency,
                'dueDate'     => ($invoice->fee && $invoice->fee->end_date)
                                    ? $invoice->fee->end_date->format('d M Y')
                                    : null,
                'status'      => $status,
                'paidOn'      => $invoice->paid_on ? $invoice->paid_on->format('d M Y') : null,
            ];
        }

        $balance_cents = $invoices->sum(function ($invoice) {
            return $invoice->outstanding_cents;
        });

        $history = Payment::where('user_id', $student_id)
            ->whereNotNull('fee_payment_id')
            ->orderByDesc('id')
            ->get()
            ->map(function ($payment) use ($currency) {
                return [
                    'id'          => $payment->id,
                    'reference'   => $payment->provider_reference,
                    'invoiceId'   => $payment->fee_payment_id,
                    'amount'      => $payment->amount_cents / 100,
                    'amountCents' => $payment->amount_cents,
                    'currency'    => $payment->currency ?: $currency,
                    'gateway'     => $payment->gateway,
                    'method'      => $payment->method,
                    'status'      => $payment->status,
                    'paidOn'      => $payment->paid_at ? $payment->paid_at->format('d M Y') : null,
                ];
            });

        return response()->json([
            'success'   =>  true,
            'message'   =>  'Fee Account',
            'data'      =>  [
                'studentId'       => (int) $student_id,
                'currency'        => $currency,
                'balanceDue'      => $balance_cents / 100,
                'balanceDueCents' => $balance_cents,
                'lines'           => $lines,
                'payments'        => $history,
            ],
        ], 200);
    }

    /**
     * Reconcile one invoice against the payments table (source of truth for
     * money actually received) so reads stay correct even when a payment was
     * recorded without a fee_payment_id link.
     *
     * @param  \App\Models\FeePayment  $invoice
     * @return void
     */
    protected function refreshInvoice(FeePayment $invoice)
    {
        $paid_cents = (int) $invoice->payments()->where('status', Payment::STATUS_PAID)->sum('amount_cents');

        $dirty = [];

        if ($paid_cents !== (int) $invoice->amount_paid_cents) {
            $dirty['amount_paid_cents'] = $paid_cents;
        }

        if ($paid_cents >= (int) $invoice->amount_due_cents && (int) $invoice->amount_due_cents > 0) {
            if ($invoice->status !== FeePayment::STATUS_PAID) {
                $dirty['status'] = FeePayment::STATUS_PAID;
            }
            if ($invoice->paid_on === null) {
                $last = $invoice->payments()->where('status', Payment::STATUS_PAID)->latest('paid_at')->first();
                $dirty['paid_on'] = $last && $last->paid_at ? $last->paid_at->toDateString() : date('Y-m-d');
            }
        } elseif ($paid_cents > 0 && $invoice->status === FeePayment::STATUS_PENDING) {
            $dirty['status'] = FeePayment::STATUS_PARTIAL;
        }

        if (! empty($dirty)) {
            $invoice->forceFill($dirty)->save();
        }
    }
}