@php
    $entryIntent = old('intent');
    $copyChoices = collect($timetableDrafts->items())->when($timetableDraft, fn ($drafts) => $drafts->prepend($timetableDraft))->unique('id');
@endphp
<p class="tt-help">Current term: <strong>{{ \App\Models\Setting::get('current_school_year', 'Not configured') }} / {{ \App\Models\Setting::get('current_semester', 'Not configured') }}</strong>. New drafts use this term automatically.</p>
<h6 class="tt-step-title">1. Start a draft</h6>
<div class="tt-start-options">
    <details class="tt-start-option" @if($entryIntent === 'demo') open @endif>
        <summary>Try demo <span class="tt-help">Fictional sample schedule</span></summary>
        <p class="tt-help">Choose a program and year. We fill a fictional group of 30 students, room, instructor and 60-minute MWF requirements. Demonstrations cannot be published.</p>
        <form method="POST" action="{{ route('admin.timetables.store') }}">
            @csrf
            <input type="hidden" name="intent" value="demo">
            <div class="tt-grid">
                <label for="tt-demo-program">Program<select id="tt-demo-program" name="program_id" data-timetable-program class="form-control" required>
                    @foreach($hostingPrograms as $choice)<option value="{{ $choice->id }}" @selected((string)($entryIntent === 'demo' ? old('program_id') : $selectedProgram?->id) === (string)$choice->id)>{{ $choice->name }}</option>@endforeach
                </select></label>
                <label for="tt-demo-year">Year level<select id="tt-demo-year" name="year_level" class="form-control">@foreach(range(1,4) as $year)<option value="{{ $year }}" @selected((int)($entryIntent === 'demo' ? old('year_level', 1) : 1) === $year)>Year {{ $year }}</option>@endforeach</select></label>
            </div>
            <div class="tt-actions"><button type="submit" class="dtc-btn dtc-btn-primary">Try demo</button></div>
        </form>
    </details>
    <details class="tt-start-option" @if($entryIntent === 'copy') open @endif>
        <summary>Copy timetable <span class="tt-help">Reuse teaching requirements</span></summary>
        @if($copyChoices->isEmpty())
            <p class="tt-help">Create a demo or save a draft first. You can copy it here later.</p>
        @else
            <p class="tt-help">Program, year, mode and latest class end come from the source. Choose a source for the configured semester. All assignments are checked again.</p>
            <form method="POST" action="{{ route('admin.timetables.store') }}">
                @csrf
                <input type="hidden" name="intent" value="copy">
                <div class="tt-grid">
                    <label for="tt-copy-source">Source timetable<select id="tt-copy-source" name="source_id" class="form-control" required>
                        @foreach($copyChoices as $source)<option value="{{ $source->id }}" @selected((string)old('source_id', $timetableDraft?->id) === (string)$source->id)>{{ $source->group_code }} / {{ $source->program->code }} / Year {{ $source->year_level }} / {{ $source->school_year }} Sem {{ $source->semester }}{{ $source->is_demo ? ' / Demo' : '' }}</option>@endforeach
                    </select></label>
                    <label for="tt-copy-group">New student group<input id="tt-copy-group" name="group_code" class="form-control" maxlength="30" placeholder="e.g. BSTM 1B" value="{{ $entryIntent === 'copy' ? old('group_code') : '' }}" required></label>
                    <label for="tt-copy-seats">Group size (optional)<input id="tt-copy-seats" name="capacity" type="number" class="form-control" min="1" max="1000" placeholder="Use source group size" value="{{ $entryIntent === 'copy' ? old('capacity') : '' }}"></label>
                </div>
                <div class="tt-actions"><button type="submit" class="dtc-btn dtc-btn-primary">Copy to new draft</button></div>
            </form>
        @endif
    </details>
    <details class="tt-start-option" @if($entryIntent === 'import') open @endif>
        <summary>Import CSV <span class="tt-help">Preview before saving</span></summary>
        <p class="tt-help">Upload a schedule with every curriculum subject once. Keep the exported column headings and subject IDs. Instructor and room columns can use resource IDs or their configured names. Maximum 256 KB / 100 subjects.</p>
        <form method="POST" action="{{ route('admin.timetables.store') }}" enctype="multipart/form-data" id="tt-import-form">
            @csrf
            <input type="hidden" name="intent" value="import">
            <input type="hidden" name="action" value="preview">
            <div class="tt-grid">
                <label for="tt-import-program">Program<select id="tt-import-program" name="program_id" data-timetable-program class="form-control" required>
                    @foreach($hostingPrograms as $choice)<option value="{{ $choice->id }}" @selected((string)($entryIntent === 'import' ? old('program_id') : $selectedProgram?->id) === (string)$choice->id)>{{ $choice->name }}</option>@endforeach
                </select></label>
                <label for="tt-import-year">Year level<select id="tt-import-year" name="year_level" class="form-control">@foreach(range(1,4) as $year)<option value="{{ $year }}" @selected((int)($entryIntent === 'import' ? old('year_level', 1) : 1) === $year)>Year {{ $year }}</option>@endforeach</select></label>
                <label for="tt-import-group">Student group<input id="tt-import-group" name="group_code" class="form-control" maxlength="30" placeholder="e.g. BSTM 1A" value="{{ $entryIntent === 'import' ? old('group_code') : '' }}" required></label>
                <label for="tt-import-seats">Group size<input id="tt-import-seats" name="capacity" type="number" class="form-control" min="1" max="1000" value="{{ $entryIntent === 'import' ? old('capacity') : '' }}" required></label>
            </div>
            <label for="tt-import-mode">Mode<select id="tt-import-mode" aria-describedby="tt-import-mode-help" name="is_demo" class="form-control"><option value="0" @selected((string)old('is_demo', '0') === '0')>Normal draft</option><option value="1" @selected((string)old('is_demo') === '1')>Demonstration - cannot publish</option></select></label>
            <p id="tt-import-mode-help" class="tt-help">For sample CSVs with demo-room and demo-instructor IDs, choose Demonstration. Normal drafts match instructor and room IDs or names to configured resources.</p>
            <label class="mt-3" for="tt-start-csv">CSV file<input id="tt-start-csv" class="form-control" type="file" name="csv" accept=".csv,text/csv" required></label>
            <p class="tt-help mt-2"><a id="tt-sample-csv" data-url="{{ route('admin.timetables.sample') }}" href="{{ route('admin.timetables.sample', ['program_id' => old('program_id', $selectedProgram?->id), 'year_level' => old('year_level', 1)]) }}">Download a CSV template for this program and year</a>. Subject IDs are filled in; add instructor, room, and schedule details.</p>
            <details class="tt-resource mt-3">
                <summary>Teaching hours</summary>
                <div class="tt-grid">
                    <label for="tt-import-close">Latest class end<select id="tt-import-close" name="closing_hour" class="form-control">@foreach([17=>'5 PM',18=>'6 PM',19=>'7 PM'] as $hour=>$label)<option value="{{ $hour }}" @selected((int)old('closing_hour',17) === $hour)>{{ $label }}</option>@endforeach</select></label>
                </div>
            </details>
            <div class="tt-actions"><button type="submit" class="dtc-btn dtc-btn-primary">Preview CSV</button></div>
        </form>
        @if($entryIntent === 'import' && old('creation_preview') && is_array(old('rows')))
            @php
                $previewRows = collect(old('rows'))->filter(fn ($row) => is_array($row));
                $previewIssues = $previewRows->filter(fn ($row) => !empty($row['issue']))->count();
                $previewSubjects = \App\Models\CourseSubject::whereIn('id', $previewRows->pluck('subject_id')->filter(fn ($id) => is_scalar($id)))->get()->keyBy('id');
                $previewResources = old('is_demo') ? collect(app(\App\Services\TimetableService::class)->resources(new \App\Models\TimetableDraft(['is_demo' => true]))) : $timetableResources;
            @endphp
            <section id="tt-import-preview" class="tt-saved-summary" aria-labelledby="tt-import-preview-title">
                <h6 id="tt-import-preview-title">CSV preview - not saved</h6>
                <p class="tt-help">{{ old('group_code') }} / {{ old('preview_school_year') }} / Semester {{ old('preview_semester') }}. Saving creates a draft only. Adjust flagged assignments in the draft.</p>
                <p class="tt-help">{{ $previewRows->count() - $previewIssues }} of {{ $previewRows->count() }} subjects placed / {{ $previewIssues }} need attention</p>
                <p id="tt-import-stale" class="tt-issue" role="status" hidden>Import details changed. Preview the CSV again before saving.</p>
                <form id="tt-import-save" method="POST" action="{{ route('admin.timetables.store') }}">
                    @csrf
                    <input type="hidden" name="intent" value="import">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="creation_preview" value="1">
                    @foreach(['program_id', 'year_level', 'group_code', 'capacity', 'is_demo', 'closing_hour', 'preview_school_year', 'preview_semester'] as $field)
                        <input type="hidden" name="{{ $field }}" value="{{ old($field) }}">
                    @endforeach
                    <div class="tt-table-wrap" role="region" aria-label="Imported schedule preview" tabindex="0">
                        <table class="tt-table tt-schedule-table"><thead><tr><th>Subject</th><th>Days / time</th><th>Room</th><th>Instructor</th><th>Review</th></tr></thead><tbody>
                            @foreach($previewRows as $index => $row)
                                <tr><th scope="row">{{ $previewSubjects->get($row['subject_id'])?->subject_code ?? $row['subject_id'] }}</th><td data-label="Days / time"><span>{{ $row['pattern'] ?? 'Not set' }}<br>{{ $row['start'] ?? '-' }} - {{ $row['end'] ?? '-' }}</span></td><td data-label="Room"><span>{{ $previewResources->firstWhere('id', $row['room_id'] ?? null)['name'] ?? 'Unassigned' }}</span></td><td data-label="Instructor"><span>{{ $previewResources->firstWhere('id', $row['instructor_id'] ?? null)['name'] ?? 'Unassigned' }}</span></td><td data-label="Review"><span>{{ $row['issue'] ?? 'Placed' }}</span></td></tr>
                            @endforeach
                        </tbody></table>
                    </div>
                    @foreach($previewRows as $index => $row)
                        @foreach(['subject_id', 'instructor_id', 'room_id', 'pattern', 'minutes', 'facility', 'start', 'end'] as $column)
                            <input type="hidden" name="rows[{{ $index }}][{{ $column }}]" value="{{ $row[$column] ?? '' }}">
                        @endforeach
                    @endforeach
                    <div class="tt-actions"><button type="submit" class="dtc-btn dtc-btn-primary">Save draft</button></div>
                </form>
            </section>
        @endif
    </details>
</div>
