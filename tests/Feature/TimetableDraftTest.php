<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\Program;
use App\Models\Setting;
use App\Models\TimetableDraft;
use App\Models\TimetableResource;
use App\Services\TimetableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class TimetableDraftTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->actingAs($this->makeUserWithRole('admin'));
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
    }

    private function setupDraft(string $code = 'BSTM', array $attributes = []): array
    {
        $program = Program::factory()->create(['code' => $code]);
        $subject = CourseSubject::factory()->create(['program_id' => $program->id, 'year_level' => 1, 'semester' => 1, 'units' => 3]);
        $this->post(route('admin.timetables.store'), array_merge([
            'program_id' => $program->id, 'year_level' => 1, 'group_code' => $code.' 1A',
            'capacity' => 30, 'is_demo' => 0, 'closing_hour' => 17,
        ], $attributes))->assertSessionHasNoErrors();

        return [TimetableDraft::latest('id')->first(), $subject];
    }

    private function resources(): array
    {
        $room = TimetableResource::create(['kind' => 'room', 'name' => '1T', 'configuration' => ['capacity' => 40, 'facility' => 'classroom', 'active' => true]]);
        $instructor = TimetableResource::create(['kind' => 'instructor', 'name' => 'Test instructor', 'configuration' => ['active' => true, 'availability' => array_fill_keys(TimetableService::DAYS, ['start' => '08:00', 'end' => '19:00'])]]);

        return [$room, $instructor];
    }

    private function row(CourseSubject $subject, TimetableResource $room, TimetableResource $instructor, array $changes = []): array
    {
        return array_merge(['subject_id' => $subject->id, 'instructor_id' => (string) $instructor->id, 'room_id' => (string) $room->id,
            'pattern' => 'MWF', 'minutes' => 60, 'facility' => 'classroom', 'start' => '08:00', 'end' => '09:00'], $changes);
    }

    private function save(TimetableDraft $draft, array $rows, string $action = 'review')
    {
        return $this->put(route('admin.timetables.update', $draft), ['action' => $action, 'rows' => $rows]);
    }

    private function publish(TimetableDraft $draft, array $extra = [])
    {
        return $this->put(route('admin.timetables.update', $draft), $extra + ['action' => 'publish', 'reviewed' => 1]);
    }

    public function test_workspace_shows_saved_assignments_and_published_resource_locks(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        $url = route('admin.programs.index', ['timetable' => $draft->id]);
        $this->get($url)->assertOk()->assertSee('0 of 1 subjects placed')->assertSee('Needs assignment');
        $this->save($draft, [$this->row($subject, $room, $instructor)])->assertSessionHasNoErrors();
        $this->get($url)->assertOk()->assertSee('1 of 1 subjects placed')->assertSee('Saved schedule')
            ->assertSee('timetable-review.js');
        $this->publish($draft)->assertSessionHasNoErrors();
        $this->get($url)->assertOk()->assertSee('Read only: used by a published timetable')
            ->assertSee('Eligible new enrollments can be assigned')->assertDontSee('Automatic student assignment is a later stage');
    }

    public function test_demonstration_uses_only_fictional_resources_and_cannot_publish(): void
    {
        [$draft] = $this->setupDraft('BSTM', ['is_demo' => 1]);
        $this->assertNotEmpty($draft->proposals);
        $this->assertNull($draft->proposals[0]['issue']);
        $this->assertSame(0, ClassSchedule::count());
        $this->assertSame(0, TimetableResource::count());
        $this->get(route('admin.programs.index', ['timetable' => $draft->id]))->assertOk()->assertSee('Demonstration only')->assertSee('DEMO 1T');
        $this->publish($draft)->assertSessionHasErrors('timetable');
        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_all_four_programs_share_rooms_and_instructors_without_overlaps(): void
    {
        [$room, $instructor] = $this->resources();
        $starts = [];
        foreach (['BSTM', 'BSIS', 'BSCRIM', 'BTVTEd'] as $code) {
            [$draft, $subject] = $this->setupDraft($code);
            $this->save($draft, [$this->row($subject, $room, $instructor)], 'generate')->assertSessionHasNoErrors();
            $draft->refresh();
            $this->assertNull($draft->proposals[0]['issue']);
            $starts[] = $draft->proposals[0]['start'];
            $this->assertSame(count($starts) - 1, ClassSchedule::count());
            $this->publish($draft)->assertSessionHasNoErrors();
            $offering = ClassSchedule::where('timetable_draft_id', $draft->id)->firstOrFail();
            $this->assertSame($draft->program_id, $offering->hosting_program_id);
            $this->assertSame('1T', $offering->room);
            $this->assertTrue((bool) $offering->is_active);
            $this->assertSame($subject->id, $offering->course_subject_id);
        }
        $this->assertSame(['08:00', '09:00', '10:00', '11:00'], $starts);
    }

    public function test_publish_rechecks_competing_drafts_and_rolls_back_all_offerings(): void
    {
        [$room, $instructor] = $this->resources();
        [$first, $firstSubject] = $this->setupDraft('BSIS');
        [$second, $secondSubject] = $this->setupDraft('BSTM');
        $this->save($first, [$this->row($firstSubject, $room, $instructor)])->assertSessionHasNoErrors();
        $this->save($second, [$this->row($secondSubject, $room, $instructor)])->assertSessionHasNoErrors();
        $this->publish($first)->assertSessionHasNoErrors();
        $this->publish($second)->assertSessionHasErrors('timetable');
        $this->assertSame('draft', $second->fresh()->status);
        $this->assertSame(1, ClassSchedule::count());
        $this->publish($first)->assertSessionHasErrors('timetable');
        $this->assertSame(1, ClassSchedule::count());
    }

    public function test_lunch_capacity_facility_availability_and_incomplete_times_are_flagged(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        foreach ([
            ['start' => '11:30', 'end' => '12:30'], ['start' => '17:00', 'end' => '18:00'],
            ['facility' => 'computer_lab'], ['start' => null], ['instructor_id' => null],
        ] as $changes) {
            $this->save($draft, [$this->row($subject, $room, $instructor, $changes)])->assertSessionHasNoErrors();
            $this->assertNotEmpty($draft->fresh()->proposals[0]['issue']);
            $this->publish($draft)->assertSessionHasErrors('timetable');
        }
        $room->update(['configuration' => ['active' => true, 'capacity' => 20, 'facility' => 'classroom']]);
        $this->save($draft, [$this->row($subject, $room, $instructor)], 'generate')->assertSessionHasNoErrors();
        $this->assertNotEmpty($draft->fresh()->proposals[0]['issue']);
        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_weekend_and_evening_need_explicit_requirements_and_availability(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft('BSTM', ['closing_hour' => 19]);
        $this->save($draft, [$this->row($subject, $room, $instructor, ['pattern' => 'SA', 'minutes' => 180, 'start' => '08:00', 'end' => '11:00'])])->assertSessionHasNoErrors();
        $this->assertNull($draft->fresh()->proposals[0]['issue']);
        $this->save($draft, [$this->row($subject, $room, $instructor, ['pattern' => 'TTH', 'minutes' => 90, 'start' => '17:30', 'end' => '19:00'])])->assertSessionHasNoErrors();
        $this->assertNull($draft->fresh()->proposals[0]['issue']);
        $instructor->update(['configuration' => ['active' => true, 'availability' => ['TU' => ['start' => '08:00', 'end' => '17:00']]]]);
        $this->publish($draft)->assertSessionHasErrors('timetable');
    }

    public function test_student_group_conflicts_even_with_different_rooms_and_instructors(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        $second = CourseSubject::factory()->create(['program_id' => $draft->program_id, 'year_level' => 1, 'semester' => 1]);
        $otherRoom = $room->replicate();
        $otherRoom->name = '2C';
        $otherRoom->save();
        $otherInstructor = $instructor->replicate();
        $otherInstructor->name = 'Second instructor';
        $otherInstructor->save();
        $this->save($draft, [$this->row($subject, $room, $instructor), $this->row($second, $otherRoom, $otherInstructor)])->assertSessionHasNoErrors();
        $this->assertNotEmpty($draft->fresh()->proposals[1]['issue']);
        $this->publish($draft)->assertSessionHasErrors('timetable');
        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_forged_duplicate_and_missing_curriculum_subjects_are_rejected(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        $foreign = CourseSubject::factory()->create();
        foreach ([[], [$this->row($foreign, $room, $instructor)], [$this->row($subject, $room, $instructor), $this->row($subject, $room, $instructor)]] as $rows) {
            $this->save($draft, $rows)->assertSessionHasErrors();
        }
        $this->assertNull($draft->fresh()->proposals);
    }

    public function test_draft_keeps_its_own_term_when_current_settings_change(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        Setting::set('current_school_year', '2027-2028');
        Setting::set('current_semester', '2nd');
        $this->save($draft, [$this->row($subject, $room, $instructor)])->assertSessionHasNoErrors();
        $this->publish($draft)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('class_schedules', ['timetable_draft_id' => $draft->id, 'school_year' => '2026-2027', 'semester' => 1]);
    }

    public function test_publication_requires_explicit_review_and_protects_published_records(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        $this->save($draft, [$this->row($subject, $room, $instructor)])->assertSessionHasNoErrors();
        $this->publish($draft, ['reviewed' => 0])->assertSessionHasErrors('reviewed');
        $this->publish($draft)->assertSessionHasNoErrors();
        $schedule = ClassSchedule::where('timetable_draft_id', $draft->id)->firstOrFail();
        $this->delete(route('admin.programs.subjects.schedule.destroy', $schedule))->assertSessionHasErrors('schedule');
        $this->post(route('admin.programs.subjects.schedule.store', $subject), ['section_code' => $draft->group_code, 'capacity' => 30,
            'day_pattern' => 'MWF', 'time_start' => '09:00', 'time_end' => '10:00', 'room' => '2T'])->assertSessionHasErrors('schedule');
        $this->save($draft, [$this->row($subject, $room, $instructor)], 'generate')->assertSessionHasErrors('timetable');
        $this->post(route('admin.timetables.resources'), ['id' => $room->id, 'kind' => 'room', 'name' => 'RENAMED', 'capacity' => 40, 'facility' => 'classroom', 'active' => 1])->assertSessionHasErrors('resource');
        $this->assertSame('08:00:00', $schedule->fresh()->time_start);
        $this->assertSame('1T', $room->fresh()->name);
        $this->delete(route('admin.programs.subjects.destroy', $subject))->assertSessionHasErrors('subject');
        $this->put(route('admin.programs.subjects.update', $subject), [])->assertSessionHasErrors('subject');
        $this->delete(route('admin.programs.destroy', $draft->program_id))->assertSessionHasErrors('program');
    }

    public function test_manual_schedule_cannot_conflict_with_published_resource(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        $this->save($draft, [$this->row($subject, $room, $instructor)])->assertSessionHasNoErrors();
        $this->publish($draft)->assertSessionHasNoErrors();
        $otherSubject = CourseSubject::factory()->create();
        $this->post(route('admin.programs.subjects.schedule.store', $otherSubject), ['section_code' => 'OTHER 1A', 'capacity' => 30,
            'day_pattern' => 'MWF', 'time_start' => '08:30', 'time_end' => '09:30', 'room' => '1T'])->assertSessionHasErrors('schedule');
        $this->assertSame(1, ClassSchedule::count());
    }

    public function test_legacy_unknown_term_blocks_shared_resources_until_reconciled(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        ClassSchedule::create(['course_subject_id' => $subject->id, 'day_pattern' => 'MWF', 'time_start' => '16:00', 'time_end' => '17:00', 'room' => '1T', 'is_active' => true]);
        $this->save($draft, [$this->row($subject, $room, $instructor)], 'generate')->assertSessionHasNoErrors();
        $this->assertNotEmpty($draft->fresh()->proposals[0]['issue']);
        $this->publish($draft)->assertSessionHasErrors('timetable');
    }

    public function test_only_admin_can_manage_drafts_and_resources(): void
    {
        [$draft] = $this->setupDraft();
        foreach (['student', 'registrar', 'cashier', 'alumni', 'new_applicant'] as $role) {
            $this->actingAs($this->makeUserWithRole($role));
            $this->post(route('admin.timetables.store'), [])->assertForbidden();
            $this->post(route('admin.timetables.resources'), [])->assertForbidden();
            $this->put(route('admin.timetables.update', $draft), ['action' => 'publish', 'reviewed' => 1])->assertForbidden();
        }
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_resources_are_editable_before_publication_and_availability_requires_both_times(): void
    {
        $this->post(route('admin.timetables.resources'), ['kind' => 'room', 'name' => '1T', 'capacity' => 35,
            'facility' => 'classroom', 'active' => 1])->assertSessionHasNoErrors();
        $room = TimetableResource::firstOrFail();
        $this->post(route('admin.timetables.resources'), ['id' => $room->id, 'kind' => 'room', 'name' => '1T', 'capacity' => 45,
            'facility' => 'classroom', 'active' => 0])->assertSessionHasNoErrors();
        $this->assertSame(45, $room->fresh()->configuration['capacity']);
        $this->assertFalse($room->fresh()->configuration['active']);
        $this->post(route('admin.timetables.resources'), ['kind' => 'instructor', 'name' => 'Available teacher', 'active' => 1,
            'availability' => ['MO' => ['start' => '08:00']]])->assertSessionHasErrors('availability');
        $this->post(route('admin.timetables.resources'), ['kind' => 'instructor', 'name' => 'Available teacher', 'active' => 1,
            'availability' => ['MO' => ['start' => '08:00', 'end' => '19:00']]])->assertSessionHasNoErrors();
        $this->post(route('admin.timetables.resources'), ['kind' => 'room', 'name' => 'TBA', 'capacity' => 40,
            'facility' => 'classroom', 'active' => 1])->assertSessionHasErrors('name');
    }

    public function test_stale_draft_rechecks_inactive_resources_and_does_not_create_offerings(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        $this->save($draft, [$this->row($subject, $room, $instructor)])->assertSessionHasNoErrors();
        $configuration = $room->configuration;
        $configuration['active'] = false;
        $room->update(['configuration' => $configuration]);
        $this->publish($draft)->assertSessionHasErrors('timetable');
        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_other_terms_and_inactive_offerings_do_not_block_the_current_term(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        foreach ([['school_year' => '2025-2026', 'is_active' => true], ['school_year' => '2026-2027', 'is_active' => false]] as $attributes) {
            ClassSchedule::create($attributes + ['course_subject_id' => $subject->id, 'semester' => 1, 'section_code' => 'OLD',
                'day_pattern' => 'MWF', 'time_start' => '08:00', 'time_end' => '09:00', 'room' => '1T', 'capacity' => 40]);
        }
        $this->save($draft, [$this->row($subject, $room, $instructor)], 'generate')->assertSessionHasNoErrors();
        $this->assertSame('08:00', $draft->fresh()->proposals[0]['start']);
        $this->publish($draft)->assertSessionHasNoErrors();
        $this->assertSame(1, ClassSchedule::where('timetable_draft_id', $draft->id)->count());
    }

    public function test_late_duplicate_offering_error_rolls_back_earlier_created_rows(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        $second = CourseSubject::factory()->create(['program_id' => $draft->program_id, 'year_level' => 1, 'semester' => 1]);
        $this->save($draft, [$this->row($subject, $room, $instructor),
            $this->row($second, $room, $instructor, ['start' => '09:00', 'end' => '10:00'])])->assertSessionHasNoErrors();
        ClassSchedule::create(['course_subject_id' => $second->id, 'section_code' => $draft->group_code,
            'school_year' => $draft->school_year, 'semester' => $draft->semester, 'is_active' => false,
            'day_pattern' => 'MWF', 'time_start' => '09:00', 'time_end' => '10:00', 'room' => '1T', 'capacity' => 30]);
        $this->publish($draft)->assertSessionHasErrors('timetable');
        $this->assertSame(0, ClassSchedule::where('timetable_draft_id', $draft->id)->count());
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame(1, ClassSchedule::count());
    }
}
