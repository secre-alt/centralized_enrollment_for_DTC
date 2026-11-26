<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\Request;

class ClaimingAppointmentReleaseService
{
    public function release(User $actor, int $appointmentId, array $documentIds, Request $request): void
    {
        abort_unless($actor->hasAnyRole(['registrar', 'admin']) && $request->user()?->id === $actor->id, 403);
        $appointment = Appointment::findOrFail($appointmentId);
        app(WalkInDocumentReleaseService::class)->release($request, $appointment->user_id, $documentIds, $appointmentId);
    }
}
