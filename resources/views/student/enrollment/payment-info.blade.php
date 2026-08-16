@extends('layouts.portal')
@section('title', 'Payment')

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4">

    {{-- Header --}}
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Enrollment Payment</h1>
    <p class="text-gray-500 mb-6">{{ $enrollment->program->name }} — Year {{ $enrollment->year_level }}, {{ $enrollment->semester }} Semester</p>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-300 text-green-800 rounded-lg">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-300 text-red-800 rounded-lg">{{ session('error') }}</div>
    @endif

    {{-- Payment Status Block --}}
    @if ($latestPayment)
        <div class="mb-6 p-4 rounded-lg border
            @if ($latestPayment->isVerified()) bg-green-50 border-green-300
            @elseif ($latestPayment->isPending()) bg-yellow-50 border-yellow-300
            @else bg-red-50 border-red-300 @endif">
            <p class="font-semibold
                @if ($latestPayment->isVerified()) text-green-800
                @elseif ($latestPayment->isPending()) text-yellow-800
                @else text-red-800 @endif">
                @if ($latestPayment->isVerified()) ✓ Payment Verified
                @elseif ($latestPayment->isPending()) ⏳ Payment Pending Verification
                @else ✗ Payment Rejected — Please resubmit below @endif
            </p>
            @if ($latestPayment->isVerified())
                <p class="text-sm text-green-700 mt-1">Receipt No: {{ $latestPayment->receipt_no }} &mdash; ₱{{ number_format($latestPayment->amount, 2) }}</p>
            @endif
        </div>
    @endif

    @if (! $enrollment->is_paid)
    {{-- Payment Options --}}
    <p class="text-sm text-gray-500 mb-4">Enrollment fee: <strong>₱{{ number_format($fee, 2) }}</strong></p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">

        {{-- Walk-in Card --}}
        <div class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">🏢 Walk-in (Cashier)</h2>
            <p class="text-sm text-gray-600">Pay in person at the Cashier's Office. Bring this confirmation and a valid ID.</p>
            <p class="text-xs text-gray-400 mt-3">No online action needed — the Cashier will record your payment.</p>
        </div>

        {{-- GCash Card --}}
        <div class="p-6 bg-white border border-blue-200 rounded-xl shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">📱 GCash</h2>

            @if ($gcashQrReady)
                <img src="{{ route('enrollment.gcash.qr') }}" alt="GCash QR Code" class="w-40 h-40 object-contain mx-auto mb-3 border rounded-lg">
            @else
                <div class="w-40 h-40 bg-gray-100 flex items-center justify-center mx-auto mb-3 border rounded-lg text-xs text-gray-400">QR not yet configured</div>
            @endif

            <p class="text-sm text-center text-gray-700 mb-1"><strong>{{ $gcashName ?? '—' }}</strong></p>
            <p class="text-sm text-center text-gray-500 mb-4">{{ $gcashNumber ?? '—' }}</p>

            <ol class="text-xs text-gray-600 space-y-1 mb-4 list-decimal list-inside">
                <li>Open GCash → Send Money / Scan QR</li>
                <li>Enter ₱{{ number_format($fee, 2) }} as the amount</li>
                <li>Take a screenshot of the successful transaction</li>
                <li>Fill in the form below and upload your screenshot</li>
            </ol>

            @php $pendingExists = $latestPayment && $latestPayment->isPending(); @endphp

            @if ($pendingExists)
                <p class="text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded p-2">
                    Your proof has been submitted and is awaiting Cashier review.
                    <a href="{{ route('enrollment.payment.proof', $latestPayment) }}" target="_blank" class="underline ml-1">View submitted proof</a>
                </p>
            @else
                <form method="POST" action="{{ route('enrollment.payment.gcash', $enrollment) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">GCash Reference Number</label>
                        <input type="text" name="reference_number" value="{{ old('reference_number') }}"
                            class="w-full border-gray-300 rounded-md text-sm shadow-sm @error('reference_number') border-red-500 @enderror"
                            placeholder="e.g. 1234567890" required>
                        @error('reference_number') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Proof of Payment (screenshot / PDF, max 5 MB)</label>
                        <input type="file" name="proof_of_payment" accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full text-sm @error('proof_of_payment') border-red-500 @enderror" required>
                        @error('proof_of_payment') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 rounded-lg transition">
                        Submit GCash Proof
                    </button>
                </form>
            @endif
        </div>
    </div>
    @endif

</div>
@endsection