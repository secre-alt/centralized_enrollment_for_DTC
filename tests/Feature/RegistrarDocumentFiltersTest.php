<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class RegistrarDocumentFiltersTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_filters_combine_and_status_counts_follow_filters_across_tabs(): void
    {
        $this->seedRoles();
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni', ['name' => 'Filter Requester']);
        $slot = AppointmentSlot::factory()->create(['date' => '2026-11-18']);
        $appointment = Appointment::factory()->create(['user_id' => $alumni->id, 'appointment_slot_id' => $slot->id]);
        $attributes = ['user_id' => $alumni->id, 'appointment_id' => $appointment->id, 'document_type' => 'tor',
            'payment_status' => 'verified', 'is_paid' => true, 'fee' => 50];
        $match = DocumentRequest::create($attributes + ['status' => 'ready']);
        DocumentRequest::create($attributes + ['status' => 'submitted']);
        DocumentRequest::create(array_merge($attributes, ['status' => 'ready', 'document_type' => 'diploma']));
        DocumentRequest::create(array_merge($attributes, ['status' => 'ready', 'payment_status' => 'unpaid', 'is_paid' => false]));
        DocumentRequest::create(array_merge($attributes, ['status' => 'ready', 'appointment_id' => null]));
        $params = ['search' => 'Filter Requester', 'status' => 'ready', 'document_type' => 'tor',
            'payment_status' => 'verified', 'pickup_from' => '2026-11-18', 'pickup_to' => '2026-11-18', 'per_page' => 5];
        $response = $this->actingAs($registrar)->get(route('registrar.documents.index', $params))->assertOk();
        $this->assertSame([$match->id], $response->viewData('requests')->pluck('id')->all());
        $this->assertSame(2, (int) $response->viewData('allCount'));
        $this->assertSame(1, (int) $response->viewData('statusCounts')['submitted']);
        $response->assertSee('Document type')->assertSee('Pickup from')->assertSee('collapse show', false);
        $response->assertSee('pickup_from=2026-11-18')->assertSee('payment_status=verified');
        $this->get(route('registrar.documents.index', ['pickup_to' => '2026-11-17']))->assertOk()
            ->assertViewHas('requests', fn ($rows) => $rows->total() === 0);
    }

    public function test_no_payment_required_is_distinct_from_unpaid_and_invalid_filters_fail(): void
    {
        $this->seedRoles();
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $free = DocumentRequest::create(['user_id' => $alumni->id, 'document_type' => 'certification', 'fee' => 0,
            'is_paid' => false, 'payment_status' => 'unpaid', 'status' => 'submitted']);
        $unpaid = DocumentRequest::create(['user_id' => $alumni->id, 'document_type' => 'tor', 'fee' => 50,
            'is_paid' => false, 'payment_status' => 'unpaid', 'status' => 'submitted']);
        $this->actingAs($registrar)->get(route('registrar.documents.index', ['payment_status' => 'no_payment_required']))
            ->assertOk()->assertViewHas('requests', fn ($rows) => $rows->pluck('id')->all() === [$free->id]);
        $this->get(route('registrar.documents.index', ['payment_status' => 'unpaid']))
            ->assertOk()->assertViewHas('requests', fn ($rows) => $rows->pluck('id')->all() === [$unpaid->id]);
        foreach ([['document_type' => 'invalid'], ['payment_status' => 'invalid'], ['pickup_from' => 'bad-date'],
            ['pickup_from' => '2026-11-20', 'pickup_to' => '2026-11-18']] as $params) {
            $this->getJson(route('registrar.documents.index', $params))->assertUnprocessable();
        }
        $this->actingAs($alumni)->get(route('registrar.documents.index'))->assertForbidden();
    }
}
