<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TimetableDraft;
use App\Models\TimetableResource;
use App\Services\TimetableService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TimetableController extends Controller
{
    private const CSV_COLUMNS = ['subject_id', 'instructor_id', 'room_id', 'pattern', 'minutes', 'facility', 'start', 'end'];

    public function export(TimetableDraft $draft, TimetableService $service)
    {
        $rows = $service->locked(function () use ($draft, $service) {
            $draft = TimetableDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $saved = collect($draft->proposals ?? [])->keyBy('subject_id');

            return $service->subjects($draft)->map(fn ($subject) => ['subject_id' => $subject->id]
                + ($saved->get($subject->id) ?? $draft->configuration['requirements'][$subject->id] ?? []))->all();
        });

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, self::CSV_COLUMNS);
            foreach ($rows as $row) {
                fputcsv($output, array_map(function ($column) use ($row) {
                    $value = (string) ($row[$column] ?? '');

                    return preg_match('/^[=+@\-\t\r\n]/', $value) ? "'".$value : $value;
                }, self::CSV_COLUMNS));
            }
            fclose($output);
        }, 'timetable-'.$draft->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function resource(Request $request, TimetableService $service)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:timetable_resources,id'],
            'kind' => ['required', 'in:room,instructor'],
            'name' => ['required', 'string', 'max:50', 'not_regex:/^\s*TBA\s*$/i'],
            'capacity' => ['required_if:kind,room', 'nullable', 'integer', 'min:1', 'max:1000'],
            'facility' => ['required_if:kind,room', 'nullable', Rule::in(TimetableService::FACILITIES)],
            'active' => ['required', 'boolean'],
            'availability' => ['nullable', 'array:MO,TU,WE,TH,FR,SA,SU'],
            'availability.*' => ['array:start,end'],
            'availability.*.start' => ['nullable', 'date_format:H:i'],
            'availability.*.end' => ['nullable', 'date_format:H:i'],
        ]);
        $service->locked(function () use ($data) {
            $resource = ! empty($data['id']) ? TimetableResource::findOrFail($data['id']) : new TimetableResource;
            if ($resource->exists && $resource->kind !== $data['kind']) {
                throw ValidationException::withMessages(['resource' => 'A room cannot be changed into an instructor.']);
            }
            $configuration = $data['kind'] === 'room'
                ? ['active' => (bool) $data['active'], 'capacity' => $data['capacity'], 'facility' => $data['facility']]
                : ['active' => (bool) $data['active'], 'availability' => $data['availability'] ?? []];
            foreach ($configuration['availability'] ?? [] as $window) {
                if ((! empty($window['start']) || ! empty($window['end']))
                    && (empty($window['start']) || empty($window['end']) || $window['end'] <= $window['start'])) {
                    throw ValidationException::withMessages(['availability' => 'Each available day needs a start and a later end time. Leave both blank for unavailable days.']);
                }
            }
            $name = trim($data['name']);
            if ($name === '' || TimetableResource::where('kind', $data['kind'])->where('name', $name)->where('id', '!=', $resource->id ?? 0)->exists()) {
                throw ValidationException::withMessages(['name' => 'Use a unique non-empty resource name.']);
            }
            if ($resource->exists) {
                foreach (TimetableDraft::where('status', 'published')->get() as $published) {
                    $key = $resource->kind === 'room' ? 'room_id' : 'instructor_id';
                    if (collect($published->proposals)->contains(fn ($row) => (string) ($row[$key] ?? '') === (string) $resource->id)) {
                        throw ValidationException::withMessages(['resource' => 'This resource is used by a published timetable. Keep its recorded details intact; timetable amendments are not supported yet.']);
                    }
                }
            }
            $resource->fill(['kind' => $data['kind'], 'name' => $name, 'configuration' => $configuration])->save();
        });

        return redirect()->to(route('admin.programs.index', ['timetable' => $request->input('timetable')]).'#timetable-workspace')->with('success', 'Timetable resource saved.');
    }

    public function store(Request $request, TimetableService $service)
    {
        $data = $request->validate([
            'program_id' => ['required', 'exists:programs,id'], 'year_level' => ['required', 'integer', 'between:1,4'],
            'group_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/'],
            'capacity' => ['required', 'integer', 'between:1,1000'], 'is_demo' => ['required', 'boolean'],
            'closing_hour' => ['required', 'integer', 'in:17,18,19'],
            'source_id' => ['nullable', 'integer', 'exists:timetable_drafts,id'],
        ]);
        $draft = $service->locked(function () use ($data, $request, $service) {
            $schoolYear = trim((string) Setting::get('current_school_year', ''));
            $semester = ['1st' => 1, '2nd' => 2, 'Summer' => 3][Setting::get('current_semester', '')] ?? null;
            if ($schoolYear === '' || ! $semester) {
                throw ValidationException::withMessages(['timetable' => 'Configure the current academic term before creating a draft.']);
            }
            $draft = TimetableDraft::create([
                'program_id' => $data['program_id'], 'year_level' => $data['year_level'], 'group_code' => strtoupper(trim($data['group_code'])),
                'capacity' => $data['capacity'], 'is_demo' => $data['is_demo'], 'school_year' => $schoolYear, 'semester' => $semester,
                'configuration' => ['closing_hour' => $data['closing_hour'], 'requirements' => []], 'created_by' => $request->user()->id,
            ]);
            $subjects = $service->subjects($draft);
            if ($subjects->isEmpty()) {
                throw ValidationException::withMessages(['timetable' => 'This program and year have no curriculum subjects for the configured semester. Add the curriculum first.']);
            }
            if (! empty($data['source_id'])) {
                $source = TimetableDraft::whereKey($data['source_id'])->lockForUpdate()->firstOrFail();
                if ($source->program_id != $draft->program_id || $source->year_level != $draft->year_level
                    || $source->semester != $draft->semester || $source->is_demo !== $draft->is_demo) {
                    throw ValidationException::withMessages(['source_id' => 'Copy from the same program, year level, semester and demonstration mode.']);
                }
                $saved = collect($source->proposals ?? [])->keyBy('subject_id');
                $rows = $subjects->map(fn ($subject) => ['subject_id' => $subject->id]
                    + ($saved->get($subject->id) ?? $source->configuration['requirements'][$subject->id] ?? [])
                    + array_fill_keys(self::CSV_COLUMNS, null))->all();
                $config = $draft->configuration;
                $config['requirements'] = collect($rows)->mapWithKeys(fn ($row) => [$row['subject_id'] => collect($row)->only(['instructor_id', 'pattern', 'minutes', 'facility'])->all()])->all();
                $draft->update(['configuration' => $config, 'proposals' => $service->review($draft, $rows)]);
            } elseif ($draft->is_demo) {
                $requirements = [];
                foreach ($subjects as $subject) {
                    // Demonstration preset only; never infer live contact hours from units.
                    $requirements[$subject->id] = ['instructor_id' => 'demo-instructor', 'pattern' => 'MWF', 'minutes' => 60, 'facility' => 'classroom'];
                }
                $draft->update(['configuration' => ['closing_hour' => $data['closing_hour'], 'requirements' => $requirements]]);
                $draft->update(['proposals' => $service->generate($draft)]);
            }

            return $draft;
        });

        return $this->back($draft, $draft->is_demo
            ? 'Demonstration created with fictional presets. It cannot be published.'
            : 'Draft created. Enter teaching requirements, then generate suggestions.');
    }

    public function update(Request $request, TimetableDraft $draft, TimetableService $service)
    {
        $preview = $request->input('action') === 'import';
        if ($preview) {
            $request->validate(['csv' => ['required', 'file', 'max:256']]);
            $file = fopen($request->file('csv')->getRealPath(), 'r');
            try {
                $header = fgetcsv($file);
                if ($header) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
                }
                if ($header !== self::CSV_COLUMNS) {
                    throw ValidationException::withMessages(['csv' => 'Use the exported CSV template without changing its column headings.']);
                }
                $rows = [];
                while (($values = fgetcsv($file)) !== false) {
                    if ($values === [null]) {
                        continue;
                    }
                    if (count($values) !== count($header) || count($rows) >= 100) {
                        throw ValidationException::withMessages(['csv' => 'CSV rows must contain all eight columns, with at most 100 subjects.']);
                    }
                    $rows[] = array_combine($header, array_map(fn ($value) => trim((string) $value) === '' ? null : trim((string) $value), $values));
                }
            } finally {
                fclose($file);
            }
            $request->merge(['action' => 'review', 'rows' => $rows]);
        }
        $data = $request->validate([
            'action' => ['required', 'in:generate,review,publish,clear'],
            'reviewed' => ['exclude_unless:action,publish', 'required', 'accepted'],
            'confirmed' => ['exclude_unless:action,clear', 'required', 'accepted'],
            'rows' => ['exclude_if:action,clear,publish', 'required', 'array', 'max:100'],
            'rows.*.subject_id' => ['required', 'integer'],
            'rows.*.instructor_id' => ['nullable', 'string', 'max:30'],
            'rows.*.room_id' => ['nullable', 'string', 'max:30'],
            'rows.*.pattern' => ['nullable', Rule::in(TimetableService::PATTERNS)],
            'rows.*.minutes' => ['nullable', 'integer', 'between:30,480', 'multiple_of:30'],
            'rows.*.facility' => ['nullable', Rule::in(TimetableService::FACILITIES)],
            'rows.*.start' => ['nullable', 'date_format:H:i'], 'rows.*.end' => ['nullable', 'date_format:H:i'],
        ]);
        $previewRows = $service->locked(function () use ($draft, $service, $data, $request, $preview) {
            $draft = TimetableDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($draft->status !== 'draft') {
                throw ValidationException::withMessages(['timetable' => 'Published timetables cannot be regenerated or edited.']);
            }
            if ($data['action'] === 'publish') {
                $service->publish($draft, $request->user()->id);

                return;
            }
            if ($data['action'] === 'clear') {
                $config = $draft->configuration;
                $config['requirements'] = collect($config['requirements'] ?? [])->map(fn ($row) => collect($row)->only(['minutes', 'facility'])->all())->all();
                $draft->update(['configuration' => $config, 'proposals' => null]);

                return;
            }
            $rows = $data['rows'];
            $ids = $service->subjects($draft)->pluck('id')->sort()->values()->all();
            $submittedIds = collect($rows)->pluck('subject_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($ids !== $submittedIds) {
                throw ValidationException::withMessages(['timetable' => 'Include every curriculum subject exactly once.']);
            }
            if ($preview) {
                return collect($service->review($draft, $rows))->sortBy('subject_id')->values()->all();
            }
            $requirements = [];
            foreach ($rows as $row) {
                $requirements[$row['subject_id']] = collect($row)->only(['instructor_id', 'pattern', 'minutes', 'facility'])->all();
            }
            $config = $draft->configuration;
            $config['requirements'] = $requirements;
            $draft->update(['configuration' => $config]);
            $draft->update(['proposals' => $data['action'] === 'generate' ? $service->generate($draft) : $service->review($draft, $rows)]);
        });

        if ($preview) {
            return $this->back($draft, 'CSV preview loaded below. Review the assignments and issues, then Save review to apply them.')
                ->withInput(['rows' => $previewRows]);
        }
        if ($data['action'] === 'clear') {
            return $this->back($draft, 'Draft assignments cleared. Subjects and group details were kept.');
        }

        return $this->back($draft, $data['action'] === 'publish' ? 'Timetable published. Eligible new enrollments can use this group for automatic section assignment. Existing holds still need Registrar assignment.' : 'Draft saved. Review the assignments and unresolved items.');
    }

    private function back(TimetableDraft $draft, string $message)
    {
        return redirect()->to(route('admin.programs.index', ['program' => $draft->program_id, 'timetable' => $draft->id]).'#timetable-workspace')->with('success', $message);
    }
}
