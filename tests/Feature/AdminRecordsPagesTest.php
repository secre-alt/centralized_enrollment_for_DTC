<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BackupLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class AdminRecordsPagesTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_users_directory_forms_have_accessible_labels_and_autofill_hints(): void
    {
        $this->actingAs($this->makeUserWithRole('admin'));
        $response = $this->get(route('admin.users.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//input[not(@type="hidden")]|//select|//textarea') as $field) {
            $id = $field->getAttribute('id');
            $explicit = $id !== '' && $xpath->query('//label[@for="'.$id.'"]')->length > 0;
            $implicit = $xpath->query('ancestor::label', $field)->length > 0;
            $this->assertTrue($explicit || $implicit || $field->hasAttribute('aria-label') || $field->hasAttribute('aria-labelledby'), 'Unnamed field: '.$field->getAttribute('name'));
        }
        foreach (['create-user-name' => 'name', 'edit-name' => 'name', 'create-user-email' => 'email', 'edit-email' => 'email', 'create-user-password' => 'new-password', 'reset-new-password' => 'new-password', 'reset-confirm-password' => 'new-password'] as $id => $hint) {
            $this->assertSame($hint, $document->getElementById($id)->getAttribute('autocomplete'));
        }
        $this->assertSame(1, $xpath->query('//*[@id="usersFilterPanel"]//*[@data-table-rows-select]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-table-rows-options]')->length);
        $response->assertSee('aria-label="Add new user"', false);
    }

    public function test_student_program_filter_preserves_search_status_and_pagination(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $program = \App\Models\Program::factory()->create();
        $otherProgram = \App\Models\Program::factory()->create();
        $expected = [];
        foreach (['student', 'student', 'student', 'student', 'student', 'student', 'alumni'] as $index => $role) {
            $user = $this->makeUserWithRole($role, ['name' => 'Matching Account '.$index, 'status' => 'active']);
            \App\Models\StudentProfile::create(['user_id' => $user->id, 'program_id' => $program->id, 'student_number' => 'TEST-PROGRAM-'.$index]);
            if ($role === 'student') $expected[] = $user->id;
        }
        $foreign = $this->makeUserWithRole('student', ['name' => 'Matching Other', 'status' => 'active']);
        \App\Models\StudentProfile::create(['user_id' => $foreign->id, 'program_id' => $otherProgram->id, 'student_number' => 'TEST-PROGRAM-OTHER']);
        $this->makeUserWithRole('student', ['name' => 'Matching No Program']);
        $params = ['program_id' => $program->id, 'status' => 'active', 'search' => 'Matching', 'per_page' => 5];
        $response = $this->actingAs($admin)->get(route('admin.users.index', $params))->assertOk();
        $this->assertSame(6, $response->viewData('users')->total());
        $this->assertContains($response->viewData('users')->first()->id, $expected);
        $response->assertSee('program_id='.$program->id)->assertSee('search=Matching')->assertSee('status=active');
        $this->get(route('admin.users.index', array_merge($params, ['page' => 2])))->assertOk()
            ->assertViewHas('users', fn ($users) => $users->count() === 1 && in_array($users->first()->id, $expected, true));
        $this->getJson(route('admin.users.index', ['program_id' => 999999]))->assertUnprocessable();
        $this->getJson(route('admin.users.index', ['program_id' => 'invalid']))->assertUnprocessable();
        $this->get(route('admin.users.index'))->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 10);
    }

    public function test_audit_page_keeps_filtering_pagination_and_escaped_record_details(): void
    {
        $admin = $this->makeUserWithRole('admin');
        AuditLog::create(['user_id' => $admin->id, 'action' => 'document.checked', 'description' => '<script>unexpected()</script>']);
        $this->actingAs($admin)->get(route('admin.settings.audit', ['action' => 'document.checked', 'per_page' => 5]))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->perPage() === 5 && $logs->total() === 1)
            ->assertSee('data-label="Description"', false)->assertSee('id="audit-per-page"', false)
            ->assertSee('&lt;script&gt;unexpected()&lt;/script&gt;', false)->assertDontSee('<script>unexpected()</script>', false)
            ->assertDontSee('dtc-table-rows-options', false);
    }

    public function test_backup_history_has_distinct_confirmation_targets_and_named_actions(): void
    {
        $admin = $this->makeUserWithRole('admin');
        foreach (['completed', 'failed'] as $status) {
            $backups[] = BackupLog::create(['filename' => 'backup_'.$status.'.sql', 'size' => 1024, 'status' => $status, 'created_by' => $admin->id]);
        }
        $response = $this->actingAs($admin)->get(route('admin.settings.backup'))->assertOk();
        foreach ($backups as $backup) {
            $response->assertSee('id="del-backup-'.$backup->id.'"', false)
                ->assertSee('data-dtc-confirm-form="#del-backup-'.$backup->id.'"', false)
                ->assertSee('aria-label="Delete '.$backup->filename.'"', false);
        }
        $response->assertSee(route('admin.settings.backup.download', $backups[0]), false)
            ->assertDontSee(route('admin.settings.backup.download', $backups[1]), false)
            ->assertSee('Uploaded SQL files are not restored')->assertDontSee('id="sql-file"', false);
    }
}
