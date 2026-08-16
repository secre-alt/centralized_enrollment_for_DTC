@extends('layouts.admin')
@section('title', 'Payment Settings')

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Payment Settings</h1>

    @if (session('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-300 text-green-800 rounded-lg">{{ session('success') }}</div>
    @endif

    {{-- Text settings (existing) --}}
    <form method="POST" action="{{ route('admin.settings.payment.update') }}" class="space-y-4 mb-8">
        @csrf @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Enrollment Fee (₱)</label>
            <input type="number" name="enrollment_fee" step="0.01" min="0"
                value="{{ old('enrollment_fee', Setting::get('enrollment_fee', 500)) }}"
                class="w-full border-gray-300 rounded-md shadow-sm" required>
            @error('enrollment_fee') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">GCash Account Name</label>
            <input type="text" name="gcash_name"
                value="{{ old('gcash_name', Setting::get('gcash_name')) }}"
                class="w-full border-gray-300 rounded-md shadow-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">GCash Number</label>
            <input type="text" name="gcash_number"
                value="{{ old('gcash_number', Setting::get('gcash_number')) }}"
                class="w-full border-gray-300 rounded-md shadow-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Deadline</label>
            <input type="date" name="payment_deadline"
                value="{{ old('payment_deadline', Setting::get('payment_deadline')) }}"
                class="w-full border-gray-300 rounded-md shadow-sm">
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2 rounded-lg">
            Save Settings
        </button>
    </form>

    {{-- QR Upload (separate form — different enctype) --}}
    <div class="border-t pt-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">GCash QR Code</h2>

        @if (Setting::get('gcash_qr_path') && Storage::disk('local')->exists(Setting::get('gcash_qr_path')))
            <div class="mb-4">
                <p class="text-xs text-gray-500 mb-2">Current QR:</p>
                <img src="{{ route('enrollment.gcash.qr') }}" alt="GCash QR" class="w-40 h-40 object-contain border rounded-lg">
            </div>
        @else
            <p class="text-sm text-gray-500 mb-3">No QR code uploaded yet.</p>
        @endif

        <form method="POST" action="{{ route('admin.settings.payment.qr') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Upload QR Image (PNG/JPG, max 2 MB)</label>
                <input type="file" name="gcash_qr" accept=".jpg,.jpeg,.png" class="text-sm" required>
                @error('gcash_qr') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium px-4 py-2 rounded-lg">
                Upload QR
            </button>
        </form>
    </div>
</div>
@endsection