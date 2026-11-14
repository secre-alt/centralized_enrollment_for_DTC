@foreach($selectFilters as $field => $definition)
    <div class="dtc-fl-field">
        <label for="{{ $selectPrefix }}-{{ $field }}">{{ $definition['label'] }}</label>
        <select name="{{ $field }}" id="{{ $selectPrefix }}-{{ $field }}" class="form-control" data-dtc-filter>
            <option value="">{{ $definition['all'] }}</option>
            @foreach($definition['options'] as $value => $label)
                <option value="{{ $value }}" @selected((string) request($field) === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
@endforeach
