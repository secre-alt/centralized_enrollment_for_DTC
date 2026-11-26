<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\ClaimingAppointmentReleaseService;
use Illuminate\Http\Request;

class ClaimingAppointmentController extends Controller
{
    public function index()
    {
        $appointments = Appointment::where('visit_type', 'claiming')
            ->with(['user', 'slot', 'documentItems.documentRequest', 'documentItems.releaser'])
            ->latest('id')->paginate(\App\Support\TablePagination::perPage(10))->withQueryString();

        return view('registrar.appointments.claiming', compact('appointments'));
    }

    public function release(Request $request, Appointment $appointment, ClaimingAppointmentReleaseService $service)
    {
        $validated = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['required', 'integer', 'distinct'],
            'handover_confirmed' => ['required', 'accepted'],
        ]);
        $service->release($request->user(), $appointment->id, $validated['document_ids'], $request);

        return redirect()->route('registrar.claiming-appointments.index')->with('success', 'Selected documents recorded as physically handed over.');
    }
}
