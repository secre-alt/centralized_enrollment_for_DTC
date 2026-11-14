<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class FormLabelAssociationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private function assertAssociatedLabels(string $html, string $context): void
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//label') as $label) {
            $target = $label->getAttribute('for');
            if ($target !== '') {
                $control = $document->getElementById($target);
                $this->assertNotNull($control, $context.': missing target '.$target);
                $this->assertContains($control->tagName, ['input', 'select', 'textarea', 'button', 'output', 'progress', 'meter'], $context.': invalid label target '.$target);
                $this->assertNotSame('hidden', $control->getAttribute('type'), $context.': label points to hidden input');
            } else {
                $this->assertGreaterThan(0, $xpath->query('.//input[not(@type="hidden")]|.//select|.//textarea', $label)->length, $context.': unassociated label '.trim($label->textContent));
            }
        }
        $unnamed = [];
        foreach ($xpath->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button")]|//select|//textarea') as $field) {
            $id = $field->getAttribute('id');
            $hasLabel = $id !== '' && $xpath->query('//label[@for="'.$id.'"]')->length > 0;
            if (! $hasLabel && ! $xpath->query('ancestor::label', $field)->length && ! $field->hasAttribute('aria-label') && ! $field->hasAttribute('aria-labelledby')) {
                $unnamed[] = $field->getAttribute('name') ?: $id;
            }
        }
        $this->assertSame([], $unnamed, $context.': fields without accessible labels');
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $id = $element->getAttribute('id');
            $this->assertArrayNotHasKey($id, $ids, $context.': duplicate id '.$id);
            $ids[$id] = true;
        }
    }

    public function test_admin_forms_and_hidden_dialogs_have_resolvable_labels(): void
    {
        $this->seedRoles();
        $admin = $this->makeUserWithRole('admin');
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $this->actingAs($admin);
        foreach ([
            ['admin.users.index', []],
            ['admin.programs.index', ['program' => $program->id]], ['admin.programs.subjects', $program],
            ['admin.settings.general', []], ['admin.settings.academic', []],
            ['admin.settings.payment', []], ['admin.settings.security', []],
        ] as [$route, $parameters]) {
            $response = $this->get(route($route, $parameters))->assertOk();
            $this->assertAssociatedLabels($response->getContent(), $route);
        }
        $this->assertAssociatedLabels(view('admin.users.edit', ['user' => $student])->render(), 'legacy user editor');
    }

    public function test_registrar_forms_have_resolvable_labels(): void
    {
        $this->seedRoles();
        $this->actingAs($this->makeUserWithRole('registrar'));
        foreach (['registrar.appointments.index', 'registrar.appointments.slots', 'registrar.appointments.schedule'] as $route) {
            $this->assertAssociatedLabels($this->get(route($route))->assertOk()->getContent(), $route);
        }
        $enrollment = Enrollment::factory()->create(['status' => 'approved', 'is_paid' => true]);
        $this->assertAssociatedLabels($this->get(route('registrar.enrollments.show', $enrollment))->assertOk()->getContent(), 'enrollment review');
        $application = \App\Models\Application::create([
            'reference_no' => 'LABEL-AUDIT-1', 'program_id' => $enrollment->program_id,
            'first_name' => 'Label', 'last_name' => 'Audit', 'gender' => 'female',
            'birthdate' => '2005-01-01', 'email' => 'label-audit@example.test',
            'academic_status' => 'new_student', 'status' => 'submitted',
        ]);
        $this->assertAssociatedLabels($this->get(route('registrar.applications.show', $application))->assertOk()->getContent(), 'application review');
    }

    public function test_student_enrollment_labels_cover_unlocked_and_locked_programs(): void
    {
        $this->seedRoles();
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $this->actingAs($student);
        $this->assertAssociatedLabels($this->get(route('portal.enrollment.create'))->assertOk()->getContent(), 'enrollment create');
        $this->assertAssociatedLabels(view('student.enrollment.create', ['programs' => null, 'lockedProgram' => $program, 'application' => null, 'fee' => 500])->render(), 'locked program');
    }
}
