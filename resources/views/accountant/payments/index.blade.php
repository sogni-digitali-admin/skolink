@extends('layouts.accountant.layout')

@section('content')

<div class="">
    <h1 class="admin-h1 my-3 flex items-center">
      	<a href="{{ url('/accountant/dashboard') }}" title="Back" class="rounded-full bg-gray-100 p-2">
          	<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 492 492" xml:space="preserve" width="512px" height="512px" class="w-3 h-3 fill-current text-gray-700"><g><g><g><path d="M464.344,207.418l0.768,0.168H135.888l103.496-103.724c5.068-5.064,7.848-11.924,7.848-19.124    c0-7.2-2.78-14.012-7.848-19.088L223.28,49.538c-5.064-5.064-11.812-7.864-19.008-7.864c-7.2,0-13.952,2.78-19.016,7.844    L7.844,226.914C2.76,231.998-0.02,238.77,0,245.974c-0.02,7.244,2.76,14.02,7.844,19.096l177.412,177.412    c5.064,5.06,11.812,7.844,19.016,7.844c7.196,0,13.944-2.788,19.008-7.844l16.104-16.112c5.068-5.056,7.848-11.808,7.848-19.008    c0-7.196-2.78-13.592-7.848-18.652L134.72,284.406h329.992c14.828,0,27.288-12.78,27.288-27.6v-22.788    C492,219.198,479.172,207.418,464.344,207.418z" data-original="#000000" fill="" class="active-path"></path></g></g></g></svg>
      	</a>
      	<span class="mx-3">Payments</span>
    </h1>
    @include('partials.message')

    <div class="flex items-center justify-between my-4">
        <form method="GET" action="{{ url('/accountant/payments') }}" class="flex items-center gap-2">
            <select name="status" class="border rounded p-2 text-sm">
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @if(($filters['status'] ?? '') === $status) selected @endif>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <input type="text" name="gateway" value="{{ $filters['gateway'] ?? '' }}" placeholder="Gateway" class="border rounded p-2 text-sm">
            <button type="submit" class="bg-blue-500 text-white text-sm rounded px-4 py-2">Filter</button>
        </form>
        <div class="flex gap-2">
            <a href="{{ url('/accountant/payments/reconciliation') }}" class="bg-gray-200 text-gray-700 text-sm rounded px-4 py-2">Reconciliation</a>
            <a href="{{ url('/accountant/payments/create') }}" class="bg-green-500 text-white text-sm rounded px-4 py-2">Record payment</a>
        </div>
    </div>

    <table class="w-full text-sm border">
        <thead>
            <tr class="bg-gray-100 text-left">
                <th class="p-2 border">Reference</th>
                <th class="p-2 border">Student</th>
                <th class="p-2 border">Amount</th>
                <th class="p-2 border">Gateway / Method</th>
                <th class="p-2 border">Status</th>
                <th class="p-2 border">Paid at</th>
                <th class="p-2 border">Receipt</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td class="p-2 border font-mono text-xs">{{ $payment->provider_reference }}</td>
                    <td class="p-2 border">{{ optional($payment->student)->full_name ?? $payment->user_id }}</td>
                    <td class="p-2 border">{{ $payment->formatted_amount }}</td>
                    <td class="p-2 border">{{ $payment->gateway }}@if($payment->method) / {{ str_replace('_', ' ', $payment->method) }}@endif</td>
                    <td class="p-2 border">
                        @if($payment->status === 'paid')
                            <span class="bg-green-100 text-green-700 rounded px-2 py-1 text-xs">Paid</span>
                        @elseif($payment->status === 'pending')
                            <span class="bg-yellow-100 text-yellow-700 rounded px-2 py-1 text-xs">Pending</span>
                        @elseif($payment->status === 'failed')
                            <span class="bg-red-100 text-red-700 rounded px-2 py-1 text-xs">Failed</span>
                        @else
                            <span class="bg-gray-100 text-gray-600 rounded px-2 py-1 text-xs">Expired</span>
                        @endif
                    </td>
                    <td class="p-2 border">{{ optional($payment->paid_at)->format('d M Y H:i') }}</td>
                    <td class="p-2 border">
                        @if($payment->status === 'paid')
                            <a href="{{ url('/accountant/payments/'.$payment->id.'/receipt') }}" target="_blank" class="text-blue-600 underline">PDF</a>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-4 border text-center text-gray-500">No payments found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="my-4">
        {{ $payments->appends($filters)->links() }}
    </div>
</div>
@endsection
