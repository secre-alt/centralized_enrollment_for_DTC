{{-- ============ SUBJECTS CARD (partial) ============
     Rendered both on full page load (index.blade.php) and via AJAX
     fetch when a program is selected (see #js-select-program handler).
     Keep this file self-contained: everything needed to display and
     manage subjects for $selectedProgram lives here so the fetch
     response can replace #subjects-card-wrapper's innerHTML wholesale. --}}
{{-- Header sits outside the card — same pattern as the page-level
     "Programs & Subjects" / "Add Program" header above the All Programs
     card. --}}
<div class="d-flex justify-content-between align-items-center flex-wrap dtc-subjects-header mb-3 u-gap-10" >
    <div>
        <h4 class="mb-0 font-weight-bold dtc-subjects-title u-text" >
            Subjects
            @if($selectedProgram)
                — {{ $selectedProgram->name }} ({{ $selectedProgram->code }})
            @endif
        </h4>
        @if(!$selectedProgram)
            <p class="mb-0 u-text-secondary-sm" >
                Select a program above to manage its subjects.
            </p>
        @endif
    </div>
    @if($selectedProgram)
    <button type="button" class="dtc-btn dtc-btn-primary dtc-header-btn" aria-label="Add subject" data-toggle="modal" data-target="#addSubjectModal">
        <i data-lucide="plus"></i>
        <span class="dtc-header-btn-label">Add Subject</span>
    </button>
    @endif
</div>

<div class="card mb-0">
    @if($selectedProgram)
    <div class="card-body p-0">
        <div class="table-responsive dtc-program-table-wrap" role="region" aria-label="Subjects for selected program" tabindex="0">
<table class="table mb-0 dtc-subjects-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Subject Name</th>
                        <th>Academic Classification</th>
                        <th>Units</th>
                        <th>Year</th>
                        <th>Semester</th>
                        <th>Schedule</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjects as $subject)
                    <tr>
                        <td>
                            <span class="dtc-status-badge is-neutral">{{ $subject->subject_code }}</span>
                        </td>
                        <td  class="u-text-sm-secondary-primary">
                            {{ $subject->subject_name }}
                        </td>
                        <td>{{ ucfirst(str_replace('_', ' ', $subject->classification)) }}</td>
                        <td  class="u-text-xxs-secondary">
                            {{ rtrim(rtrim(number_format($subject->units, 1), '0'), '.') }}
                        </td>
                        <td  class="u-text-xxs-secondary">
                            {{ $subject->year_level }}{{ ['','st','nd','rd','th'][$subject->year_level] ?? 'th' }} Year
                        </td>
                        <td  class="u-text-xxs-secondary">
                            {{ $subject->semester }}{{ $subject->semester == 1 ? 'st' : 'nd' }} Sem
                        </td>
                        <td  class="u-text-xxs-secondary">
                            @forelse($subject->schedules as $sched)
                                <span class="d-block mb-1">
                                    @if($sched->section_code)
                                        <strong>{{ $sched->section_code }}</strong>
                                        · {{ $sched->school_year }} Sem {{ $sched->semester }}
                                        · {{ $sched->capacity }} seats
                                        · hosted by {{ $sched->hostingProgram->name ?? $subject->program->name }}
                                        · {{ $sched->is_active ? 'Open' : 'Closed' }}
                                    @else
                                        <span class="u-text-muted">Legacy schedule</span>
                                    @endif
                                    · {{ $sched->day_pattern }}
                                    {{ \Illuminate\Support\Carbon::parse($sched->time_start)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($sched->time_end)->format('g:i A') }}
                                    · {{ $sched->room }}
                                </span>
                            @empty
                                <span class="u-text-muted">Not set</span>
                            @endforelse
                        </td>
                        <td>
                            <div  class="u-actions-gap">
                                <button type="button"
                                        class="dtc-icon-btn js-edit-subject"
                                        title="Edit subject"
                                        data-url="{{ route('admin.programs.subjects.update', $subject) }}"
                                        data-subject-code="{{ $subject->subject_code }}"
                                        data-subject-name="{{ $subject->subject_name }}"
                                        data-units="{{ $subject->units }}"
                                        data-year-level="{{ $subject->year_level }}"
                                        data-semester="{{ $subject->semester }}"
                                        data-classification="{{ $subject->classification }}">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <button type="button"
                                        class="dtc-icon-btn js-edit-schedule"
                                        title="Add section offering"
                                        data-url="{{ route('admin.programs.subjects.schedule.store', $subject) }}"
                                        data-day-pattern=""
                                        data-time-start=""
                                        data-time-end=""
                                        data-room=""
                                        data-instructor=""
                                        data-section-code=""
                                        data-capacity=""
                                        data-active="1"
                                        data-hosting-program="{{ $selectedProgram->id }}"
                                        data-requires-irregular="">
                                    <i data-lucide="calendar-clock"></i>
                                </button>
                                @foreach($subject->schedules->whereNotNull('section_code') as $offering)
                                    <button type="button"
                                            class="dtc-icon-btn js-edit-schedule"
                                            title="Edit section {{ $offering->section_code }}"
                                            data-url="{{ route('admin.programs.subjects.schedule.store', $subject) }}"
                                            data-day-pattern="{{ $offering->day_pattern }}"
                                            data-time-start="{{ \Illuminate\Support\Carbon::parse($offering->time_start)->format('H:i') }}"
                                            data-time-end="{{ \Illuminate\Support\Carbon::parse($offering->time_end)->format('H:i') }}"
                                            data-room="{{ $offering->room }}"
                                            data-instructor="{{ $offering->instructor_name }}"
                                            data-section-code="{{ $offering->section_code }}"
                                            data-capacity="{{ $offering->capacity }}"
                                            data-active="{{ $offering->is_active ? 1 : 0 }}"
                                            data-hosting-program="{{ $offering->hosting_program_id ?: $subject->program_id }}"
                                            data-requires-irregular="{{ is_null($offering->requires_irregular) ? '' : (int) $offering->requires_irregular }}">
                                        <i data-lucide="pencil"></i>
                                    </button>
                                @endforeach
                                <button type="button"
                                        class="dtc-icon-btn js-edit-equivalency"
                                        data-subject-id="{{ $subject->id }}"
                                        data-subject-units="{{ $subject->units }}"
                                        data-equivalencies="{{ $subject->equivalencies->pluck('equivalent_subject_id')->toJson() }}"
                                        title="Record an approved subject equivalency"
                                        data-url="{{ route('registrar.subjects.equivalencies.store', $subject) }}">
                                    <i data-lucide="badge-check"></i>
                                </button>
                                <form id="del-sub-card-{{ $subject->id }}"
                                      method="POST"
                                      action="{{ route('admin.programs.subjects.destroy', $subject) }}">
                                    @csrf @method('DELETE')
                                    <button type="button"
                                            class="dtc-icon-btn danger"
                                            title="Remove subject"
                                            data-dtc-confirm
                                            data-dtc-confirm-title="Remove subject?"
                                            data-dtc-confirm-message="Are you sure you want to remove this subject from the program?"
                                            data-dtc-confirm-ok="Remove Subject"
                                            data-dtc-confirm-tone="danger"
                                            data-dtc-confirm-form="#del-sub-card-{{ $subject->id }}">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 u-text-muted" >
                            No subjects added yet for this program.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap u-gap-15" >
        <small  class="u-text-secondary">
            Showing
            <strong>{{ $subjects->firstItem() ?? 0 }}</strong>
            to
            <strong>{{ $subjects->lastItem() ?? 0 }}</strong>
            of
            <strong>{{ $subjects->total() }}</strong>
            subjects
        </small>

        @if($subjects->hasPages())
        <div>
            {{ $subjects->appends(request()->except('subjects_page'))->onEachSide(1)->links('pagination::bootstrap-4') }}
        </div>
        @endif
    </div>
    @else
    <div class="text-center py-5 u-text-muted" >
        <i data-lucide="book" style="font-size:22px; opacity:.5;"></i>
        <p class="mb-0 mt-2 u-text-sm" >No program selected yet.</p>
    </div>
    @endif
</div>

{{-- ============ ADD SUBJECT MODAL ============ --}}
@if($selectedProgram)
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST"
                  action="{{ route('admin.programs.subjects.store', $selectedProgram) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title u-text" >
                        <i data-lucide="plus-circle" class="mr-2" style="color:var(--dtc-primary);"></i>
                        Add Subject — {{ $selectedProgram->code }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="admin-programs-partials-subjects-card-subject-code-1">Subject Code</label>
                        <input id="admin-programs-partials-subjects-card-subject-code-1" type="text" name="subject_code" class="form-control"
                               placeholder="e.g. IS101" required>
                    </div>
                    <div class="form-group">
                        <label for="admin-programs-partials-subjects-card-subject-name-2">Subject Name</label>
                        <input id="admin-programs-partials-subjects-card-subject-name-2" type="text" name="subject_name" class="form-control"
                               placeholder="e.g. Introduction to Computing" required>
                    </div>
                    <div class="form-group">
                        <label for="admin-programs-partials-subjects-card-classification-6">Academic Classification</label>
                        <select id="admin-programs-partials-subjects-card-classification-6" name="classification" class="form-control" required>
                            <option value="major">Major</option>
                            <option value="minor">Minor</option>
                            <option value="general_education">General education</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="admin-programs-partials-subjects-card-units-3">Units</label>
                        <input id="admin-programs-partials-subjects-card-units-3" type="number" name="units" class="form-control"
                               step="0.5" min="0.5" max="6" placeholder="e.g. 3" value="3" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="admin-programs-partials-subjects-card-year-level-4">Year Level</label>
                                <select id="admin-programs-partials-subjects-card-year-level-4" name="year_level" class="form-control" required>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="admin-programs-partials-subjects-card-semester-5">Semester</label>
                                <select id="admin-programs-partials-subjects-card-semester-5" name="semester" class="form-control" required>
                                    <option value="1">1st Sem</option>
                                    <option value="2">2nd Sem</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer dtc-balanced-footer">
                    <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="dtc-btn dtc-btn-primary">
                        <i data-lucide="plus"></i> Add Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============ EDIT SUBJECT MODAL ============
     One shared modal, repopulated by JS from the clicked row's
     data-* attributes (no extra fetch needed — the data's already
     on the page). Form action is swapped per-click too. --}}
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" id="edit-subject-form" action="">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title u-text" >
                        <i data-lucide="pencil" class="mr-2" style="color:var(--dtc-primary);"></i>
                        Edit Subject
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-subject-code">Subject Code</label>
                        <input type="text" name="subject_code" id="edit-subject-code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-subject-name">Subject Name</label>
                        <input type="text" name="subject_name" id="edit-subject-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-subject-classification">Academic Classification</label>
                        <select name="classification" id="edit-subject-classification" class="form-control" required>
                            <option value="major">Major</option>
                            <option value="minor">Minor</option>
                            <option value="general_education">General education</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-subject-units">Units</label>
                        <input type="number" name="units" id="edit-subject-units" class="form-control"
                               step="0.5" min="0.5" max="6" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="edit-subject-year">Year Level</label>
                                <select name="year_level" id="edit-subject-year" class="form-control" required>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="edit-subject-semester">Semester</label>
                                <select name="semester" id="edit-subject-semester" class="form-control" required>
                                    <option value="1">1st Sem</option>
                                    <option value="2">2nd Sem</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer dtc-balanced-footer">
                    <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="dtc-btn dtc-btn-primary">
                        <i data-lucide="save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============ SECTION OFFERING MODAL ============ --}}
<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" id="edit-schedule-form" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title u-text" >
                        <i data-lucide="calendar-clock" class="mr-2" style="color:var(--dtc-primary);"></i>
                        Manage Section Offering
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-schedule-section">Section Code</label>
                        <input type="text" name="section_code" id="edit-schedule-section" class="form-control"
                               maxlength="30" placeholder="Required for capacity-managed offerings">
                        <small class="form-text text-muted">Leave blank only to maintain a legacy subject schedule. Section offerings use the configured current academic term.</small>
                    </div>
                    <div class="form-group">
                        <label for="edit-schedule-capacity">Seat Capacity</label>
                        <input type="number" name="capacity" id="edit-schedule-capacity" class="form-control"
                               min="1" max="1000" placeholder="Required when a section code is provided">
                    </div>
                    <div class="form-group">
                        <label for="edit-schedule-host-program">Hosting program</label>
                        <select name="hosting_program_id" id="edit-schedule-host-program" class="form-control">
                            @foreach($hostingPrograms as $hostingProgram)
                                <option value="{{ $hostingProgram->id }}">{{ $hostingProgram->code }} — {{ $hostingProgram->name }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">The student's official program and curriculum subject do not change.</small>
                    </div>
                    <div class="form-group">
                        <label for="edit-schedule-irregular">Does cross-program enrollment require Irregular classification?</label>
                        <select name="requires_irregular" id="edit-schedule-irregular" class="form-control">
                            <option value="">Select institutional treatment</option>
                            <option value="1">Yes, Irregular</option>
                            <option value="0">No, Regular may be retained</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-schedule-active">Offering status</label>
                        <select name="is_active" id="edit-schedule-active" class="form-control">
                            <option value="1">Open</option>
                            <option value="0">Closed / inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-schedule-day">Day Pattern</label>
                        <input type="text" name="day_pattern" id="edit-schedule-day" class="form-control"
                               placeholder="e.g. MWF, TTH, MONDAY" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="edit-schedule-start">Time Start</label>
                                <input type="time" name="time_start" id="edit-schedule-start" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="edit-schedule-end">Time End</label>
                                <input type="time" name="time_end" id="edit-schedule-end" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit-schedule-room">Room</label>
                        <input type="text" name="room" id="edit-schedule-room" class="form-control" placeholder="e.g. TBA">
                    </div>
                    <div class="form-group">
                        <label for="edit-schedule-instructor">Instructor</label>
                        <input type="text" name="instructor_name" id="edit-schedule-instructor" class="form-control" placeholder="e.g. Angelica Pacris">
                    </div>
                </div>
                <div class="modal-footer dtc-balanced-footer">
                    <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="dtc-btn dtc-btn-primary">
                        <i data-lucide="save"></i> Save Offering
                    </button>
                </div>

                <div class="modal fade" id="subjectEquivalencyModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <form method="POST" id="subject-equivalency-form" action="">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title">Approve Subject Equivalency</h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted">This creates an explicit, directed academic equivalency. Similar titles or units alone are not sufficient.</p>
                                    <div class="form-group">
                                        <label for="equivalent-subject">Equivalent course subject</label>
                                        <select name="equivalent_subject_id" id="equivalent-subject" class="form-control" required>
                                            <option value="">Select a matching-unit subject</option>
                                            @foreach($allSubjects as $candidate)
                                                <option value="{{ $candidate->id }}" data-units="{{ $candidate->units }}">{{ $candidate->subject_code }} — {{ $candidate->subject_name }} ({{ $candidate->program->code }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="equivalency-reason">Academic approval reason</label>
                                        <textarea name="approval_reason" id="equivalency-reason" class="form-control" rows="3" maxlength="2000" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="equivalency-irregular">Does this mapping require Irregular enrollment?</label>
                                        <select name="requires_irregular" id="equivalency-irregular" class="form-control" required>
                                            <option value="">Select the approved treatment</option>
                                            <option value="1">Yes</option>
                                            <option value="0">No</option>
                                        </select>
                                    </div>
                                    <small class="text-muted">The approved load treatment is recorded with the approving staff member. A section change for the same subject does not use this mapping.</small>
                                </div>
                                <div class="modal-footer dtc-action-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Record approval</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
// Re-bind whenever this partial is (re)inserted - see loadSubjectsCard() in index.blade.php
(function initSubjectsCardScripts() {
    document.querySelectorAll('.js-edit-subject').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit-subject-form').action = this.dataset.url;
            document.getElementById('edit-subject-code').value = this.dataset.subjectCode;
            document.getElementById('edit-subject-name').value = this.dataset.subjectName;
            document.getElementById('edit-subject-units').value = this.dataset.units;
            document.getElementById('edit-subject-year').value = this.dataset.yearLevel;
            document.getElementById('edit-subject-semester').value = this.dataset.semester;
            document.getElementById('edit-subject-classification').value = this.dataset.classification;
            $('#editSubjectModal').modal('show');
        });
    });

    document.querySelectorAll('.js-edit-schedule').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit-schedule-form').action = this.dataset.url;
            document.getElementById('edit-schedule-day').value = this.dataset.dayPattern;
            document.getElementById('edit-schedule-start').value = this.dataset.timeStart;
            document.getElementById('edit-schedule-end').value = this.dataset.timeEnd;
            document.getElementById('edit-schedule-room').value = this.dataset.room;
            document.getElementById('edit-schedule-instructor').value = this.dataset.instructor;
            document.getElementById('edit-schedule-section').value = this.dataset.sectionCode;
            document.getElementById('edit-schedule-capacity').value = this.dataset.capacity;
            document.getElementById('edit-schedule-active').value = this.dataset.active;
            document.getElementById('edit-schedule-host-program').value = this.dataset.hostingProgram;
            document.getElementById('edit-schedule-irregular').value = this.dataset.requiresIrregular;
            $('#editScheduleModal').modal('show');
        });
    });

    document.querySelectorAll('.js-edit-equivalency').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('subject-equivalency-form').action = this.dataset.url;
            var recorded = JSON.parse(this.dataset.equivalencies || '[]').map(String);
            var sourceId = this.dataset.subjectId;
            var units = this.dataset.subjectUnits;
            var picker = document.getElementById('equivalent-subject');
            picker.value = '';
            Array.from(picker.options).forEach(function (option) {
                var excluded = option.value && (option.value === sourceId || units === '' || option.dataset.units === '' || Number(option.dataset.units) !== Number(units) || recorded.includes(option.value));
                option.hidden = !!excluded;
                option.disabled = !!excluded;
            });
            $('#subjectEquivalencyModal').modal('show');
        });
    });
})();
</script>
