@props(['icon' => 'fa-inbox', 'title' => 'Nothing here yet', 'message' => null])
<div {{ $attributes->merge(['class' => 'dtc-empty-state']) }}>
    <div class="dtc-empty-icon"><i class="fas {{ $icon }}"></i></div>
    <div class="dtc-empty-title">{{ $title }}</div>
    @if($message)<div class="dtc-empty-message">{{ $message }}</div>@endif
    @isset($action)<div class="dtc-empty-action">{{ $action }}</div>@endisset
</div>
