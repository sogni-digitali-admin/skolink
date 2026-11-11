@extends('layouts.accountant.layout')

@section('content')

<div class="">
    <h1 class="admin-h1 my-3 flex items-center">
      	<a href="{{ url('/accountant/payments') }}" title="Back" class="rounded-full bg-gray-100 p-2">
          	<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 492 492" xml:space="preserve" width="512px" height="512px" class="w-3 h-3 fill-current text-gray-700"><g><g><g><path d="M464.344,207.418l0.768,0.168H135.888l103.496-103.724c5.068-5.064,7.848-11.924,7.848-19.124    c0-7.2-2.78-14.012-7.848-19.088L223.28,49.538c-5.064-5.064-11.812-7.864-19.008-7.864c-7.2,0-13.952,2.78-19.016,7.844    L7.844,226.914C2.76,231.998-0.02,238.77,0,245.974c-0.02,7.244,2.76,14.02,7.844,19.096l177.412,177.412    c5.064,5.06,11.812,7.844,19.016,7.844c7.196,0,13.944-2.788,19.008-7.844l16.104-16.112c5.068-5.056,7.848-11.808,7.848-19.008    c0-7.196-2.78-13.592-7.848-18.652L134.72,284.406h329.992c14.828,0,27.288-12.78,27.288-27.6v-22.788    C492,219.198,479.172,207.418,464.344,207.418z" data-original="#000000" fill="" class="active-path"></path></g></g></g></svg>
      	</a>
      	<span class="mx-3">Record manual payment</span>
    </h1>
    @include('partials.message')

    @if($errors->any())
        <div class="alert alert-danger bg-red-200 text-red-500 text-sm p-2 rounded mb-2 w-1/2">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ url('/accountant/payments') }}" class="w-1/2 bg-white rounded shadow p-6">
        @csrf

        <div class="mb-4">
            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">Student</label>
            <select name="user_id" id="user_id" required class="border rounded p-2 w-full">
                <option value="">Select a student</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @if(old('user_id') == $student->id) selected @endif>
                        {{ $student->full_name }} ({{ $student->id }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-4">
            <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount ({{ $currency }})</label>
            <input type="number" name="amount" id="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required
                   class="border rounded p-2 w-full" placeholder="e.g. 1250.00">
        </div>

        <div class="mb-4">
            <label for="method" class="block text-sm font-medium text-gray-700 mb-1">Method</label>
            <select name="method" id="method" required class="border rounded p-2 w-full">
                <option value="">Select a method</option>
                @foreach($methods as $method)
                    <option value="{{ $method }}" @if(old('method') === $method) selected @endif>
                        {{ ucfirst(str_replace('_', ' ', $method)) }}
                    </option>
                @endforeach
            </select>
        </div>

        @if($openFeePayments->isNotEmpty())
            <div class="mb-4">
                <label for="fee_payment_id" class="block text-sm font-medium text-gray-700 mb-1">Fee ledger entry (optional)</label>
                <select name="fee_payment_id" id="fee_payment_id" class="border rounded p-2 w-full">
                    <option value="">Not linked</option>
                    @foreach($openFeePayments as $feePayment)
                        <option value="{{ $feePayment->id }}" @if(old('fee_payment_id') == $feePayment->id) selected @endif>
                            #{{ $feePayment->id }} — student {{ $feePayment->user_id }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="mb-4">
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
            <textarea name="notes" id="notes" rows="3" class="border rounded p-2 w-full"
                      placeholder="Cheque number, Juice reference, …">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="bg-green-500 text-white rounded px-6 py-2">Record payment</button>
    </form>
</div>
@endsection
