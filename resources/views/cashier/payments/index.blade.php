@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Process Payments')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Process Payments</h4>
        <p class="mb-0" style="color:#64748B; font-size:13px;">
            Approved enrollments awaiting payment collection
        </p>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Filter Tabs --}}
<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
    @foreach ([
        'all'           => 'All',
        'unpaid'        => 'Unpaid',
        'gcash_pending' => 'GCash Pending',
        'verified'      => 'Verified',
        'walk_in'       => 'Walk-in',
        'rejected'      => 'Rejected',
    ] as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['filter' => $key]) }}"
           style="padding:4px 14px; border-radius:20px; font-size:12px; font-weight:600;
                  text-decoration:none; border:1.5px solid;
                  {{ $filter === $key
                      ? 'background:#0F4CDB; color:#fff; border-color:#0F4CDB;'
                      : 'background:#fff; color:#64748B; border-color:#E2E8F0;' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

@if ($enrollments->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle fa-4x mb-3" style="color:#E2E8F0;"></i>
            <h5 style="color:#1E293B; font-weight:700;">No Results</h5>
            <p style="color:#64748B; font-size:13px;">
                No enrollments match the selected filter.
            </p>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Program</th>
                        <th>Year / Semester</th>
                        <th>Subjects</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Fee</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enrollments as $enrollment)
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:38px; height:38px; border-radius:50%;
                                            background:linear-gradient(135deg,#22C55E,#059669);
                                            display:flex; align-items:center; justify-content:center;
                                            color:#fff; font-weight:700; font-size:14px; flex-shrink:0;">
                                    {{ strtoupper(substr($enrollment->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                        {{ $enrollment->user->name }}
                                    </div>
                                    <div style="font-size:11px; color:#64748B;">
                                        {{ $enrollment->user->email }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:13px; color:#1E293B; font-weight:500;">
                            {{ $enrollment->program->name }}
                        </td>
                        <td style="font-size:13px; color:#64748B;">
                            Year {{ $enrollment->year_level }} — Sem {{ $enrollment->semester }}
                        </td>
                        <td>
                            <span style="background:#EEF2FF; color:#0F4CDB; font-size:12px;
                                         font-weight:600; padding:3px 10px; border-radius:20px;">
                                {{ count($enrollment->subject_ids) }} subjects
                            </span>
                        </td>
                        <td style="font-size:13px; color:#1E293B;">
                            @if ($enrollment->payment)
                                {{ $enrollment->payment->isWalkIn() ? 'Walk-in' : 'GCash' }}
                            @else
                                <span style="color:#94A3B8;">—</span>
                            @endif
                        </td>
                        <td>
                            @if (! $enrollment->payment)
                                <span style="background:#F1F5F9; color:#64748B; font-size:11px;
                                             font-weight:600; padding:3px 10px; border-radius:20px;">
                                    Unpaid
                                </span>
                            @elseif ($enrollment->payment->isPending())
                                <span style="background:#FEF9C3; color:#92400E; font-size:11px;
                                             font-weight:600; padding:3px 10px; border-radius:20px;">
                                    Pending
                                </span>
                            @elseif ($enrollment->payment->isVerified())
                                <span style="background:#DCFCE7; color:#15803D; font-size:11px;
                                             font-weight:600; padding:3px 10px; border-radius:20px;">
                                    Verified
                                </span>
                            @elseif ($enrollment->payment->isRejected())
                                <span style="background:#FEE2E2; color:#B91C1C; font-size:11px;
                                             font-weight:600; padding:3px 10px; border-radius:20px;">
                                    Rejected
                                </span>
                            @endif
                        </td>
                        <td>
                            <span style="font-size:14px; font-weight:800; color:#15803D;">
                                ₱{{ number_format(\App\Models\Setting::get('enrollment_fee', 500), 2) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('cashier.payments.show', $enrollment) }}"
                               style="background:#DCFCE7; color:#15803D; padding:6px 14px;
                                      border-radius:8px; font-size:12px; font-weight:600;
                                      text-decoration:none;">
                                <i class="fas fa-money-bill-wave mr-1"></i> Process
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $enrollments->links() }}
    </div>
@endif

@endsection