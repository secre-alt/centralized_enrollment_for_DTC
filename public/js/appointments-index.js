/* appointments/index.blade.php — extracted scripts */

// Re-render lucide icons inside modals when they open
$(document).on('shown.bs.modal', '.modal', function () {
    if (window.lucide) lucide.createIcons();
});

// Update filename label when a file is chosen
function dtcFileChange(input) {
    var nameEl = input.closest('.dtc-file-upload').querySelector('.dtc-file-name');
    if (input.files && input.files.length) {
        nameEl.textContent = input.files[0].name;
        nameEl.classList.add('has-file');
    } else {
        nameEl.textContent = 'No file chosen';
        nameEl.classList.remove('has-file');
    }
}
