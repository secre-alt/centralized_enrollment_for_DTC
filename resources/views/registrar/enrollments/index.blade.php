@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Pending Enrollments')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold" style="color:var(--dtc-text);">Pending Enrollments</h4>
        <p class="mb-0" style="color:var(--dtc-text-secondary); font-size:13px;">
            Enrollment submissions awaiting registrar approval.
        </p>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- Enrollments Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold" style="color:var(--dtc-text);">All Pending Enrollments</span>
        <span style="font-size:13px; color:var(--dtc-text-secondary);">{{ $enrollments->total() }} total</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Program</th>
                    <th>Year / Semester</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($enrollments as $enrollment)
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:32px; height:32px; border-radius:50%;
                                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                        display:flex; align-items:center; justify-content:center;
                                        color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                {{ strtoupper(substr($enrollment->user->name, 0, 1)) }}
                            </div>
                            <span style="font-size:13px; font-weight:500;">{{ $enrollment->user->name }}</span>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:13px; font-weight:500; color:var(--dtc-text);">{{ $enrollment->program->name }}</div>
                        <div style="font-size:11px; color:var(--dtc-text-muted);">{{ $enrollment->program->code }}</div>
                    </td>
                    <td style="font-size:13px; color:var(--dtc-text-secondary);">
                        {{ $enrollment->year_level }}{{ ['st','nd','rd','th'][$enrollment->year_level - 1] ?? 'th' }} Year
                        <span style="color:var(--dtc-text-muted);">&middot; Sem {{ $enrollment->semester }}</span>
                    </td>
                    <td>
                        @if($enrollment->status === 'approved')
                            <span class="badge badge-success">Approved</span>
                        @elseif($enrollment->status === 'rejected')
                            <span class="badge badge-danger">Rejected</span>
                        @else
                            <span class="badge badge-warning">Pending</span>
                        @endif
                    </td>
                    <td style="font-size:13px; color:var(--dtc-text-secondary);">
                        {{ $enrollment->created_at->format('M d, Y h:i A') }}
                    </td>
                    <td>
                        <button type="button" class="dtc-review-btn" data-url="{{ route('registrar.enrollments.show', $enrollment) }}">
                            <i class="fas fa-eye"></i> Review
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-4" style="color:var(--dtc-text-muted);">
                        No pending enrollments.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="card-footer" style="background:var(--dtc-surface); border-top:1px solid var(--dtc-border);">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:15px;">
            <small style="color:var(--dtc-text-secondary);">
                Showing
                <strong>{{ $enrollments->firstItem() ?? 0 }}</strong>
                to
                <strong>{{ $enrollments->lastItem() ?? 0 }}</strong>
                of
                <strong>{{ $enrollments->total() }}</strong>
                enrollments
            </small>

            @if($enrollments->hasPages())
            <div>
                {{ $enrollments->onEachSide(1)->links('pagination::bootstrap-4') }}
            </div>
            @endif
        </div>
    </div>
</div>

@include('registrar.enrollments._review-modal')

@endsection