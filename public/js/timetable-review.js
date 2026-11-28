document.addEventListener('DOMContentLoaded', function () {
    const programFields = document.querySelectorAll('[data-timetable-program]');
    const sampleLink = document.getElementById('tt-sample-csv');
    const sampleProgram = document.getElementById('tt-import-program');
    const sampleYear = document.getElementById('tt-import-year');
    function updateSampleLink() {
        if (!sampleLink || !sampleProgram?.value || !sampleYear?.value) return;
        const params = new URLSearchParams({ program_id: sampleProgram.value, year_level: sampleYear.value });
        sampleLink.href = sampleLink.dataset.url + '?' + params.toString();
    }
    [sampleProgram, sampleYear].forEach(field => field?.addEventListener('change', updateSampleLink));
    updateSampleLink();
    programFields.forEach(field => field.addEventListener('change', function () {
        field.dataset.timetableEdited = '1';
        if (field === sampleProgram) updateSampleLink();
    }));
    document.addEventListener('dtc:program-selected', function (event) {
        programFields.forEach(field => {
            if (!field.dataset.timetableEdited) field.value = event.detail || field.options[0]?.value || '';
        });
        updateSampleLink();
        checkImportPreview();
    });
    const importForm = document.getElementById('tt-import-form');
    const importSave = document.getElementById('tt-import-save');
    const importSnapshot = importForm && importSave ? new URLSearchParams(new FormData(importForm)).toString() : null;
    let fileChanged = false;
    function checkImportPreview(event) {
        if (importSnapshot === null) return true;
        if (event?.target?.type === 'file') fileChanged = true;
        const stale = fileChanged || new URLSearchParams(new FormData(importForm)).toString() !== importSnapshot;
        document.getElementById('tt-import-stale').hidden = !stale;
        importSave.querySelector('button[type="submit"]').disabled = stale;
        return !stale;
    }
    if (importForm && importSave) {
        importForm.addEventListener('input', checkImportPreview);
        importForm.addEventListener('change', checkImportPreview);
        importSave.addEventListener('submit', function (event) {
            if (!checkImportPreview()) event.preventDefault();
        });
        checkImportPreview();
    }
    const editor = document.getElementById('tt-edit-form');
    const publication = document.querySelector('.tt-publish[data-ready]');
    if (!editor || !publication) return;

    const initial = new URLSearchParams(new FormData(editor)).toString();
    const restored = !!editor.querySelector('[data-restored-edits]');
    function update() {
        const dirty = restored || new URLSearchParams(new FormData(editor)).toString() !== initial;
        const disabled = dirty || publication.dataset.ready !== '1';
        document.getElementById('tt-unsaved').hidden = !dirty;
        publication.querySelectorAll('input[name="reviewed"], button[type="submit"]').forEach(control => {
            control.disabled = disabled;
        });
        if (dirty) publication.querySelector('input[name="reviewed"]').checked = false;
        return !disabled;
    }
    editor.addEventListener('input', update);
    editor.addEventListener('change', update);
    publication.addEventListener('submit', function (event) {
        if (!update()) event.preventDefault();
    });
    // Restored validation input must be saved before the saved version is published.
    update();
});
