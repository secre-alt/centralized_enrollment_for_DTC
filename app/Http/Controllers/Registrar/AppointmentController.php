<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AppointmentController extends Controller
{
    // ── Slot Management ───────────────────────────────────────────────────

    public function slots()
    {
        $slots = AppointmentSlot::withCount('appointments')
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate(10);

        return view('registrar.appointments.slots', compact('slots'));
    }

    public function storeSlot(Request $request)
    {
        $validated = $request->validate([
            'date'         => ['required', 'date', 'after_or_equal:today'],
            'start_time'   => ['required'],
            'end_time'     => ['required', 'after:start_time'],
            'max_bookings' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        AppointmentSlot::create($validated);

        return redirect()->route('registrar.appointments.slots')
            ->with('success', 'Appointment slot added successfully.');
    }

    public function updateSlot(Request $request, AppointmentSlot $slot)
    {
        $booked = $slot->appointments()->whereNotIn('status', ['cancelled'])->count();
        $validated = $request->validate([
            'date'         => ['required', 'date'],
            'start_time'   => ['required'],
            'end_time'     => ['required', 'after:start_time'],
            'max_bookings' => ['required', 'integer', 'min:' . $booked, 'max:50'],
        ]);

        $slot->update($validated);

        return redirect()->route('registrar.appointments.slots')
            ->with('success', 'Appointment slot updated successfully.');
    }

    public function deleteSlot(AppointmentSlot $slot)
    {
        $slot->delete();

        return redirect()->route('registrar.appointments.slots')
            ->with('success', 'Slot removed.');
    }

    // ── Appointment Requests ──────────────────────────────────────────────

    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');
        $allowed = ['pending', 'confirmed', 'cancelled', 'all'];
        if (! in_array($status, $allowed)) $status = 'pending';

        $query = Appointment::with(['user', 'slot', 'latestPayment'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $appointments = $query->paginate(15)->withQueryString();

        // Pending GCash payments needing attention (across all appointments)
        $pendingPayments = AppointmentPayment::with(['appointment.user', 'appointment.slot'])
            ->where('status', 'pending')
            ->where('payment_method', 'gcash')
            ->latest()
            ->get();

        $counts = [
            'pending'   => Appointment::where('status', 'pending')->count(),
            'confirmed' => Appointment::where('status', 'confirmed')->count(),
            'cancelled' => Appointment::where('status', 'cancelled')->count(),
        ];

        return view('registrar.appointments.index', compact(
            'appointments', 'pendingPayments', 'status', 'counts'
        ));
    }

    public function confirm(Appointment $appointment)
    {
        $appointment->update(['status' => 'confirmed']);

        NotificationService::send(
            $appointment->user,
            'Appointment Confirmed',
            'Your appointment for ' . ucfirst($appointment->document_type) .
            ' has been confirmed on ' .
            \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') .
            ' at ' .
            \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') . '.',
            'success',
            route('portal.appointments.index')
        );

        return redirect()->route('registrar.appointments.index')
            ->with('success', 'Appointment confirmed and student notified.');
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $appointment->update([
            'status'  => 'cancelled',
            'remarks' => $validated['remarks'],
        ]);

        NotificationService::send(
            $appointment->user,
            'Appointment Cancelled',
            'Your appointment was cancelled. Reason: ' . $validated['remarks'],
            'danger',
            route('portal.appointments.index')
        );

        return redirect()->route('registrar.appointments.index')
            ->with('success', 'Appointment cancelled and student notified.');
    }

    // ── GCash Payment Verification ────────────────────────────────────────

    public function verifyPayment(AppointmentPayment $payment)
    {
        abort_if($payment->status !== 'pending', 422, 'Payment is not pending.');

        $payment->update([
            'status'      => 'verified',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        NotificationService::send(
            $payment->appointment->user,
            'GCash Payment Verified',
            'Your GCash payment for your ' .
            ucfirst($payment->appointment->document_type) .
            ' appointment has been verified.',
            'success',
            route('portal.appointments.index')
        );

        return redirect()->route('registrar.appointments.index')
            ->with('success', 'GCash payment verified and student notified.');
    }

    public function rejectPayment(Request $request, AppointmentPayment $payment)
    {
        abort_if($payment->status !== 'pending', 422, 'Payment is not pending.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $payment->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'verified_by'      => Auth::id(),
            'verified_at'      => now(),
        ]);

        NotificationService::send(
            $payment->appointment->user,
            'GCash Payment Rejected',
            'Your GCash payment was rejected. Reason: ' . $validated['rejection_reason'] .
            '. Please resubmit with the correct details.',
            'danger',
            route('portal.appointments.index')
        );

        return redirect()->route('registrar.appointments.index')
            ->with('success', 'Payment rejected and student notified.');
    }

    public function viewProof(AppointmentPayment $payment)
    {
        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404, 'Proof not found.');
        }

        return response()->file(Storage::disk('local')->path($payment->proof_of_payment));
    }
}
