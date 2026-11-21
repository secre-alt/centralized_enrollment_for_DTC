document.addEventListener('DOMContentLoaded', function () {
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
