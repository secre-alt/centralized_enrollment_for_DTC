<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\TimetableDraft;
use App\Models\TimetableResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableService
{
    public const DAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    public const PATTERNS = ['MWF', 'TTH', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    public const FACILITIES = ['classroom', 'computer_lab', 'food_service', 'outdoor'];

    // Every timetable/resource mutation and existing manual schedule write uses
    // this same row. Read committed schedules only AFTER obtaining the lock.
    public function locked(callable $work)
    {
        return DB::transaction(function () use ($work) {
            if (! DB::table('timetable_locks')->where('id', 1)->lockForUpdate()->first()) {
                throw new \LogicException('The scheduling lock is missing. Restore the migration-created mutex before editing timetables.');
            }

            return $work();
        }, 3);
    }

    public function subjects(TimetableDraft $draft)
    {
        $query = CourseSubject::where('program_id', $draft->program_id)
            ->where('year_level', $draft->year_level)->where('semester', $draft->semester)
            ->orderBy('id');
        if ($draft->status === 'published') {
            $query->whereIn('id', array_column($draft->proposals ?? [], 'subject_id'));
        }

        return $query->lockForUpdate()->get();
    }

    public function resources(TimetableDraft $draft): array
    {
        if ($draft->is_demo) {
            return [
                ['id' => 'demo-room', 'kind' => 'room', 'name' => 'DEMO 1T', 'configuration' => ['capacity' => 40, 'facility' => 'classroom', 'active' => true]],
                ['id' => 'demo-instructor', 'kind' => 'instructor', 'name' => 'Demonstration instructor', 'configuration' => ['active' => true, 'availability' => array_fill_keys(self::DAYS, ['start' => '08:00', 'end' => '17:00'])]],
            ];
        }

        return TimetableResource::orderBy('id')->lockForUpdate()->get()->toArray();
    }

    public function generate(TimetableDraft $draft): array
    {
        $resources = $this->resources($draft);
        $busy = $this->committed($draft);
        $rows = [];
        $requirements = $draft->configuration['requirements'] ?? [];
        foreach ($this->subjects($draft) as $subject) {
            $requirement = $requirements[$subject->id] ?? [];
            $row = ['subject_id' => $subject->id, 'subject_code' => $subject->subject_code, 'room_id' => null, 'start' => null, 'end' => null, 'issue' => null] + $requirement;
            if (empty($row['instructor_id']) || empty($row['pattern']) || empty($row['minutes']) || empty($row['facility'])) {
                $row['issue'] = 'Set the instructor, meeting pattern, duration and facility.';
                $rows[] = $row;

                continue;
            }
            $instructor = collect($resources)->firstWhere('id', $row['instructor_id']);
            $placed = false;
            foreach ($resources as $room) {
                if ($room['kind'] !== 'room') {
                    continue;
                }
                for ($minute = 480; $minute + (int) $row['minutes'] <= $this->closing($draft); $minute += 30) {
                    $candidate = array_merge($row, ['room_id' => $room['id'], 'start' => $this->time($minute), 'end' => $this->time($minute + (int) $row['minutes'])]);
                    if ($this->problem($draft, $candidate, $room, $instructor, $busy) === null) {
                        $rows[] = $candidate;
                        $busy[] = $this->busyRow($draft, $candidate, $room, $instructor);
                        $placed = true;
                        break 2;
                    }
                }
            }
            if (! $placed) {
                $row['issue'] = 'No placement found with these resources and availability. Adjust the requirements or place it manually.';
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function review(TimetableDraft $draft, array $rows): array
    {
        $subjects = $this->subjects($draft)->keyBy('id');
        if (count($rows) !== $subjects->count() || count(array_unique(array_column($rows, 'subject_id'))) !== count($rows)) {
            throw ValidationException::withMessages(['timetable' => 'Include every curriculum subject exactly once.']);
        }
        $resources = collect($this->resources($draft));
        $busy = $this->committed($draft);
        foreach ($rows as &$row) {
            if (! $subjects->has($row['subject_id'])) {
                throw ValidationException::withMessages(['timetable' => 'A subject does not belong to this group curriculum and term.']);
            }
            $row['subject_code'] = $subjects[$row['subject_id']]->subject_code;
            $room = $resources->firstWhere('id', $row['room_id']);
            $instructor = $resources->firstWhere('id', $row['instructor_id']);
            $row['issue'] = $this->problem($draft, $row, $room, $instructor, $busy);
            if (! $row['issue']) {
                $busy[] = $this->busyRow($draft, $row, $room, $instructor);
            }
        }

        return $rows;
    }

    public function publish(TimetableDraft $draft, int $actor): void
    {
        if ($draft->status !== 'draft' || $draft->is_demo) {
            throw ValidationException::withMessages(['timetable' => 'Only a normal, unpublished draft can be published. Demonstrations cannot create live offerings.']);
        }
        $rows = $this->review($draft, $draft->proposals ?? []);
        if (! $rows || collect($rows)->contains(fn ($row) => ! empty($row['issue']))) {
            throw ValidationException::withMessages(['timetable' => 'Resolve every missing assignment or conflict and save the review before publishing.']);
        }
        $resources = collect($this->resources($draft));
        foreach ($rows as $row) {
            if (ClassSchedule::where('school_year', $draft->school_year)->where('semester', $draft->semester)
                ->where('course_subject_id', $row['subject_id'])->where('section_code', $draft->group_code)->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['timetable' => 'This subject already has an offering for the group and term. Existing offerings are preserved.']);
            }
            ClassSchedule::create([
                'course_subject_id' => $row['subject_id'], 'hosting_program_id' => $draft->program_id,
                'section_code' => $draft->group_code, 'school_year' => $draft->school_year, 'semester' => $draft->semester,
                'capacity' => $draft->capacity, 'is_active' => true, 'requires_irregular' => false,
                'day_pattern' => $row['pattern'], 'time_start' => $row['start'], 'time_end' => $row['end'],
                'room' => $resources->firstWhere('id', $row['room_id'])['name'],
                'instructor_name' => $resources->firstWhere('id', $row['instructor_id'])['name'],
                'timetable_draft_id' => $draft->id,
            ]);
        }
        $draft->update(['status' => 'published', 'proposals' => $rows, 'published_by' => $actor, 'published_at' => now()]);
    }

    public function assertManualSchedule(array $data, int $programId, string $schoolYear, ?int $semester): void
    {
        $published = ClassSchedule::whereNotNull('timetable_draft_id')->where('is_active', true)
            ->where('school_year', $schoolYear)->where('semester', $semester)->lockForUpdate()->get();
        foreach ($published as $other) {
            $shared = (! empty($data['room']) && $this->same($data['room'], $other->room))
                || (! empty($data['instructor_name']) && $this->same($data['instructor_name'], $other->instructor_name))
                || (! empty($data['section_code']) && $programId === (int) $other->hosting_program_id && $this->same($data['section_code'], $other->section_code));
            if (! $shared) {
                continue;
            }
            $days = $this->days($data['day_pattern']);
            if (! $days || (array_intersect($days, $this->days($other->day_pattern))
                && $this->minutes($data['time_start']) < $this->minutes($other->time_end)
                && $this->minutes($data['time_end']) > $this->minutes($other->time_start))) {
                throw ValidationException::withMessages(['schedule' => 'This manual schedule conflicts with a published timetable room, instructor or student group.']);
            }
        }
    }

    private function closing(TimetableDraft $draft): int
    {
        return ((int) ($draft->configuration['closing_hour'] ?? 17)) * 60;
    }

    private function time(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }

    private function minutes(?string $time): ?int
    {
        return $time && preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::00)?$/', $time, $parts)
            ? (int) $parts[1] * 60 + (int) $parts[2] : null;
    }

    public function days(?string $pattern): array
    {
        $pattern = strtoupper(trim((string) $pattern));
        $pattern = str_replace(['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'], self::DAYS, $pattern);
        $pattern = preg_replace('/[\s,\/&-]+/', '', $pattern);
        preg_match_all('/TH|MO|TU|WE|FR|SA|SU|M|T|W|R|F/', $pattern, $matches);
        if (implode('', $matches[0]) !== $pattern) {
            return [];
        }

        return array_unique(array_map(fn ($day) => ['M' => 'MO', 'T' => 'TU', 'W' => 'WE', 'R' => 'TH', 'F' => 'FR'][$day] ?? $day, $matches[0]));
    }

    private function same(?string $a, ?string $b): bool
    {
        return mb_strtoupper(trim((string) $a)) === mb_strtoupper(trim((string) $b));
    }

    private function problem(TimetableDraft $draft, array $row, ?array $room, ?array $instructor, array $busy): ?string
    {
        if (! $room || $room['kind'] !== 'room' || ! $instructor || $instructor['kind'] !== 'instructor'
            || empty($room['configuration']['active']) || empty($instructor['configuration']['active'])) {
            return 'Choose an active room and instructor.';
        }
        if ((int) ($room['configuration']['capacity'] ?? 0) < $draft->capacity
            || ($room['configuration']['facility'] ?? null) !== ($row['facility'] ?? null)) {
            return 'The room capacity or facility does not meet this class requirement.';
        }
        $days = $this->days($row['pattern'] ?? '');
        $start = $this->minutes($row['start'] ?? null);
        $end = $this->minutes($row['end'] ?? null);
        if (! $days || $start === null || $end === null || $start < 480 || $end > $this->closing($draft)
            || $end <= $start || $end - $start !== (int) ($row['minutes'] ?? 0)
            || ($start < 780 && $end > 720)) {
            return 'Set valid meeting times within the teaching day, outside the noon–1 PM lunch break, matching the required duration.';
        }
        foreach ($days as $day) {
            $window = $instructor['configuration']['availability'][$day] ?? [];
            $availableStart = $this->minutes($window['start'] ?? null);
            $availableEnd = $this->minutes($window['end'] ?? null);
            if ($availableStart === null || $availableEnd === null || $start < $availableStart || $end > $availableEnd) {
                return 'The instructor is unavailable during one or more meetings.';
            }
        }
        foreach ($busy as $other) {
            if (! $this->same($room['name'], $other['room']) && ! $this->same($instructor['name'], $other['instructor'])
                && ! ($draft->program_id == $other['program_id'] && $this->same($draft->group_code, $other['group']))) {
                continue;
            }
            if (! empty($other['unknown_term'])) {
                return 'A shared resource has a legacy schedule without a confirmed term. Reconcile it before publishing.';
            }
            $otherDays = $this->days($other['pattern']);
            $otherStart = $this->minutes($other['start']);
            $otherEnd = $this->minutes($other['end']);
            if (! $otherDays || $otherStart === null || $otherEnd === null || $otherEnd <= $otherStart) {
                return 'A shared room, instructor or group has an unresolved existing schedule. Reconcile that offering first.';
            }
            if (array_intersect($days, $otherDays) && $start < $otherEnd && $end > $otherStart) {
                return 'This meeting overlaps an existing room, instructor or student-group assignment.';
            }
        }

        return null;
    }

    private function busyRow(TimetableDraft $draft, array $row, array $room, array $instructor): array
    {
        return ['room' => $room['name'], 'instructor' => $instructor['name'], 'group' => $draft->group_code,
            'program_id' => $draft->program_id, 'pattern' => $row['pattern'], 'start' => $row['start'], 'end' => $row['end']];
    }

    private function committed(TimetableDraft $draft): array
    {
        // Demonstrations remain isolated from live resources and offerings.
        if ($draft->is_demo) {
            return [];
        }

        return ClassSchedule::where(fn ($query) => $query->where('school_year', $draft->school_year)->orWhereNull('school_year'))
            ->where(fn ($query) => $query->where('semester', $draft->semester)->orWhereNull('semester'))
            ->where('is_active', true)->with('courseSubject')->orderBy('id')->lockForUpdate()->get()
            ->map(fn ($schedule) => ['room' => $schedule->room, 'instructor' => $schedule->instructor_name,
                'group' => $schedule->section_code, 'program_id' => $schedule->hosting_program_id ?: $schedule->courseSubject->program_id,
                'pattern' => $schedule->day_pattern, 'start' => $schedule->time_start, 'end' => $schedule->time_end,
                'unknown_term' => ! $schedule->school_year || ! $schedule->semester])->all();
    }
}
