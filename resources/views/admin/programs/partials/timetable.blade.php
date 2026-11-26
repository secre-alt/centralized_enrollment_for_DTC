<section class="card tt-workspace" id="timetable-workspace" aria-labelledby="tt-title">
    <div class="card-header"><h5 id="tt-title" class="mb-1">Draft timetables</h5><p class="tt-help mb-0">Prepare group schedules using shared rooms and instructors. Drafts do not reserve seats or create enrollment offerings.</p></div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <details class="tt-section">
            <summary>Shared rooms and instructors <span class="tt-help">{{ $timetableResources->count() }} configured</span></summary>
            <p class="tt-help">Rooms are shared across programs. Use one consistent room name (for example 1T) and one record per instructor. No rooms are created automatically. Resources used by published timetables are protected from changes.</p>
            @foreach(['room', 'instructor'] as $resourceKind)
                <h6 class="mt-4">{{ $resourceKind === 'room' ? 'Rooms' : 'Instructors' }}</h6>
                @foreach($timetableResources->where('kind', $resourceKind) as $resource)
                    <details class="tt-resource"><summary>{{ $resource->name }} <span class="tt-help">{{ ($resource->configuration['active'] ?? false) ? 'Active' : 'Inactive' }}</span></summary>
                        @include('admin.programs.partials.timetable-resource')
                    </details>
                @endforeach
                <details class="tt-resource"><summary>Add {{ $resourceKind }}</summary>
                    @include('admin.programs.partials.timetable-resource', ['resource' => null])
                </details>
            @endforeach
        </details>

        <details class="tt-section" @if(!$timetableDraft) open @endif>
            <summary>Create a draft</summary>
            <p class="tt-help">The configured academic term is captured when the draft is created. Demonstrations use a fictional room and instructor with a 60-minute MWF preset for illustration; they cannot be published.</p>
            <form method="POST" action="{{ route('admin.timetables.store') }}">
                @csrf
                <input type="hidden" name="_timetable_form" value="1">
                <div class="tt-grid">
                    <label for="tt-program">Program<select id="tt-program" name="program_id" class="form-control" required>
                        @foreach($hostingPrograms as $choice)<option value="{{ $choice->id }}" @selected((string)old('program_id', $selectedProgram?->id) === (string)$choice->id)>{{ $choice->name }}</option>@endforeach
                    </select></label>
                    <label for="tt-year">Year level<select id="tt-year" name="year_level" class="form-control">@foreach(range(1,4) as $year)<option value="{{ $year }}" @selected((int)old('year_level',1) === $year)>Year {{ $year }}</option>@endforeach</select></label>
                    <label for="tt-group">Student group<input id="tt-group" name="group_code" class="form-control" maxlength="30" placeholder="e.g. BSTM 1A" value="{{ old('group_code') }}" required></label>
                    <label for="tt-seats">Group size<input id="tt-seats" name="capacity" type="number" class="form-control" min="1" max="1000" value="{{ old('capacity') }}" required></label>
                    <label for="tt-mode">Mode<select id="tt-mode" name="is_demo" class="form-control"><option value="1" @selected((string)old('is_demo','1') === '1')>Demonstration — cannot publish</option><option value="0" @selected((string)old('is_demo') === '0')>Normal draft — configured resources</option></select></label>
                    <label for="tt-close">Latest class end<select id="tt-close" name="closing_hour" class="form-control">@foreach([17=>'5 PM',18=>'6 PM',19=>'7 PM'] as $hour=>$label)<option value="{{ $hour }}" @selected((int)old('closing_hour',17) === $hour)>{{ $label }}</option>@endforeach</select></label>
                </div>
                <p class="tt-help">Extended hours also require instructor availability. NSTP, ROTC and laboratory contact hours must be entered explicitly; units do not determine meeting duration.</p>
                @if($timetableDraft)
                    <label class="tt-confirm"><input type="checkbox" name="source_id" value="{{ $timetableDraft->id }}" @checked(old('source_id'))> Copy assignments from {{ $timetableDraft->group_code }}</label>
                    <p class="tt-help">Use the same program, year level and mode as the source. The new draft uses the configured current term and the group details above. Only subjects in the current curriculum are copied; conflicts need review.</p>
                @endif
                <div class="tt-actions"><button type="submit" class="dtc-btn dtc-btn-primary">Create draft</button></div>
            </form>
        </details>

        <details class="tt-section" @if(!$timetableDraft) open @endif>
            <summary>Saved timetables</summary>
            <div class="tt-list">
                @forelse($timetableDrafts as $saved)
                    <a href="{{ route('admin.programs.index', ['program'=>$saved->program_id, 'timetable'=>$saved->id]) }}#timetable-workspace" class="tt-list-row"><span>{{ $saved->program->code }} · {{ $saved->group_code }}<small>{{ $saved->school_year }} · Semester {{ $saved->semester }}</small></span><span>{{ $saved->is_demo ? 'Demonstration' : ucfirst($saved->status) }}</span></a>
                @empty
                    <p class="tt-help">No draft timetables yet</p>
                @endforelse
            </div>
            {{ $timetableDrafts->links('pagination::bootstrap-4') }}
        </details>

        @if($timetableDraft)
            @php
                $draft = $timetableDraft;
                $proposalRows = collect($draft->proposals ?? [])->keyBy('subject_id');
                $resourceChoices = collect($timetableChoices);
                $unresolved = $timetableSubjects->filter(fn($subject) => !$proposalRows->has($subject->id) || !empty($proposalRows->get($subject->id)['issue']))->count();
                $canPublish = $timetableSubjects->isNotEmpty() && $unresolved === 0;
            @endphp
            <div class="tt-draft-heading"><div><h5>{{ $draft->group_code }} <span class="tt-help">{{ $draft->is_demo ? 'Demonstration only' : ucfirst($draft->status) }}</span></h5><p class="tt-help mb-0">{{ $draft->program->name }} · Year {{ $draft->year_level }} · {{ $draft->school_year }} · Semester {{ $draft->semester }} · {{ $draft->capacity }} students</p></div></div>
            @if($draft->is_demo)<p class="tt-notice">Demonstration data. The room, instructor, availability and meeting requirements below are fictional presets and cannot become live offerings.</p>@endif
            <details class="tt-section">
                <summary>CSV and draft tools</summary>
                <p class="tt-help">Export the saved assignments as a CSV template for Excel. Keep the column headings and subject IDs unchanged. Use the instructor and room IDs shown in the editor. Days use MWF, TTH or a single-day code; times use HH:MM (24-hour format).</p>
                <div class="tt-actions"><a class="dtc-btn dtc-btn-secondary" href="{{ route('admin.timetables.export', $draft) }}">Export CSV</a></div>
                @if($draft->status === 'draft')
                    <form method="POST" action="{{ route('admin.timetables.update', $draft) }}" enctype="multipart/form-data" class="mt-3">
                        @csrf @method('PUT')
                        <input type="hidden" name="action" value="import">
                        <label for="tt-csv">Import CSV preview<input id="tt-csv" class="form-control" type="file" name="csv" accept=".csv,text/csv" required></label>
                        <p class="tt-help">Preview replaces unsaved editor values. Nothing is saved until you choose Save review. Maximum file size: 256 KB; include every subject exactly once.</p>
                        <div class="tt-actions"><button class="dtc-btn dtc-btn-secondary" type="submit">Preview CSV</button></div>
                    </form>
                    <form method="POST" action="{{ route('admin.timetables.update', $draft) }}" class="mt-4">
                        @csrf @method('PUT')
                        <input type="hidden" name="action" value="clear">
                        <p class="tt-help">Clear days, times, rooms and instructors. Keep the subjects, meeting durations, facility requirements and group details.</p>
                        <label class="tt-confirm"><input type="checkbox" name="confirmed" value="1" required> Clear all schedule assignments for this draft</label>
                        <div class="tt-actions"><button class="dtc-btn dtc-btn-secondary" type="submit">Clear draft schedule</button></div>
                    </form>
                @endif
            </details>
            @if($draft->status === 'published')
                <p class="tt-notice">Published {{ $draft->published_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}. Eligible new enrollments can be assigned to this group automatically when the complete selected load fits. Existing enrollments waiting for sections still need Registrar assignment. Published timetables are read only.</p>
            @else
                <p class="tt-help">Enter requirements, then generate suggestions. Edit individual room/time assignments and save the review. Generation uses a first-fit search; an unresolved item may need a manual adjustment rather than another room. MWF means Monday, Wednesday and Friday; TTH means Tuesday and Thursday.</p>
                <p class="tt-help">Each offering currently supports one time block repeated on its meeting days. A split morning/afternoon class needs an approved meeting model before publication; do not enter it as an uninterrupted block across lunch.</p>
            @endif
            <section class="tt-saved-summary" aria-labelledby="tt-saved-title">
                <h6 id="tt-saved-title">Saved schedule</h6>
                <p class="tt-help">{{ $timetableSubjects->count() - $unresolved }} of {{ $timetableSubjects->count() }} subjects placed · {{ $unresolved }} need attention. This summary shows saved assignments.</p>
                <div class="tt-table-wrap" role="region" aria-label="Saved subject assignments" tabindex="0">
                    <table class="tt-table"><thead><tr><th>Subject</th><th>Days / time</th><th>Room</th><th>Instructor</th><th>Review</th></tr></thead><tbody>
                    @foreach($timetableSubjects as $subject)
                        @php $savedRow = $proposalRows->get($subject->id, []); @endphp
                        <tr><th scope="row">{{ $subject->subject_code }}</th><td>{{ $savedRow['pattern'] ?? 'Not set' }}<br>{{ $savedRow['start'] ?? '—' }}–{{ $savedRow['end'] ?? '—' }}</td><td>{{ $resourceChoices->firstWhere('id', $savedRow['room_id'] ?? null)['name'] ?? 'Unassigned' }}</td><td>{{ $resourceChoices->firstWhere('id', $savedRow['instructor_id'] ?? null)['name'] ?? 'Unassigned' }}</td><td>{{ empty($savedRow) ? 'Needs assignment' : ($savedRow['issue'] ?? 'Placed') }}</td></tr>
                    @endforeach
                    </tbody></table>
                </div>
            </section>
            <form id="tt-edit-form" method="POST" action="{{ route('admin.timetables.update', $draft) }}">
                @csrf @method('PUT')
                @if(old('rows') !== null)<span data-restored-edits hidden></span>@endif
                <input type="hidden" name="_timetable_form" value="1">
                <fieldset @disabled($draft->status === 'published')>
                    @foreach($timetableSubjects as $index => $subject)
                        @php $row = old('rows.'.$index, $proposalRows->get($subject->id, $draft->configuration['requirements'][$subject->id] ?? [])); @endphp
                        <div class="tt-subject">
                            <div class="tt-subject-title"><strong>{{ $subject->subject_code }}</strong><span>{{ $subject->subject_name }} · {{ $subject->units }} units · CSV subject ID: {{ $subject->id }}</span></div>
                            <input type="hidden" name="rows[{{ $index }}][subject_id]" value="{{ $subject->id }}">
                            <div class="tt-grid">
                                <label for="tt-instructor-{{ $index }}">Instructor<select id="tt-instructor-{{ $index }}" name="rows[{{ $index }}][instructor_id]" class="form-control"><option value="">Choose instructor</option>@foreach($resourceChoices->where('kind','instructor') as $choice)<option value="{{ $choice['id'] }}" @selected((string)($row['instructor_id'] ?? '') === (string)$choice['id'])>{{ $choice['name'] }} (ID {{ $choice['id'] }})</option>@endforeach</select></label>
                                <label for="tt-pattern-{{ $index }}">Meeting days<select id="tt-pattern-{{ $index }}" name="rows[{{ $index }}][pattern]" class="form-control"><option value="">Choose pattern</option>@foreach(\App\Services\TimetableService::PATTERNS as $pattern)<option value="{{ $pattern }}" @selected(($row['pattern'] ?? '') === $pattern)>{{ $pattern }}</option>@endforeach</select></label>
                                <label for="tt-minutes-{{ $index }}">Minutes per meeting<input id="tt-minutes-{{ $index }}" type="number" min="30" max="480" step="30" class="form-control" name="rows[{{ $index }}][minutes]" value="{{ $row['minutes'] ?? '' }}"></label>
                                <label for="tt-required-facility-{{ $index }}">Required facility<select id="tt-required-facility-{{ $index }}" name="rows[{{ $index }}][facility]" class="form-control"><option value="">Choose facility</option>@foreach(\App\Services\TimetableService::FACILITIES as $facility)<option value="{{ $facility }}" @selected(($row['facility'] ?? '') === $facility)>{{ ucwords(str_replace('_',' ',$facility)) }}</option>@endforeach</select></label>
                                <label for="tt-room-{{ $index }}">Room<select id="tt-room-{{ $index }}" name="rows[{{ $index }}][room_id]" class="form-control"><option value="">Unassigned</option>@foreach($resourceChoices->where('kind','room') as $choice)<option value="{{ $choice['id'] }}" @selected((string)($row['room_id'] ?? '') === (string)$choice['id'])>{{ $choice['name'] }} (ID {{ $choice['id'] }}) · {{ $choice['configuration']['capacity'] }} seats</option>@endforeach</select></label>
                                <label for="tt-time-start-{{ $index }}">Start<input id="tt-time-start-{{ $index }}" type="time" class="form-control" name="rows[{{ $index }}][start]" value="{{ $row['start'] ?? '' }}"></label>
                                <label for="tt-time-end-{{ $index }}">End<input id="tt-time-end-{{ $index }}" type="time" class="form-control" name="rows[{{ $index }}][end]" value="{{ $row['end'] ?? '' }}"></label>
                            </div>
                            @if(!empty($row['issue']))<p class="tt-issue" role="status">{{ $row['issue'] }}</p>@endif
                        </div>
                    @endforeach
                    @if($draft->status === 'draft')
                        <div class="tt-actions"><button type="submit" name="action" value="review" class="dtc-btn dtc-btn-secondary">Save review</button><button type="submit" name="action" value="generate" class="dtc-btn dtc-btn-primary">Generate suggestions</button></div>
                    @endif
                </fieldset>
            </form>
            @if(!$draft->is_demo && $draft->status === 'draft')
                <form method="POST" action="{{ route('admin.timetables.update', $draft) }}" class="tt-publish" data-ready="{{ $canPublish ? '1' : '0' }}">
                    @csrf @method('PUT')<input type="hidden" name="action" value="publish">
                    <input type="hidden" name="_timetable_form" value="1">
                    <h6>Publish reviewed schedule</h6>
                    <p class="tt-help">{{ $canPublish ? 'All subjects are placed. Review the saved schedule before publishing.' : 'Generate or save assignments and resolve every subject before publishing.' }} Publication rechecks current room, instructor and group conflicts.</p>
                    <p id="tt-unsaved" class="tt-notice" role="status" hidden>Unsaved changes. Save review or generate suggestions before publishing.</p>
                    <label class="tt-confirm"><input type="checkbox" name="reviewed" value="1" required @disabled(!$canPublish)> I reviewed the saved timetable, teaching requirements and resource details</label>
                    <div class="tt-actions"><button type="submit" class="dtc-btn dtc-btn-primary" @disabled(!$canPublish)>Publish saved timetable</button></div>
                </form>
            @endif
        @endif
    </div>
</section>
@push('css')<link rel="stylesheet" href="{{ asset('css/timetables.css') }}">@endpush
@push('js')<script src="{{ asset('js/timetable-review.js') }}" defer></script>@endpush
