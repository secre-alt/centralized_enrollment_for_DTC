<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;

class ClaimingAppointmentController extends Controller
{
    public function index(Request $request)
    {
        $appointments = Appointment::with(['slot', 'documentItems.documentRequest', 'documentItems.releaser'])->where('user_id', $request->user()->id)
            ->where('visit_type', 'claiming')->latest('id')->get();
        return view('portal.claiming-appointments', compact('appointments'));
    }

    public function store(Request $request)
    {
        throw \Illuminate\Validation\ValidationException::withMessages(['appointment_slot_id' => 'Claiming bookings are retired. Collect ready, payment-satisfied documents at the Registrar during published office hours.']);
    }
}
