@extends('layouts.accountant.layout')

@section('content')

<div class="">
    <h1 class="admin-h1 my-3 flex items-center">
      	<a href="{{ url('/accountant/payments') }}" title="Back" class="rounded-full bg-gray-100 p-2">
          	<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 492 492" xml:space="preserve" width="512px" height="512px" class="w-3 h-3 fill-current text-gray-700"><g><g><g><path d="M464.344,207.418l0.768,0.168H135.888l103.496-103.724c5.068-5.064,7.848-11.924,7.848-19.124    c0-7.2-2.78-14.012-7.848-19.088L223.28,49.538c-5.064-5.064-11.812-7.864-19.008-7.864c-7.2,0-13.952,2.78-19.016,7.844    L7.844,226.914C2.76,231.998-0.02,238.77,0,245.974c-0.02,7.244,2.76,14.02,7.844,19.096l177.412,177.412    c5.064,5.06,11.812,7.844,19.016,7.844c7.196,0,13.944-2.788,19.008-7.844l16.104-16.112c5.068-5.056,7.848-11.808,7.848-19.008    c0-7.196-2.78-13.592-7.848-18.652L134.72,284.406h329.992c14.828,0,27.288-12.78,27.288-27.6v-22.788    C492,219.198,479.172,207.418,464.344,207.418z" data-original="#000000" fill="" class="active-path"></path></g></g></g></svg>
      	</a>
      	<span class="mx-3">Daily reconciliation</span>
    </h1>
    @include('partials.message')

    <form method="GET" action="{{ url('/accountant/payments/reconciliation') }}" class="flex items-center gap-2 my-4">
        <input type="date" name="date" value="{{ $date }}" class="border rounded p-2 text-sm">
        <button type="submit" class="bg-blue-500 text-white text-sm rounded px-4 py-2">Show</button>
    </form>

    <h2 class="font-semibold my-2">Payments received ({{ $date }})</h2>
    <table class="w-1/2 text-sm border">
        <thead>
            <tr class="bg-gray-100 text-left">
                <th class="p-2 border">Gateway</th>
                <th class="p-2 border">Payments</th>
                <th class="p-2 border">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gatewayTotals as $row)
                <tr>
                    <td class="p-2 border">{{ $row->gateway }}</td>
                    <td class="p-2 border">{{ $row->payments_count }}</td>
                    <td class="p-2 border">{{ \App\Payments\Support\Money::format($row->total_cents) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="p-4 border text-center text-gray-500">No payments on this day.</td></tr>
            @endforelse
            <tr class="bg-gray-50 font-semibold">
                <td class="p-2 border">Total</td>
                <td class="p-2 border">{{ $gatewayTotals->sum('payments_count') }}</td>
                <td class="p-2 border">{{ \App\Payments\Support\Money::format($paymentsTotalCents) }}</td>
            </tr>
        </tbody>
    </table>

    <h2 class="font-semibold my-2 mt-6">Fee ledger comparison</h2>
    @if($ledgerCount === null)
        <p class="text-sm text-gray-500">The fee ledger module is not installed; only the payments side is shown.</p>
    @else
        <table class="w-1/2 text-sm border">
            <tbody>
                <tr>
                    <td class="p-2 border">Ledger entries marked paid</td>
                    <td class="p-2 border">{{ $ledgerCount }}</td>
                </tr>
                @if($ledgerTotalCents !== null)
                    <tr>
                        <td class="p-2 border">Ledger invoiced total</td>
                        <td class="p-2 border">{{ \App\Payments\Support\Money::format($ledgerTotalCents) }}</td>
                    </tr>
                    <tr class="{{ $ledgerTotalCents === (int) $paymentsTotalCents ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                        <td class="p-2 border">Difference (payments − ledger)</td>
                        <td class="p-2 border">{{ \App\Payments\Support\Money::format($paymentsTotalCents - $ledgerTotalCents) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif
</div>
@endsection
