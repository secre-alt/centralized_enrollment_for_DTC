@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Book Appointment')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold u-text" >Book an Appointment</h4>
        <p class="mb-0 u-text-secondary-sm" >
            Request a certificate or document from the Registrar's Office
        </p>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header font-weight-bold u-text" >
                <i class="fas fa-calendar-plus mr-2 u-link" ></i>
                Appointment Details
            </div>
            <div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('portal.appointments.store') }}">
                    @csrf

                    {{-- Document Type --}}
                    <div class="form-group">
                        <label>Document Type</label>
                        <div class="row">
                            @foreach([
                                ['value' => 'transcript',    'label' => 'Transcript of Records', 'icon' => 'fa-file-alt'],
                                ['value' => 'diploma',       'label' => 'Diploma',               'icon' => 'fa-graduation-cap'],
                                ['value' => 'certification', 'label' => 'Certification',          'icon' => 'fa-certificate'],
                                ['value' => 'tor',           'label' => 'True Copy of Records',  'icon' => 'fa-copy'],
                            ] as $doc)
                            <div class="col-6 mb-2">
                                <label style="cursor:pointer; width:100%;">
                                    <input type="radio" name="document_type"
                                           value="{{ $doc['value'] }}"
                                           
                                           class="doc-radio u-hidden"
                                           {{ old('document_type') === $doc['value'] ? 'checked' : '' }}>
                                    <div class="doc-option"
                                         style="border:2px solid var(--dtc-border); border-radius:12px;
                                                padding:14px; text-align:center; transition:all 0.2s;
                                                background:var(--dtc-surface-soft);">
                                        <i class="fas {{ $doc['icon'] }}"
                                           style="font-size:20px; color:var(--dtc-text-muted); margin-bottom:6px; display:block;"></i>
                                        <div style="font-size:12px; font-weight:600; color:var(--dtc-text-secondary);">
                                            {{ $doc['label'] }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Slot Selection --}}
                    <div class="form-group">
                        <label>Select Available Date & Time</label>
                        @forelse ($slots as $slot)
                        <label style="cursor:pointer; width:100%; margin-bottom:8px;">
                            <input type="radio" name="appointment_slot_id"
                                   value="{{ $slot->id }}"
                                   style="display:none;"
                                   class="slot-radio"
                                   {{ old('appointment_slot_id') == $slot->id ? 'checked' : '' }}>
                            <div class="slot-option"
                                 style="border:2px solid var(--dtc-border); border-radius:12px; padding:14px 16px;
                                        display:flex; align-items:center; gap:14px; transition:all 0.2s;
                                        background:var(--dtc-surface-soft);">
                                <div style="background:#EEF2FF; color:#0F4CDB; border-radius:10px;
                                            padding:8px 12px; font-size:11px; font-weight:700;
                                            text-align:center; line-height:1.3; flex-shrink:0; min-width:48px;">
                                    {{ \Carbon\Carbon::parse($slot->date)->format('M') }}<br>
                                    <span style="font-size:18px; display:block; line-height:1;">
                                        {{ \Carbon\Carbon::parse($slot->date)->format('d') }}
                                    </span>
                                </div>
                                <div  class="u-flex-1">
                                    <div  class="u-text-sm-bold-primary">
                                        {{ \Carbon\Carbon::parse($slot->date)->format('l, F d, Y') }}
                                    </div>
                                    <div  class="u-text-xxs-secondary">
                                        <i class="fas fa-clock mr-1"></i>
                                        {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}
                                        —
                                        {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                                    </div>
                                </div>
                                <div style="text-align:right; flex-shrink:0;">
                                    @php $left = $slot->availableSlots(); @endphp
                                    <span style="font-size:11px; font-weight:600;
                                                 color:{{ $left > 2 ? '#15803D' : '#D97706' }};">
                                        {{ $left }} slot{{ $left !== 1 ? 's' : '' }} left
                                    </span>
                                </div>
                            </div>
                        </label>
                        @empty
                        <div style="background:var(--dtc-surface-soft); border:2px dashed var(--dtc-border); border-radius:12px;
                                    padding:24px; text-align:center;">
                            <i class="fas fa-calendar-times fa-2x mb-2 u-border-color" ></i>
                            <p style="color:var(--dtc-text-muted); font-size:13px; margin:0;">
                                No available slots at the moment. Please check back later.
                            </p>
                        </div>
                        @endforelse
                    </div>

                    {{-- Purpose --}}
                    <div class="form-group">
                        <label>Purpose <span style="color:var(--dtc-text-muted); font-weight:400;">(optional)</span></label>
                        <textarea name="purpose" class="form-control" rows="3"
                                  placeholder="e.g. For employment application, scholarship, board exam...">{{ old('purpose') }}</textarea>
                    </div>

                    <div class="d-flex gap-2 u-gap-10" >
                        <button type="submit" class="btn btn-primary" {{ $slots->isEmpty() ? 'disabled' : '' }}>
                            <i class="fas fa-calendar-check mr-1"></i> Book Appointment
                        </button>
                        <a href="{{ route('portal.appointments.index') }}" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Info Panel --}}
    <div class="col-lg-5">
        <div class="card u-primary-gradient-btn"
             >
            <div class="card-body p-4">
                <h5 style="font-weight:700; margin-bottom:16px; color:#fff;">
                    <i class="fas fa-info-circle mr-2"></i> Appointment Guide
                </h5>
                <div style="font-size:13px; line-height:1.8; opacity:0.9;">
                    <p><i class="fas fa-check-circle mr-2 u-accent" ></i>
                        Select your document type and preferred time slot.</p>
                    <p><i class="fas fa-check-circle mr-2 u-accent" ></i>
                        Wait for the Registrar to confirm your booking.</p>
                    <p><i class="fas fa-check-circle mr-2 u-accent" ></i>
                        You will receive a notification once confirmed.</p>
                    <p><i class="fas fa-check-circle mr-2 u-accent" ></i>
                        Bring a valid ID on your appointment date.</p>
                    <p><i class="fas fa-check-circle mr-2 u-accent" ></i>
                        Processing fees may apply upon pickup.</p>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header font-weight-bold u-text" >
                Office Information
            </div>
            <div class="card-body" style="font-size:13px; color:var(--dtc-text-secondary); line-height:2;">
                <p class="mb-1"><i class="fas fa-map-marker-alt mr-2" style="color:#0F4CDB; width:16px;"></i>
                    Registrar's Office, DTC Main Building</p>
                <p class="mb-1"><i class="fas fa-clock mr-2" style="color:#0F4CDB; width:16px;"></i>
                    Monday – Friday, 8:00 AM – 5:00 PM</p>
                <p class="mb-0"><i class="fas fa-phone mr-2" style="color:#0F4CDB; width:16px;"></i>
                    Contact Registrar for inquiries</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
// Document type selection styling
document.querySelectorAll('.doc-radio').forEach(radio => {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.doc-option').forEach(opt => {
            opt.style.borderColor = 'var(--dtc-border)';
            opt.style.background = 'var(--dtc-surface-soft)';
            opt.querySelector('i').style.color = 'var(--dtc-text-muted)';
            opt.querySelector('div').style.color = 'var(--dtc-text-secondary)';
        });
        const selected = this.nextElementSibling;
        selected.style.borderColor = '#0F4CDB';
        selected.style.background = '#EEF2FF';
        selected.querySelector('i').style.color = '#0F4CDB';
        selected.querySelector('div').style.color = '#0F4CDB';
    });

    // Apply on page load if old value exists
    if (this.checked) this.dispatchEvent(new Event('change'));
});

// Slot selection styling
document.querySelectorAll('.slot-radio').forEach(radio => {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.slot-option').forEach(opt => {
            opt.style.borderColor = 'var(--dtc-border)';
            opt.style.background = 'var(--dtc-surface-soft)';
        });
        this.nextElementSibling.style.borderColor = '#0F4CDB';
        this.nextElementSibling.style.background = '#EEF2FF';
    });

    if (this.checked) this.dispatchEvent(new Event('change'));
});
</script>
@endsection