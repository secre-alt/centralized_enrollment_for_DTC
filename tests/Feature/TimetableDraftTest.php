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
use Illuminate\Http\UploadedFile;
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

    public function test_quick_demo_needs_only_program_and_year_and_stays_fictional(): void
    {
        $program = Program::factory()->create();
        CourseSubject::factory()->create(['program_id' => $program->id, 'year_level' => 2, 'semester' => 1]);
        $this->get(route('admin.programs.index', ['program' => $program->id]))->assertOk()
            ->assertSeeInOrder(['Try demo', 'Copy timetable', 'Import CSV', 'Create manually', 'Manage resources']);
        $this->post(route('admin.timetables.store'), ['intent' => 'demo', 'program_id' => $program->id, 'year_level' => 2,
            'is_demo' => 0, 'capacity' => 999, 'group_code' => 'LIVE GROUP'])->assertSessionHasNoErrors();
        $draft = TimetableDraft::firstOrFail();
        $this->assertTrue($draft->is_demo);
        $this->assertSame('DEMO YEAR 2', $draft->group_code);
        $this->assertSame(30, $draft->capacity);
        $this->assertSame('demo-instructor', $draft->proposals[0]['instructor_id']);
        $this->assertSame(0, TimetableResource::count());
        $this->publish($draft)->assertSessionHasErrors('timetable');
        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_quick_copy_inherits_source_details_without_retyping(): void
    {
        [$room, $instructor] = $this->resources();
        [$source, $subject] = $this->setupDraft('BSTM', ['closing_hour' => 19, 'capacity' => 25]);
        $this->save($source, [$this->row($subject, $room, $instructor)])->assertSessionHasNoErrors();
        $this->post(route('admin.timetables.store'), ['intent' => 'copy', 'source_id' => $source->id,
            'group_code' => 'BSTM 1B'])->assertSessionHasNoErrors();
        $copy = TimetableDraft::latest('id')->first();
        $this->assertSame($source->program_id, $copy->program_id);
        $this->assertSame($source->year_level, $copy->year_level);
        $this->assertSame($source->capacity, $copy->capacity);
        $this->assertFalse($copy->is_demo);
        $this->assertSame(19, $copy->configuration['closing_hour']);
        $this->assertSame($source->fresh()->proposals, $copy->proposals);
        $this->assertSame('BSTM 1A', $source->fresh()->group_code);
        $this->get(route('admin.programs.index', ['timetable' => $source->id]))->assertOk()
            ->assertViewHas('timetableDrafts', fn ($drafts) => $drafts->count() === 2);
    }

    public function test_csv_can_preview_before_any_draft_exists_then_save_with_fresh_checks(): void
    {
        [$room, $instructor] = $this->resources();
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->create(['program_id' => $program->id, 'year_level' => 1, 'semester' => 1]);
        $csv = "subject_id,instructor_id,room_id,pattern,minutes,facility,start,end\n"
            ."{$subject->id},{$instructor->id},{$room->id},MWF,60,classroom,08:00,09:00\n";
        $data = ['intent' => 'import', 'action' => 'preview', 'program_id' => $program->id, 'year_level' => 1,
            'group_code' => 'IMPORT 1A', 'capacity' => 30];
        $response = $this->post(route('admin.timetables.store'), $data + ['csv' => UploadedFile::fake()->createWithContent('schedule.csv', $csv)])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, TimetableDraft::count());
        $this->assertSame(0, ClassSchedule::count());
        $preview = session()->getOldInput();
        $this->assertSame('2026-2027', $preview['preview_school_year']);
        $this->assertNull($preview['rows'][0]['issue']);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('CSV preview - not saved')
            ->assertSee($subject->subject_code)->assertSee('Save draft')->assertDontSee('Publish saved timetable');
        $room->update(['configuration' => ['active' => false, 'capacity' => 40, 'facility' => 'classroom']]);
        $this->post(route('admin.timetables.store'), array_merge($preview, ['action' => 'create']))->assertSessionHasNoErrors();
        $draft = TimetableDraft::firstOrFail();
        $this->assertNotEmpty($draft->proposals[0]['issue']);
        $this->assertSame('draft', $draft->status);
        $this->assertSame('60', $draft->configuration['requirements'][$subject->id]['minutes']);
        $this->publish($draft)->assertSessionHasErrors('timetable');
        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_csv_import_matches_shared_resource_names_across_rows(): void
    {
        [$room, $instructor] = $this->resources();
        $program = Program::factory()->create();
        $subjects = collect([1, 2])->map(fn () => CourseSubject::factory()->create([
            'program_id' => $program->id, 'year_level' => 1, 'semester' => 1,
        ]));
        $csv = "subject_id,instructor_id,room_id,pattern,minutes,facility,start,end\n";
        foreach ($subjects as $index => $subject) {
            $start = $index === 0 ? '08:00' : '09:00';
            $end = $index === 0 ? '09:00' : '10:00';
            $csv .= "{$subject->id},TEST INSTRUCTOR,1t,MWF,60,classroom,{$start},{$end}\n";
        }

        $response = $this->post(route('admin.timetables.store'), [
            'intent' => 'import', 'action' => 'preview', 'program_id' => $program->id, 'year_level' => 1,
            'group_code' => 'IMPORT 1A', 'capacity' => 30,
            'csv' => UploadedFile::fake()->createWithContent('schedule.csv', $csv),
        ])->assertSessionHasNoErrors();

        $rows = session()->getOldInput()['rows'];
        $this->assertSame([(string) $instructor->id, (string) $instructor->id], array_column($rows, 'instructor_id'));
        $this->assertSame([(string) $room->id, (string) $room->id], array_column($rows, 'room_id'));
        $this->assertNull($rows[0]['issue']);
        $this->assertSame(0, TimetableDraft::count());
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Test instructor')->assertSee('1T');
    }

    public function test_sample_csv_export_uses_current_program_year_curriculum(): void
    {
        $program = Program::factory()->create();
        $subjects = collect([1, 2])->map(fn () => CourseSubject::factory()->create([
            'program_id' => $program->id, 'year_level' => 2, 'semester' => 1,
        ]));
        CourseSubject::factory()->create(['program_id' => $program->id, 'year_level' => 2, 'semester' => 2]);

        $csv = $this->get(route('admin.timetables.sample', ['program_id' => $program->id, 'year_level' => 2]))
            ->assertOk()->assertDownload('timetable-sample.csv')->streamedContent();
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));

        $this->assertSame(['subject_id', 'instructor_id', 'room_id', 'pattern', 'minutes', 'facility', 'start', 'end'], $rows[0]);
        $this->assertSame($subjects->pluck('id')->map(fn ($id) => (string) $id)->all(), array_column(array_slice($rows, 1), 0));
        $this->assertSame(8, count($rows[1]));
        $this->assertSame(array_fill(0, 7, ''), array_slice($rows[1], 1));
    }

    public function test_csv_import_flags_unknown_resource_names_instead_of_guessing(): void
    {
        [$room] = $this->resources();
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->create(['program_id' => $program->id, 'year_level' => 1, 'semester' => 1]);
        $csv = "subject_id,instructor_id,room_id,pattern,minutes,facility,start,end\n"
            ."{$subject->id},Unknown Instructor,{$room->id},MWF,60,classroom,08:00,09:00\n";

        $response = $this->post(route('admin.timetables.store'), [
            'intent' => 'import', 'action' => 'preview', 'program_id' => $program->id, 'year_level' => 1,
            'group_code' => 'IMPORT 1A', 'capacity' => 30,
            'csv' => UploadedFile::fake()->createWithContent('schedule.csv', $csv),
        ])->assertSessionHasNoErrors();

        $row = session()->getOldInput()['rows'][0];
        $this->assertNull($row['instructor_id']);
        $this->assertStringContainsString('No instructor matches', $row['issue']);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('No instructor matches')->assertSee('Unknown Instructor');
        $this->assertSame(0, TimetableDraft::count());
    }

    public function test_new_import_rejects_stale_terms_and_invalid_subjects_without_leaving_empty_drafts(): void
    {
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->create(['program_id' => $program->id, 'year_level' => 1, 'semester' => 1]);
        $row = ['subject_id' => $subject->id, 'instructor_id' => 'demo-instructor', 'room_id' => 'demo-room',
            'pattern' => 'MWF', 'minutes' => 60, 'facility' => 'classroom', 'start' => '08:00', 'end' => '09:00'];
        $data = ['intent' => 'import', 'action' => 'create', 'program_id' => $program->id, 'year_level' => 1,
            'group_code' => 'IMPORT DEMO', 'capacity' => 30, 'is_demo' => 1, 'rows' => [$row],
            'preview_school_year' => '2025-2026', 'preview_semester' => 1];
        $this->post(route('admin.timetables.store'), $data)->assertSessionHasErrors('timetable');
        $data['preview_school_year'] = '2026-2027';
        $data['rows'][0]['subject_id'] = CourseSubject::factory()->create()->id;
        $this->post(route('admin.timetables.store'), $data)->assertSessionHasErrors('timetable');
        $this->assertSame(0, TimetableDraft::count());
        $data['rows'] = [$row];
        $this->post(route('admin.timetables.store'), $data)->assertSessionHasNoErrors();
        $this->assertTrue(TimetableDraft::firstOrFail()->is_demo);
        $this->assertSame(0, TimetableResource::count());
        $this->assertSame(0, ClassSchedule::count());
        $data['action'] = 'preview';
        $this->post(route('admin.timetables.store'), $data + ['csv' => UploadedFile::fake()->createWithContent('bad.csv', "wrong,header\n")])
            ->assertSessionHasErrors('csv');
        $this->assertSame(1, TimetableDraft::count());
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
            $this->put(route('admin.timetables.update', $draft), ['action' => 'clear', 'confirmed' => 1])->assertForbidden();
            $this->put(route('admin.timetables.update', $draft), ['action' => 'import'])->assertForbidden();
            $this->get(route('admin.timetables.export', $draft))->assertForbidden();
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

    public function test_clear_requires_confirmation_and_preserves_group_and_published_offerings(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        $this->save($draft, [$this->row($subject, $room, $instructor)])->assertSessionHasNoErrors();
        $this->put(route('admin.timetables.update', $draft), ['action' => 'clear'])->assertSessionHasErrors('confirmed');
        $this->assertNotEmpty($draft->fresh()->proposals);
        $this->put(route('admin.timetables.update', $draft), ['action' => 'clear', 'confirmed' => 1])->assertSessionHasNoErrors();
        $draft->refresh();
        $this->assertNull($draft->proposals);
        $this->assertSame(['minutes' => 60, 'facility' => 'classroom'], $draft->configuration['requirements'][$subject->id]);
        $this->assertSame('BSTM 1A', $draft->group_code);
        $this->assertSame(30, $draft->capacity);
        $this->publish($draft)->assertSessionHasErrors('timetable');
        $this->save($draft, [$this->row($subject, $room, $instructor)])->assertSessionHasNoErrors();
        $this->publish($draft)->assertSessionHasNoErrors();
        $this->put(route('admin.timetables.update', $draft), ['action' => 'clear', 'confirmed' => 1])->assertSessionHasErrors('timetable');
        $this->assertSame(1, ClassSchedule::count());
    }

    public function test_copy_uses_current_term_rechecks_conflicts_and_keeps_source_intact(): void
    {
        [$room, $instructor] = $this->resources();
        [$source, $subject] = $this->setupDraft();
        $this->save($source, [$this->row($subject, $room, $instructor)])->assertSessionHasNoErrors();
        $this->publish($source)->assertSessionHasNoErrors();
        $original = $source->fresh()->proposals;
        $data = ['source_id' => $source->id, 'program_id' => $source->program_id, 'year_level' => 1,
            'group_code' => 'BSTM 1B', 'capacity' => 30, 'is_demo' => 0, 'closing_hour' => 17];
        $this->post(route('admin.timetables.store'), $data)->assertSessionHasNoErrors();
        $copy = TimetableDraft::latest('id')->first();
        $this->assertSame('draft', $copy->status);
        $this->assertNotEmpty($copy->proposals[0]['issue']);
        $this->assertSame($original, $source->fresh()->proposals);
        Setting::set('current_school_year', '2027-2028');
        $added = CourseSubject::factory()->create(['program_id' => $source->program_id, 'year_level' => 1, 'semester' => 1]);
        $this->post(route('admin.timetables.store'), $data)->assertSessionHasNoErrors();
        $copy = TimetableDraft::latest('id')->first();
        $this->assertSame('2027-2028', $copy->school_year);
        $this->assertNull($copy->proposals[0]['issue']);
        $this->assertSame($added->id, $copy->proposals[1]['subject_id']);
        $this->assertNotEmpty($copy->proposals[1]['issue']);
        $this->post(route('admin.timetables.store'), array_merge($data, ['is_demo' => 1]))->assertSessionHasErrors('source_id');
        $this->assertSame(3, TimetableDraft::count());
        $this->assertSame(1, ClassSchedule::count());
    }

    public function test_csv_round_trip_previews_without_saving_then_uses_normal_review(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        $this->save($draft, [$this->row($subject, $room, $instructor)])->assertSessionHasNoErrors();
        $export = $this->get(route('admin.timetables.export', $draft))->assertOk();
        $this->assertStringContainsString('no-store', $export->headers->get('Cache-Control'));
        $csv = $export->streamedContent();
        $this->assertStringContainsString('subject_id,instructor_id,room_id,pattern,minutes,facility,start,end', $csv);
        $original = $draft->fresh()->proposals;
        $csv = str_replace('08:00,09:00', '09:00,10:00', $csv);
        $response = $this->put(route('admin.timetables.update', $draft), ['action' => 'import',
            'csv' => UploadedFile::fake()->createWithContent('schedule.csv', $csv)])->assertSessionHasNoErrors();
        $rows = session()->getOldInput('rows');
        $this->assertSame('09:00', $rows[0]['start']);
        $this->assertNull($rows[0]['issue']);
        $this->assertSame($original, $draft->fresh()->proposals);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('data-restored-edits', false);
        $this->put(route('admin.timetables.update', $draft), ['action' => 'import',
            'csv' => UploadedFile::fake()->createWithContent('schedule.csv', str_replace('09:00,10:00', '11:30,12:30', $csv))])->assertSessionHasNoErrors();
        $this->assertNotEmpty(session()->getOldInput('rows')[0]['issue']);
        $this->assertSame($original, $draft->fresh()->proposals);
        $this->save($draft, $rows)->assertSessionHasNoErrors();
        $this->assertSame('09:00', $draft->fresh()->proposals[0]['start']);
        $this->publish($draft)->assertSessionHasNoErrors();
        $this->put(route('admin.timetables.update', $draft), ['action' => 'import',
            'csv' => UploadedFile::fake()->createWithContent('schedule.csv', $csv)])->assertSessionHasErrors('timetable');
    }

    public function test_csv_rejects_invalid_headers_values_and_incomplete_or_duplicate_subjects(): void
    {
        [$draft, $subject] = $this->setupDraft();
        $header = "subject_id,instructor_id,room_id,pattern,minutes,facility,start,end\n";
        foreach (["wrong,header\n", $header, $header."{$subject->id},,,MWF,60,classroom,25:00,09:00\n",
            $header."999999,,,MWF,60,classroom,08:00,09:00\n",
            $header.str_repeat("{$subject->id},,,MWF,60,classroom,08:00,09:00\n", 2),
            $header."{$subject->id},too,few\n"] as $csv) {
            $this->put(route('admin.timetables.update', $draft), ['action' => 'import',
                'csv' => UploadedFile::fake()->createWithContent('schedule.csv', $csv)])->assertSessionHasErrors();
            $this->assertNull($draft->fresh()->proposals);
        }
    }

    public function test_csv_preview_keeps_assignments_with_their_subject_when_rows_are_reordered(): void
    {
        [$room, $instructor] = $this->resources();
        [$draft, $subject] = $this->setupDraft();
        $second = CourseSubject::factory()->create(['program_id' => $draft->program_id, 'year_level' => 1, 'semester' => 1]);
        $csv = "subject_id,instructor_id,room_id,pattern,minutes,facility,start,end\n"
            ."{$second->id},{$instructor->id},{$room->id},MWF,60,classroom,09:00,10:00\n"
            ."{$subject->id},{$instructor->id},{$room->id},MWF,60,classroom,08:00,09:00\n";
        $this->put(route('admin.timetables.update', $draft), ['action' => 'import',
            'csv' => UploadedFile::fake()->createWithContent('schedule.csv', $csv)])->assertSessionHasNoErrors();
        $rows = session()->getOldInput('rows');
        $this->assertSame([$subject->id, $second->id], array_map('intval', array_column($rows, 'subject_id')));
        $this->assertSame(['08:00', '09:00'], array_column($rows, 'start'));
        $this->assertNull($draft->fresh()->proposals);
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
