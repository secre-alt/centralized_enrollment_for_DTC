<?php

namespace Tests;

use App\Services\ClaimingAppointmentBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

trait SeedsHistoricalClaimingAppointments
{
    // Explicit historical fixture; the public booking endpoint is independently tested as retired.
    protected function historicalClaiming(array $data): TestResponse
    {
        session()->forget('errors');
        $request = Request::create('/historical-claiming-fixture', 'POST', $data + ['operation_key' => (string) Str::uuid()]);
        $request->setUserResolver(fn () => Auth::user());
        try {
            $request->validate(['appointment_slot_id' => ['required', 'integer', 'exists:appointment_slots,id'],
                'document_ids' => ['required', 'array', 'min:1'], 'document_ids.*' => ['integer', 'distinct']]);
            app(ClaimingAppointmentBookingService::class)->book(Auth::user(), (int) $data['appointment_slot_id'], $data['document_ids'], $request);

            return TestResponse::fromBaseResponse(redirect()->route('portal.claiming-appointments.index'));
        } catch (ValidationException $exception) {
            return TestResponse::fromBaseResponse(redirect()->back()->withErrors($exception->errors()));
        }
    }
}
