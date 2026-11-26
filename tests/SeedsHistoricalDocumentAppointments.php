<?php

namespace Tests;

use App\Services\DocumentAppointmentBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

trait SeedsHistoricalDocumentAppointments
{
    // Explicit legacy fixtures preserve bundle regression coverage after the public submission route changes.
    protected function historicalSubmission(array $data, bool $studentCodes = true): TestResponse
    {
        session()->forget('errors');
        $input = $data;
        unset($input['proof_of_payment']);
        $request = Request::create('/historical-document-fixture', 'POST', $input);
        $request->setUserResolver(fn () => Auth::user());
        $documents = array_values($data['documents'] ?? [['document_type' => $data['document_type'], 'copies' => $data['copies'] ?? 1]]);
        $type = $documents[0]['document_type'];
        foreach ($documents as &$document) {
            if ($studentCodes) {
                $document['document_type'] = match ($document['document_type']) {
                    'transcript' => 'tor', 'tor' => 'true_copy', default => $document['document_type'],
                };
            }
        }
        unset($document);
        $proof = isset($data['proof_of_payment']) ? $data['proof_of_payment']->store('document-request-proofs', 'local') : null;
        app(DocumentAppointmentBookingService::class)->book(Auth::user(), $data['appointment_slot_id'], $documents,
            $type, $data['purpose'] ?? null, ($data['payment_method'] ?? 'walk_in') === 'walk_in' ? 'walk_in' : 'gcash',
            $data['reference_number'] ?? null, $proof, $request);

        return TestResponse::fromBaseResponse(redirect()->route($studentCodes ? 'portal.appointments.index' : 'portal.documents.index')->with('success', 'Historical fixture created.'));
    }
}
