<input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
@isset($lifecycleAppointment)
<input type="hidden" name="lifecycle_version" value="{{ $lifecycleAppointment->lifecycle_version }}">
@endisset
