@foreach(['date_from' => ' from', 'date_to' => ' through'] as $dateField => $suffix)
    <div class="dtc-fl-field">
        <label for="{{ $datePrefix }}-{{ $dateField }}">{{ $dateLabel }}{{ $suffix }}</label>
        <input type="date" id="{{ $datePrefix }}-{{ $dateField }}" name="{{ $dateField }}" value="{{ request($dateField) }}" class="form-control" data-dtc-filter>
    </div>
@endforeach
