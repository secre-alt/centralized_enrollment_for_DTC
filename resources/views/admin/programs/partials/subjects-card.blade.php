{{-- ============ SUBJECTS CARD (partial) ============
     Rendered both on full page load (index.blade.php) and via AJAX
     fetch when a program is selected (see #js-select-program handler).
     Keep this file self-contained: everything needed to display and
     manage subjects for $selectedProgram lives here so the fetch
     response can replace #subjects-card-wrapper's innerHTML wholesale. --}}
{{-- Header sits outside the card — same pattern as the page-level
     "Programs & Subjects" / "Add Program" header above the All Programs
     card. --}}
<div class="d-flex justify-content-between align-items-center flex-wrap dtc-subjects-header mb-3 u-gap-10" >
    <div>
        <h4 class="mb-0 font-weight-bold dtc-subjects-title u-text" >
            Subjects
            @if($selectedProgram)
                — {{ $selectedProgram->name }} ({{ $selectedProgram->code }})
            @endif
        </h4>
        @if(!$selectedProgram)
            <p class="mb-0 u-text-secondary-sm" >
                Select a program above to manage its subjects.
            </p>
        @endif
    </div>
    @if($selectedProgram)
    <button type="button" class="dtc-btn dtc-btn-primary dtc-header-btn" data-toggle="modal" data-target="#addSubjectModal">
        <i class="fas fa-plus"></i>
        <span class="dtc-header-btn-label">Add Subject</span>
    </button>
    @endif
</div>

<style>
/* Desktop/tablet: single line, shrinks with clamp(), truncates if truly
   too long. Mobile: instead of truncating a long program name into an
   unreadable fragment, let it wrap onto 2 lines and stack the button
   below it (same placement behavior requested earlier on mobile). */
.dtc-subjects-title {
    font-size: clamp(15px, 4.2vw, 22px);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (max-width: 575.98px) {
    .dtc-subjects-header {
        flex-direction: column;
        align-items: flex-start !important;
    }
    .dtc-subjects-title {
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
        font-size: 18px;
        line-height: 1.3;
    }
}
</style>

<div class="card mb-0">
    @if($selectedProgram)
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Subject Name</th>
                        <th>Year</th>
                        <th>Semester</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjects as $subject)
                    <tr>
                        <td>
                            <span class="dtc-status-badge is-neutral">{{ $subject->subject_code }}</span>
                        </td>
                        <td  class="u-text-sm-secondary-primary">
                            {{ $subject->subject_name }}
                        </td>
                        <td  class="u-text-xxs-secondary">
                            {{ $subject->year_level }}{{ ['','st','nd','rd','th'][$subject->year_level] ?? 'th' }} Year
                        </td>
                        <td  class="u-text-xxs-secondary">
                            {{ $subject->semester }}{{ $subject->semester == 1 ? 'st' : 'nd' }} Sem
                        </td>
                        <td>
                            <div  class="u-actions-gap">
                                <button type="button"
                                        class="dtc-icon-btn js-edit-subject"
                                        title="Edit subject"
                                        data-url="{{ route('admin.programs.subjects.update', $subject) }}"
                                        data-subject-code="{{ $subject->subject_code }}"
                                        data-subject-name="{{ $subject->subject_name }}"
                                        data-year-level="{{ $subject->year_level }}"
                                        data-semester="{{ $subject->semester }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST"
                                      action="{{ route('admin.programs.subjects.destroy', $subject) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="dtc-icon-btn danger"
                                            title="Remove subject"
                                            onclick="return confirm('Remove this subject?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 u-text-muted" >
                            No subjects added yet for this program.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap u-gap-15" >
        <small  class="u-text-secondary">
            Showing
            <strong>{{ $subjects->firstItem() ?? 0 }}</strong>
            to
            <strong>{{ $subjects->lastItem() ?? 0 }}</strong>
            of
            <strong>{{ $subjects->total() }}</strong>
            subjects
        </small>

        @if($subjects->hasPages())
        <div>
            {{ $subjects->appends(request()->except('subjects_page'))->onEachSide(1)->links('pagination::bootstrap-4') }}
        </div>
        @endif
    </div>
    @else
    <div class="text-center py-5 u-text-muted" >
        <i class="fas fa-book" style="font-size:22px; opacity:.5;"></i>
        <p class="mb-0 mt-2 u-text-sm" >No program selected yet.</p>
    </div>
    @endif
</div>

{{-- ============ ADD SUBJECT MODAL ============ --}}
@if($selectedProgram)
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST"
                  action="{{ route('admin.programs.subjects.store', $selectedProgram) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title u-text" >
                        <i class="fas fa-plus-circle mr-2" style="color:var(--dtc-primary);"></i>
                        Add Subject — {{ $selectedProgram->code }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Subject Code</label>
                        <input type="text" name="subject_code" class="form-control"
                               placeholder="e.g. IS101" required>
                    </div>
                    <div class="form-group">
                        <label>Subject Name</label>
                        <input type="text" name="subject_name" class="form-control"
                               placeholder="e.g. Introduction to Computing" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Year Level</label>
                                <select name="year_level" class="form-control" required>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label>Semester</label>
                                <select name="semester" class="form-control" required>
                                    <option value="1">1st Sem</option>
                                    <option value="2">2nd Sem</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="dtc-btn dtc-btn-primary">
                        <i class="fas fa-plus"></i> Add Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============ EDIT SUBJECT MODAL ============
     One shared modal, repopulated by JS from the clicked row's
     data-* attributes (no extra fetch needed — the data's already
     on the page). Form action is swapped per-click too. --}}
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" id="edit-subject-form" action="">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title u-text" >
                        <i class="fas fa-edit mr-2" style="color:var(--dtc-primary);"></i>
                        Edit Subject
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Subject Code</label>
                        <input type="text" name="subject_code" id="edit-subject-code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Subject Name</label>
                        <input type="text" name="subject_name" id="edit-subject-name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Year Level</label>
                                <select name="year_level" id="edit-subject-year" class="form-control" required>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label>Semester</label>
                                <select name="semester" id="edit-subject-semester" class="form-control" required>
                                    <option value="1">1st Sem</option>
                                    <option value="2">2nd Sem</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="dtc-btn dtc-btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
// Re-bind whenever this partial is (re)inserted — see loadSubjectsCard() in index.blade.php
(function initSubjectsCardScripts() {
    document.querySelectorAll('.js-edit-subject').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit-subject-form').action = this.dataset.url;
            document.getElementById('edit-subject-code').value = this.dataset.subjectCode;
            document.getElementById('edit-subject-name').value = this.dataset.subjectName;
            document.getElementById('edit-subject-year').value = this.dataset.yearLevel;
            document.getElementById('edit-subject-semester').value = this.dataset.semester;
            $('#editSubjectModal').modal('show');
        });
    });
})();
</script>