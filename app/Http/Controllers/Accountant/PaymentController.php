<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Users\StudentUser;
use App\Payments\PaymentService;
use App\Payments\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Exception;
use PDF;

/**
 * Records manual fee payments (cash, cheque, bank transfer, Juice by
 * reference) and streams receipt PDFs.
 */
class PaymentController extends Controller
{
    /**
     * List payments for the current school with optional status filter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $school_id = Auth::user()->school_id;

        $payments = Payment::where('school_id', $school_id)
            ->with('student.userprofile')
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->gateway, function ($query) use ($request) {
                $query->where('gateway', $request->gateway);
            })
            ->latest()
            ->paginate(25);

        $statuses = [
            Payment::STATUS_PENDING, Payment::STATUS_PAID,
            Payment::STATUS_FAILED, Payment::STATUS_EXPIRED,
        ];

        return view('accountant.payments.index', [
            'payments' => $payments,
            'statuses' => $statuses,
            'filters'  => $request->only(['status', 'gateway']),
        ]);
    }

    /**
     * Show the manual payment form.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $school_id = Auth::user()->school_id;

        $students = StudentUser::ByRole(6)->where('school_id', $school_id)->get();

        $openFeePayments = Schema::hasTable('fee_payments')
            ? DB::table('fee_payments')->whereNull('paid_on')->orderBy('id')->get()
            : collect();

        return view('accountant.payments.create', [
            'students'        => $students,
            'openFeePayments' => $openFeePayments,
            'methods'         => config('payments.gateways.manual.methods'),
            'currency'        => Money::currency(),
        ]);
    }

    /**
     * Store a manual payment, mark it paid and queue the receipt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Payments\PaymentService  $service
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, PaymentService $service)
    {
        $school_id = Auth::user()->school_id;

        $rules = [
            'user_id'        => ['required', Rule::exists('users', 'id')->where('school_id', $school_id)],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'method'         => ['required', Rule::in(config('payments.gateways.manual.methods'))],
            'fee_payment_id' => ['nullable', 'integer'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];

        if (Schema::hasTable('fee_payments')) {
            $rules['fee_payment_id'][] = Rule::exists('fee_payments', 'id')->whereNull('paid_on');
        }

        $this->validate($request, $rules);

        try {
            $payment = $service->recordManualPayment([
                'school_id'      => $school_id,
                'user_id'        => $request->user_id,
                'fee_payment_id' => $request->fee_payment_id,
                'amount_cents'   => Money::toCents($request->amount),
                'currency'       => Money::currency(),
                'method'         => $request->method,
                'meta'           => array_filter(['notes' => $request->notes]),
            ], Auth::user());
        } catch (Exception $e) {
            $request->session()->flash('failmessage', 'Payment could not be recorded: '.$e->getMessage());

            return redirect()->back()->withInput();
        }

        $request->session()->flash('successmessage', 'Payment recorded. Reference: '.$payment->provider_reference);

        return redirect(url('/accountant/payments'));
    }

    /**
     * Stream the receipt PDF for a paid payment.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function receipt($id)
    {
        $school_id = Auth::user()->school_id;

        $payment = Payment::where('school_id', $school_id)
            ->with(['student.userprofile', 'school', 'recordedBy.userprofile'])
            ->findOrFail($id);

        abort_unless($payment->isPaid(), 404);

        $filename = 'receipt-'.$payment->provider_reference;

        $pdf = PDF::loadView('accountant.payments.receipt', [
            'payment' => $payment,
            'logo'    => Auth::user()->SchoolLogo['meta_value'] != '-' ? Auth::user()->SchoolLogoPath : null,
        ]);

        return $pdf->stream($filename.'.pdf', ['Attachment' => 0]);
    }
}
