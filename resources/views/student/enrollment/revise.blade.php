@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Revise Enrollment')

@section('content_header')
    <h1 class="h4">Revise Enrollment</h1>
    <p class="text-muted mb-0">{{ $enrollment->program->name }} · Year {{ $enrollment->year_level }} · Semester {{ $enrollment->semester }}</p>
@endsection

@section('content')
<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="alert alert-warning" role="status">
        <h2 class="h5">Subject Replacement Required</h2>
        <p>{{ $replacementRequest->reason }}</p>
        <p class="mb-0">Your verified payment remains on this enrollment. Do not create another enrollment or payment while revising.</p>
    </div>

    @if($replacementOptions->contains(fn ($options) => $options->isEmpty()))
        <div class="alert alert-info" role="status">
            At least one affected subject has no eligible, capacity-confirmed replacement section. Contact the Registrar for academic guidance or request refund follow-up below; reduced-load and deferral options require an institutional policy that is not configured in this system.
        </div>
    @endif

    <form id="enrollment-revision-form" data-retained-irregular="{{ $retainedIrregular ? '1' : '0' }}" method="POST" action="{{ route('portal.enrollment.revise.submit', $enrollment) }}">
        @csrf
        @foreach($replacementRequest->items as $item)
            @php $options = $replacementOptions[$item->id] ?? collect(); @endphp
            <section class="card">
                <div class="card-header">
                    <strong>{{ $item->originalSubject->subject_code }} — {{ $item->originalSubject->subject_name }}</strong>
                    <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $item->originalSubject->classification)) }}</span>
                </div>
                <div class="card-body">
                    @if($item->originalSchedule)
                        <p class="text-muted">Registrar marked Section {{ $item->originalSchedule->section_code }} unavailable.</p>
                    @endif
                    @if($capacityReconciliationRequired[$item->id] ?? false)
                        <div class="alert alert-warning" role="status">
                            Current-term active enrollments do not all have confirmed section assignments. The Registrar must reconcile those records before the system can confirm capacity for this subject.
                        </div>
                    @endif
                    @if($options->isEmpty())
                        <p class="text-muted mb-0">No approved open sections with capacity are currently available.</p>
                    @else
                        <label for="replacement-{{ $item->id }}">Choose an eligible class section</label>
                        <select id="replacement-{{ $item->id }}" name="replacements[{{ $item->id }}]" class="form-control" data-revision-choice data-original="{{ $item->originalSubject->subject_code }} — {{ $item->originalSubject->subject_name }}{{ $item->originalSchedule ? ' / Section '.$item->originalSchedule->section_code : ' / section not recorded' }}" required>
                            <option value="">Select a section</option>
                            @foreach($options as $offering)
                                <option value="{{ $offering->id }}" data-requires-irregular="{{ $offering->replacement_requires_irregular ? '1' : '0' }}">
                                    {{ $offering->courseSubject->subject_code }}
                                    · Section {{ $offering->section_code }}
                                    · hosted by {{ $offering->hostingProgram->name ?? $offering->courseSubject->program->name }}
                                    · {{ $offering->day_pattern }} {{ \Illuminate\Support\Carbon::parse($offering->time_start)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($offering->time_end)->format('g:i A') }}
                                    · {{ $offering->courseSubject->units }} units
                                    · {{ $offering->remaining_seats }} seat(s) available
                                    @if($offering->replacement_requires_irregular) · Requires Irregular load @endif
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Capacity is checked again when you submit. A seat shown here is not reserved until submission succeeds.</small>
                        @foreach($options->pluck('replacementEquivalency')->filter()->unique('id') as $equivalency)
                            <p class="small mt-2 mb-0">Approved equivalency: {{ $equivalency->equivalentSubject->subject_code }}.
                                {{ $equivalency->approval_reason }}
                                Approved by {{ $equivalency->approver->name }} on {{ $equivalency->approved_at->format('M j, Y') }}.
                                Load treatment: {{ $equivalency->requires_irregular ? 'Irregular' : 'Regular permitted' }}.</p>
                        @endforeach
                    @endif
                </div>
            </section>
        @endforeach

        <div class="card">
            <div class="card-body">
                <h2 class="h5">Review your revised load</h2>
                <p>Only the subjects listed above will change. All other selections, your official program, and the original verified payment stay attached to this enrollment.</p>
                <div id="enrollment-revision-review" aria-live="polite"></div>
                <noscript><p>Review the original subject and chosen section in each field above before confirming.</p></noscript>
                <p class="mb-3">The official degree program remains {{ $enrollment->program->name }}. A change to another section of the same subject does not by itself change Regular/Irregular status. A cross-program offering or approved equivalency follows its recorded academic load treatment.</p>
                <p class="text-muted">The Registrar must review prerequisites and permitted unit loads under school policy before final approval. These academic policies are not configured for automatic validation.</p>
                <div class="form-check mb-3">
                    <input id="confirm-revision" name="confirm_revision" value="1" type="checkbox" class="form-check-input" required>
                    <label for="confirm-revision" class="form-check-label">I reviewed the original and replacement selections and confirm this revised enrollment for Registrar review.</label>
                </div>
                <button type="submit" class="btn btn-primary" @if($replacementOptions->contains(fn ($options) => $options->isEmpty())) disabled @endif>
                    Submit revised enrollment
                </button>
                <a href="{{ route('portal.enrollment.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>

    <div class="card border-warning">
        <div class="card-header"><strong>Request Refund</strong></div>
        <div class="card-body">
            <p>The verified payment will remain in your history. This request is not a completed refund; the Cashier must confirm actual reconciliation.</p>
            <form id="request-refund-{{ $replacementRequest->id }}" method="POST"
                  action="{{ route('portal.enrollment.replacement.refund', $replacementRequest) }}">
                @csrf
                <div class="form-group">
                    <label for="refund-reason-{{ $replacementRequest->id }}">Reason</label>
                    <textarea id="refund-reason-{{ $replacementRequest->id }}" name="reason" class="form-control"
                              rows="3" minlength="5" maxlength="1000" required></textarea>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="confirm_refund" value="1"
                           id="confirm-refund-{{ $replacementRequest->id }}" required>
                    <label class="form-check-label" for="confirm-refund-{{ $replacementRequest->id }}">
                        I confirm I am requesting a refund instead of revising this enrollment.
                    </label>
                </div>
                <button type="submit" class="btn btn-outline-danger">Send refund request to Cashier</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('js')
<script src="{{ asset('js/enrollment-revision.js') }}" defer></script>
@endpush
