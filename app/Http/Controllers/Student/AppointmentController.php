<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AppointmentController extends Controller
{
    public function index()
    {
        $appointments = Appointment::with(['slot', 'latestPayment'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->isAvailable())
            ->values();

        return view('student.appointments.index', compact('appointments', 'slots'));
    }

    public function create()
    {
        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->isAvailable())
            ->values();

        $gcashNumber  = Setting::get('gcash_number');
        $gcashName    = Setting::get('gcash_name');
        $gcashQrReady = Setting::get('gcash_qr_path') && Storage::disk('local')->exists(Setting::get('gcash_qr_path'));
        $processingFee = Setting::get('appointment_processing_fee', 0);

        return view('student.appointments.create', compact(
            'slots', 'gcashNumber', 'gcashName', 'gcashQrReady', 'processingFee'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'exists:appointment_slots,id'],
            'document_type'       => ['required', 'in:transcript,diploma,certification,tor'],
            'purpose'             => ['nullable', 'string', 'max:500'],
            'payment_method'      => ['required', 'in:walk_in,gcash'],
            'reference_number'    => ['required_if:payment_method,gcash', 'nullable', 'string', 'max:50'],
            'proof_of_payment'    => ['required_if:payment_method,gcash', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $slot = AppointmentSlot::findOrFail($validated['appointment_slot_id']);

        if (! $slot->isAvailable()) {
            return back()->withErrors([
                'appointment_slot_id' => 'This slot is no longer available. Please select another.',
            ])->withInput();
        }

        $existing = Appointment::where('user_id', Auth::id())
            ->where('appointment_slot_id', $validated['appointment_slot_id'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($existing) {
            return back()->withErrors([
                'appointment_slot_id' => 'You already have a booking for this slot.',
            ])->withInput();
        }

        $appointment = Appointment::create([
            'user_id'             => Auth::id(),
            'appointment_slot_id' => $validated['appointment_slot_id'],
            'document_type'       => $validated['document_type'],
            'purpose'             => $validated['purpose'],
            'status'              => 'pending',
        ]);

        // Record payment if applicable
        $fee = (float) Setting::get('appointment_processing_fee', 0);
        if ($fee > 0 || $validated['payment_method'] === 'gcash') {
            $paymentData = [
                'appointment_id' => $appointment->id,
                'payment_method' => $validated['payment_method'],
                'amount'         => $fee,
                'status'         => $validated['payment_method'] === 'gcash' ? 'pending' : 'walk_in_pending',
            ];

            if ($validated['payment_method'] === 'gcash') {
                $paymentData['reference_number'] = $validated['reference_number'];
                $paymentData['proof_of_payment'] = $request->file('proof_of_payment')
                    ->store('appointment-proofs', 'local');
            }

            AppointmentPayment::create($paymentData);
        }

        // Notify registrars
        $notifyUsers = User::role(['registrar', 'admin'])->get();
        foreach ($notifyUsers as $staff) {
            NotificationService::send(
                $staff,
                'New Appointment Request',
                Auth::user()->name . ' has booked an appointment for ' .
                ucfirst($appointment->document_type) . ' on ' .
                \Carbon\Carbon::parse($slot->date)->format('M d, Y') .
                ' at ' . \Carbon\Carbon::parse($slot->start_time)->format('h:i A') . '.',
                'info',
                route('registrar.appointments.index')
            );
        }

        return redirect()->route('portal.appointments.index')
            ->with('success', 'Appointment booked successfully! Please wait for the Registrar to confirm.');
    }

    public function edit(Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);

        if ($appointment->status !== 'pending') {
            return redirect()->route('portal.appointments.index')
                ->with('error', 'Only pending appointments can be edited.');
        }

        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->id === $appointment->appointment_slot_id || $slot->isAvailable())
            ->values();

        return view('student.appointments.edit', compact('appointment', 'slots'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);

        if ($appointment->status !== 'pending') {
            return redirect()->route('portal.appointments.index')
                ->with('error', 'Only pending appointments can be edited.');
        }

        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'exists:appointment_slots,id'],
            'document_type'       => ['required', 'in:transcript,diploma,certification,tor'],
            'purpose'             => ['required', 'string', 'max:500'],
        ]);

        // If slot changed, verify the new slot is available
        if ($validated['appointment_slot_id'] != $appointment->appointment_slot_id) {
            $slot = AppointmentSlot::findOrFail($validated['appointment_slot_id']);

            if (! $slot->isAvailable()) {
                return back()->withErrors([
                    'appointment_slot_id' => 'This slot is no longer available. Please select another.',
                ])->withInput();
            }
        }

        $changes = [];
        if ($appointment->document_type !== $validated['document_type']) {
            $changes[] = 'document type changed to ' . ucfirst($validated['document_type']);
        }
        if ($appointment->appointment_slot_id != $validated['appointment_slot_id']) {
            $newSlot = AppointmentSlot::find($validated['appointment_slot_id']);
            $changes[] = 'schedule changed to ' . \Carbon\Carbon::parse($newSlot->date)->format('M d, Y')
                . ' at ' . \Carbon\Carbon::parse($newSlot->start_time)->format('h:i A');
        }
        if ($appointment->purpose !== $validated['purpose']) {
            $changes[] = 'purpose updated';
        }

        $appointment->update($validated);

        // Notify registrars of the update
        $slot = AppointmentSlot::find($appointment->appointment_slot_id);
        $summary = count($changes)
            ? implode('; ', $changes)
            : 'details updated';

        $notifyUsers = User::role(['registrar', 'admin'])->get();
        foreach ($notifyUsers as $staff) {
            NotificationService::send(
                $staff,
                'Appointment Updated',
                Auth::user()->name . ' has updated their appointment booking (' . $summary . ').',
                'warning',
                route('registrar.appointments.index')
            );
        }

        return redirect()->route('portal.appointments.index')
            ->with('success', 'Appointment updated successfully. The Registrar has been notified.');
    }

    public function cancel(Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);

        if ($appointment->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending appointments can be cancelled.']);
        }

        $appointment->update(['status' => 'cancelled']);

        $notifyUsers = User::role(['registrar', 'admin'])->get();
        foreach ($notifyUsers as $staff) {
            NotificationService::send(
                $staff,
                'Appointment Cancelled',
                Auth::user()->name . ' has cancelled their appointment booking for ' .
                ucfirst($appointment->document_type) . '.',
                'warning',
                route('registrar.appointments.index')
            );
        }

        return redirect()->route('portal.appointments.index')
            ->with('success', 'Appointment cancelled.');
    }

    public function submitGcash(Request $request, Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);

        if ($appointment->status === 'cancelled') {
            return back()->with('error', 'Cannot submit payment for a cancelled appointment.');
        }

        $pendingExists = $appointment->payments()->where('status', 'pending')->exists();
        if ($pendingExists) {
            return back()->with('error', 'You already have a payment pending verification.');
        }

        $request->validate([
            'reference_number' => ['required', 'string', 'max:50'],
            'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof_of_payment')->store('appointment-proofs', 'local');

        $fee = (float) Setting::get('appointment_processing_fee', 0);

        AppointmentPayment::create([
            'appointment_id'   => $appointment->id,
            'payment_method'   => 'gcash',
            'reference_number' => $request->reference_number,
            'proof_of_payment' => $path,
            'amount'           => $fee,
            'status'           => 'pending',
        ]);

        return back()->with('success', 'GCash payment proof submitted. The Cashier will verify your payment shortly.');
    }

    public function viewProof(AppointmentPayment $payment)
    {
        abort_if($payment->appointment->user_id !== Auth::id(), 403);

        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404, 'Proof not found.');
        }

        return response()->file(Storage::disk('local')->path($payment->proof_of_payment));
    }

    public function showGcashQr()
    {
        $path = Setting::get('gcash_qr_path');
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }
        return response()->file(Storage::disk('local')->path($path));
    }
}
