<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class CashierPaymentFiltersTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_filters_combine_before_pagination_and_use_the_enrollments_own_term(): void
    {
        $this->seedRoles();
        $this->actingAs($this->makeUserWithRole('cashier'));
        $target = Enrollment::factory()->create(['school_year' => '2025-2026', 'year_level' => 2, 'semester' => 2, 'created_at' => '2026-01-12 23:59:59']);
        Payment::factory()->gcashPending()->create(['enrollment_id' => $target->id]);
        $other = Enrollment::factory()->create(['program_id' => $target->program_id, 'school_year' => '2026-2027']);
        Payment::factory()->create(['enrollment_id' => $other->id]);
        $filters = ['program_id' => $target->program_id, 'school_year' => '2025-2026', 'year_level' => 2, 'semester' => 2, 'method' => 'gcash', 'submitted_from' => '2026-01-12', 'submitted_to' => '2026-01-12', 'filter' => 'gcash_pending'];
        $this->get(route('cashier.payments.index', $filters))->assertOk()
            ->assertViewHas('enrollments', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id);
        foreach (['program_id', 'school_year', 'year_level', 'semester', 'method', 'submitted_from', 'submitted_to'] as $field) {
            $this->get(route('cashier.payments.index', [$field => $filters[$field]]))->assertOk()
                ->assertViewHas('enrollments', fn ($rows) => $rows->contains('id', $target->id));
        }
        $this->get(route('cashier.payments.index', ['method' => 'walk_in']))->assertOk()
            ->assertViewHas('enrollments', fn ($rows) => ! $rows->contains('id', $target->id));
        $this->get(route('cashier.payments.index', ['search' => $target->user->email] + $filters))->assertOk()
            ->assertViewHas('enrollments', fn ($rows) => $rows->total() === 1);
        foreach ([['method' => 'invalid'], ['program_id' => 999999], ['year_level' => 0], ['submitted_from' => 'invalid'], ['submitted_from' => '2026-02-01', 'submitted_to' => '2026-01-01']] as $invalid) {
            $this->getJson(route('cashier.payments.index', $invalid))->assertUnprocessable();
        }
        $this->actingAs($this->makeUserWithRole('student'))
            ->get(route('cashier.payments.index', $filters))->assertForbidden();
    }
}
