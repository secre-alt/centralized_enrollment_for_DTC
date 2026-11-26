<?php

namespace App\Http\Middleware;

use App\Services\AppointmentLifecycle;
use Closure;
use Illuminate\Http\Request;

class AppointmentLifecycleCommand
{
    public function handle(Request $request, Closure $next, string $action)
    {
        $appointment = $request->route('appointment');
        $document = $request->route('documentRequest');
        if ($action === 'legacy.document_released') {
            if ($request->input('status') !== 'released' || ! $document->appointment_id) {
                return $next($request);
            }
            $appointment = $document->appointment;
        }
        if (str_starts_with($request->route()->getName(), 'portal.')) {
            abort_if($appointment && $appointment->user_id !== $request->user()->id, 403);
            abort_if($document && $document->user_id !== $request->user()->id, 403);
        }
        $request->validate(['operation_key' => ['required', 'uuid'],
            'lifecycle_version' => $appointment ? ['required', 'integer', 'min:0'] : ['nullable', 'integer', 'min:0']]);
        $lifecycle = app(AppointmentLifecycle::class);
        if ($lifecycle->replay($request, $action, $lifecycle->fingerprint($request, $action))) {
            $destination = match ($action) {
                'claim.booked' => 'portal.claiming-appointments.index',
                'claim.released' => 'registrar.claiming-appointments.index',
                'appointment.confirmed' => 'registrar.documents.index',
                'legacy.pickup_booked' => 'portal.documents.index',
                'legacy.document_released' => 'registrar.documents.index',
                default => str_starts_with($request->route()->getName(), 'registrar.') ? 'registrar.appointments.index'
                    : ($request->route()->getName() === 'portal.documents.store' ? 'portal.documents.index' : 'portal.appointments.index'),
            };

            return redirect()->route($destination)->with('success', 'This operation was already completed; its recorded outcome was preserved.');
        }

        return $next($request);
    }
}
