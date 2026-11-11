<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daily reconciliation: payments recorded per gateway versus the fee
 * ledger (gegok12/fee module) for a given day.
 */
class PaymentReconciliationController extends Controller
{
    /**
     * Show the daily reconciliation report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $school_id = Auth::user()->school_id;
        $date      = $request->input('date', Carbon::today()->toDateString());

        $gatewayTotals = Payment::where('school_id', $school_id)
            ->paid()
            ->whereDate('paid_at', $date)
            ->select('gateway', DB::raw('COUNT(*) as payments_count'), DB::raw('SUM(amount_cents) as total_cents'))
            ->groupBy('gateway')
            ->get();

        $paymentsTotalCents = $gatewayTotals->sum('total_cents');

        // Fee ledger side (optional gegok12/fee module): fees marked paid on
        // the same day, with the invoiced amount joined from the fees table.
        $ledgerTotalCents = null;
        $ledgerCount      = null;

        if (Schema::hasTable('fee_payments')) {
            $ledgerCount = DB::table('fee_payments')
                ->where('paid_on', $date)
                ->where('status', 1)
                ->count();

            if (Schema::hasTable('fees')) {
                $ledgerAmount = DB::table('fee_payments')
                    ->join('fees', 'fee_payments.fee_id', '=', 'fees.id')
                    ->where('fee_payments.paid_on', $date)
                    ->where('fee_payments.status', 1)
                    ->where('fees.school_id', $school_id)
                    ->sum('fees.amount');

                // Ledger amounts are decimal rupees; normalize to cents.
                $ledgerTotalCents = (int) round(((float) $ledgerAmount) * 100);
            }
        }

        return view('accountant.payments.reconciliation', [
            'date'               => $date,
            'gatewayTotals'      => $gatewayTotals,
            'paymentsTotalCents' => $paymentsTotalCents,
            'ledgerTotalCents'   => $ledgerTotalCents,
            'ledgerCount'        => $ledgerCount,
        ]);
    }
}
