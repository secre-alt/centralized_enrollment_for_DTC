@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Enroll Now')

@section('content')
<div class="row justify-content-center dtc-enrollment-create">
    <div class="col-lg-8 dtc-enrollment-create-main">
        <div class="card dtc-enrollment-create-card">
            <div class="card-header dtc-enrollment-create-header">
                <a href="{{ route('portal.dashboard') }}" class="dtc-enrollment-create-back" aria-label="Return to dashboard">
                    <i data-lucide="arrow-left" aria-hidden="true"></i>
                </a>
                <div>
                    <h1>Enrollment details</h1>
                </div>
            </div>
            <div class="card-body dtc-enrollment-create-body">

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('portal.enrollment.store') }}" class="dtc-enrollment-create-form">
                    @csrf

                    {{-- Program --}}
                    @if ($lockedProgram)
                        <div class="form-group">
                            <label>Program</label>
                            <p class="dtc-enrollment-locked-program">{{ $lockedProgram->name }}</p>
                            <input type="hidden" name="program_id" id="program_id" value="{{ $lockedProgram->id }}">
                        </div>
                    @else
                        <div class="form-group">
                            <label>Program</label>
                            <select name="program_id" id="program_id" class="form-control" required>
                                <option value="">— Select Program —</option>
                                @foreach ($programs as $program)
                                    <option value="{{ $program->id }}"
                                        {{ old('program_id') == $program->id ? 'selected' : '' }}>
                                        {{ $program->name }} ({{ $program->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Enrollment Type --}}
                    <fieldset class="form-group dtc-enrollment-type-group" aria-describedby="subject-selection-help">
                        <legend>Which subjects will you take?</legend>
                        <div class="dtc-enrollment-type-options">
                            <label class="dtc-enrollment-type-option">
                                <input type="radio" name="is_irregular" value="0" id="type-regular" {{ (string) old('is_irregular', '0') === '0' ? 'checked' : '' }} aria-describedby="standard-subjects-help">
                                <span><strong>Regular subjects</strong><small id="standard-subjects-help">Subjects for your selected year and semester.</small></span>
                            </label>
                            <label class="dtc-enrollment-type-option">
                                <input type="radio" name="is_irregular" value="1" id="type-irregular" {{ (string) old('is_irregular') === '1' ? 'checked' : '' }} aria-describedby="custom-subjects-help">
                                <span><strong>Irregular subjects</strong><small id="custom-subjects-help">Subjects from different year levels or semesters.</small></span>
                            </label>
                        </div>
                        <small id="subject-selection-help">
                            Transferee or unsure? Ask the Registrar which subjects to take.
                        </small>
                    </fieldset>

                    {{-- Year + Semester --}}
                    <div class="row dtc-enrollment-term-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Year Level</label>
                                <select name="year_level" id="year_level" class="form-control" required>
                                    <option value="1" {{ old('year_level') == 1 ? 'selected' : '' }}>1st Year</option>
                                    <option value="2" {{ old('year_level') == 2 ? 'selected' : '' }}>2nd Year</option>
                                    <option value="3" {{ old('year_level') == 3 ? 'selected' : '' }}>3rd Year</option>
                                    <option value="4" {{ old('year_level') == 4 ? 'selected' : '' }}>4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Semester</label>
                                <select name="semester" id="semester" class="form-control" required>
                                    <option value="1" {{ old('semester') == 1 ? 'selected' : '' }}>1st Semester</option>
                                    <option value="2" {{ old('semester') == 2 ? 'selected' : '' }}>2nd Semester</option>
                                    <option value="3" {{ old('semester') == 3 ? 'selected' : '' }}>Summer</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Subjects --}}
                    <div class="form-group dtc-enrollment-subjects-group">
                        <div class="dtc-enrollment-form-section-heading">
                            <div><strong>Select subjects</strong></div>
                        </div>
                        <div class="dtc-subjects-hint" id="subjects-hint">
                            All subjects for the selected year and semester will be selected automatically.
                        </div>
                        <div id="subjects-container">
                            <div style="background:var(--dtc-surface-soft); border:2px dashed var(--dtc-border);
                                        border-radius:12px; padding:24px; text-align:center;">
                                <i data-lucide="book" class="mb-2 u-border-color" style="width:2em;height:2em"></i>
                                <p style="color:var(--dtc-text-muted); font-size:13px; margin:0;">
                                    Select program, year level, and semester to load subjects.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Fee Notice --}}
                    <div class="alert alert-info dtc-enrollment-fee-notice" style="border-radius:12px; padding:16px; margin-bottom:20px;
                                display:flex; align-items:center; gap:12px;">
                        <div style="width:40px; height:40px; border-radius:10px;
                                    background:var(--dtc-primary); display:flex; align-items:center;
                                    justify-content:center; flex-shrink:0;">
                            <i data-lucide="info" style="color:#fff; font-size:16px;"></i>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600;">
                                Enrollment Fee: ₱{{ number_format($fee, 2) }} (fixed rate)
                            </div>
                            <div class="dtc-enrollment-payment-copy">
                                Pay first; the Registrar reviews your subjects after the Cashier verifies payment.
                            </div>
                            <div class="dtc-enrollment-payment-options" aria-label="Available payment options">
                                <span><i data-lucide="smartphone" aria-hidden="true"></i> GCash</span>
                                <span><i data-lucide="wallet-cards" aria-hidden="true"></i> Cashier</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex u-gap-10 dtc-enrollment-create-actions" >
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i data-lucide="send" class="mr-1"></i> Submit Enrollment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Info Panel --}}
    <div class="col-lg-4 dtc-enrollment-guide-column">
        <div class="card u-primary-gradient-btn dtc-enrollment-guide-card"
             >
            <div class="card-body p-4">
                <h5 class="dtc-enrollment-guide-title">
                    <i data-lucide="info" class="mr-2"></i> After You Submit
                </h5>
                <p class="dtc-enrollment-guide-intro">Payment is verified before the Registrar reviews your subjects.</p>
                <div class="dtc-enrollment-guide-steps">
                   @foreach([
                    ['icon'=>'wallet', 'text'=>'Pay the ₱' . number_format($fee, 2) . ' fee at the Cashier or submit GCash proof.'],
                    ['icon'=>'badge-check', 'text'=>'The Cashier verifies your payment and issues a receipt.'],
                    ['icon'=>'scan-search', 'text'=>'The Registrar reviews your selected subjects and decides on your enrollment.'],
                ] as $step)
                    <div class="dtc-enrollment-guide-step">
                        <i data-lucide="{{ $step['icon'] }}" class="dtc-enrollment-guide-step-icon" aria-hidden="true"></i>
                        <span>{{ $step['text'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
function isIrregularMode() {
    return document.getElementById('type-irregular').checked;
}

function loadSubjects() {
    if (isIrregularMode()) {
        return loadAllSubjects();
    }

    const programId = document.getElementById('program_id').value;
    const yearLevel = document.getElementById('year_level').value;
    const semester  = document.getElementById('semester').value;
    const container = document.getElementById('subjects-container');

    if (!programId) return;

    container.innerHTML = `
        <div style="text-align:center; padding:20px; color:var(--dtc-text-muted);">
            <i data-lucide="loader" class="lucide-spin" style="width:2em;height:2em"></i>
            <p style="margin-top:8px; font-size:13px;">Loading subjects...</p>
        </div>`;

    window.dtcFetch(`/portal/programs/${programId}/subjects?year_level=${yearLevel}&semester=${semester}`)
        .then(res => res.json())
        .then(subjects => {
            if (subjects.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-warning" style="border-radius:12px; padding:16px; text-align:center;">
                        <i data-lucide="alert-triangle" style="margin-bottom:8px;"></i>
                        <p style="font-size:13px; margin:0;">
                            No subjects found for this selection.
                        </p>
                    </div>`;
                return;
            }

            container.innerHTML = `
                <div style="border:1.5px solid var(--dtc-border); border-radius:12px; overflow:hidden;">
                    <div style="background:var(--dtc-surface-soft); padding:10px 16px; font-size:11px;
                                font-weight:700; color:var(--dtc-text-secondary); text-transform:uppercase;
                                letter-spacing:0.5px; border-bottom:1px solid var(--dtc-border);">
                        ${subjects.length} Subject${subjects.length > 1 ? 's' : ''} Available
                    </div>
                    ${subjects.map(s => `
                        <label style="display:flex; align-items:center; gap:14px;
                                      padding:14px 16px; border-bottom:1px solid var(--dtc-border-soft);
                                      cursor:pointer; transition:background 0.2s; margin:0;"
                               onmouseover="this.style.background='var(--dtc-surface-soft)'"
                               onmouseout="this.style.background='transparent'">
                            <input type="checkbox" name="subject_ids[]" value="${s.id}"
                                   checked
                                   style="width:18px; height:18px; accent-color:var(--dtc-primary);
                                          cursor:pointer; flex-shrink:0;">
                            <div  class="u-flex-1">
                                <div  class="u-text-sm-bold-primary">
                                    ${s.subject_code}
                                </div>
                                <div  class="u-text-xxs-secondary">
                                    ${s.subject_name}
                                </div>
                            </div>
                        </label>
                    `).join('')}
                </div>`;
        })
        .catch(error => {
            window.dtcRequestFailure(error);
            container.innerHTML = `<div class="alert alert-danger">Failed to load subjects.</div>`;
        });
}

// ── Irregular mode: fetch ALL subjects for the program, grouped by
// year_level + semester, so students can select across groups in one
// enrollment (shiftees, transferees, catching up on missed subjects). ──

const YEAR_LABELS = { 1: '1st Year', 2: '2nd Year', 3: '3rd Year', 4: '4th Year' };
const SEM_LABELS  = { 1: '1st Semester', 2: '2nd Semester', 3: 'Summer' };

function loadAllSubjects() {
    const programId = document.getElementById('program_id').value;
    const container = document.getElementById('subjects-container');

    if (!programId) return;

    container.innerHTML = `
        <div style="text-align:center; padding:20px; color:var(--dtc-text-muted);">
            <i data-lucide="loader" class="lucide-spin" style="width:2em;height:2em"></i>
            <p style="margin-top:8px; font-size:13px;">Loading all subjects...</p>
        </div>`;

    window.dtcFetch(`/portal/programs/${programId}/subjects?mode=all`)
        .then(res => res.json())
        .then(groups => {
            if (groups.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-warning" style="border-radius:12px; padding:16px; text-align:center;">
                        <p style="font-size:13px; margin:0;">No subjects found for this program.</p>
                    </div>`;
                return;
            }

            container.innerHTML = groups.map(group => `
                <div style="border:1.5px solid var(--dtc-border); border-radius:12px; overflow:hidden; margin-bottom:14px;">
                    <div style="background:var(--dtc-surface-soft); padding:10px 16px; font-size:12px;
                                font-weight:700; color:var(--dtc-text); border-bottom:1px solid var(--dtc-border);">
                        ${YEAR_LABELS[group.year_level] || ('Year ' + group.year_level)} — ${SEM_LABELS[group.semester] || ('Semester ' + group.semester)}
                    </div>
                    ${group.subjects.map(s => `
                        <label style="display:flex; align-items:center; gap:14px;
                                      padding:14px 16px; border-bottom:1px solid var(--dtc-border-soft);
                                      cursor:pointer; transition:background 0.2s; margin:0;"
                               onmouseover="this.style.background='var(--dtc-surface-soft)'"
                               onmouseout="this.style.background='transparent'">
                            <input type="checkbox" name="subject_ids[]" value="${s.id}"
                                   style="width:18px; height:18px; accent-color:var(--dtc-primary);
                                          cursor:pointer; flex-shrink:0;">
                            <div  class="u-flex-1">
                                <div  class="u-text-sm-bold-primary">${s.subject_code}</div>
                                <div  class="u-text-xxs-secondary">${s.subject_name} · ${s.units} unit${s.units == 1 ? '' : 's'}</div>
                            </div>
                        </label>
                    `).join('')}
                </div>
            `).join('');
        })
        .catch(error => {
            window.dtcRequestFailure(error);
            container.innerHTML = `<div class="alert alert-danger">Failed to load subjects.</div>`;
        });
}

document.getElementById('program_id').addEventListener('change', loadSubjects);
document.getElementById('year_level').addEventListener('change', loadSubjects);
document.getElementById('semester').addEventListener('change', loadSubjects);
document.getElementById('type-regular').addEventListener('change', function () {
    document.getElementById('subjects-hint').textContent = 'All subjects for the selected year and semester are selected. Uncheck any you will not take.';
    loadSubjects();
});
document.getElementById('type-irregular').addEventListener('change', function () {
    document.getElementById('subjects-hint').textContent = 'Showing all subjects grouped by year and semester.';
    loadSubjects();
});

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('subjects-hint').textContent = isIrregularMode()
        ? 'Showing all subjects grouped by year and semester.'
        : 'All subjects for the selected year and semester are selected. Uncheck any you will not take.';
    if (document.getElementById('program_id').value) loadSubjects();
});
</script>
@endsection
