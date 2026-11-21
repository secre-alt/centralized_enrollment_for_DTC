@php
    $resourceConfig = $resource?->configuration ?? [];
    $resourceKey = $resource ? (string) $resource->id : 'new-'.$resourceKind;
    $resourceLocked = $resource && in_array((string) $resource->id, $lockedTimetableResources, true);
@endphp
<form method="POST" action="{{ route('admin.timetables.resources') }}" class="tt-resource-form">
    @csrf
    <input type="hidden" name="_timetable_form" value="1">
    <input type="hidden" name="kind" value="{{ $resourceKind }}">
    <input type="hidden" name="id" value="{{ $resource?->id }}">
    <input type="hidden" name="timetable" value="{{ $timetableDraft?->id }}">
    @if($resourceLocked)<p class="tt-notice">Read only: used by a published timetable. Published resource details cannot be amended here.</p>@endif
    <fieldset @disabled($resourceLocked)>
    <div class="tt-grid">
        <label for="tt-name-{{ $resourceKey }}">{{ $resourceKind === 'room' ? 'Room name' : 'Instructor name' }}
            <input id="tt-name-{{ $resourceKey }}" name="name" class="form-control" value="{{ $resource?->name }}" maxlength="50" required placeholder="{{ $resourceKind === 'room' ? 'e.g. 1T or COMPLAB' : 'Full name' }}">
        </label>
        @if($resourceKind === 'room')
            <label for="tt-capacity-{{ $resourceKey }}">Seat capacity
                <input id="tt-capacity-{{ $resourceKey }}" name="capacity" class="form-control" type="number" min="1" max="1000" value="{{ $resourceConfig['capacity'] ?? '' }}" required>
            </label>
            <label for="tt-facility-{{ $resourceKey }}">Facility
                <select id="tt-facility-{{ $resourceKey }}" name="facility" class="form-control">
                    @foreach(\App\Services\TimetableService::FACILITIES as $facility)
                        <option value="{{ $facility }}" @selected(($resourceConfig['facility'] ?? '') === $facility)>{{ ucwords(str_replace('_', ' ', $facility)) }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label for="tt-active-{{ $resourceKey }}">Availability
            <select id="tt-active-{{ $resourceKey }}" name="active" class="form-control">
                <option value="1" @selected($resourceConfig['active'] ?? true)>Active</option>
                <option value="0" @selected(isset($resourceConfig['active']) && !$resourceConfig['active'])>Inactive</option>
            </select>
        </label>
    </div>
    @if($resourceKind === 'instructor')
        <p class="tt-help">Enter availability for each day. Leave both times blank when unavailable. The generator always preserves the noon–1 PM lunch break.</p>
        <div class="tt-availability">
            @foreach(\App\Services\TimetableService::DAYS as $day)
                <fieldset>
                    <legend>{{ ['MO'=>'Monday','TU'=>'Tuesday','WE'=>'Wednesday','TH'=>'Thursday','FR'=>'Friday','SA'=>'Saturday','SU'=>'Sunday'][$day] }}</legend>
                    <label for="tt-start-{{ $resourceKey }}-{{ $day }}">From<input id="tt-start-{{ $resourceKey }}-{{ $day }}" type="time" class="form-control" name="availability[{{ $day }}][start]" value="{{ $resourceConfig['availability'][$day]['start'] ?? '' }}"></label>
                    <label for="tt-end-{{ $resourceKey }}-{{ $day }}">Until<input id="tt-end-{{ $resourceKey }}-{{ $day }}" type="time" class="form-control" name="availability[{{ $day }}][end]" value="{{ $resourceConfig['availability'][$day]['end'] ?? '' }}"></label>
                </fieldset>
            @endforeach
        </div>
    @endif
    @unless($resourceLocked)<div class="tt-actions"><button class="dtc-btn dtc-btn-secondary" type="submit">Save {{ $resourceKind }}</button></div>@endunless
    </fieldset>
</form>
