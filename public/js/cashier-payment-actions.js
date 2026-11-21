// Delegated controls support both the payment dialog and the direct review page.
document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.dtc-review-reject-trigger, .dtc-review-reject-cancel');
    if (!trigger) return;
    const content = trigger.closest('#paymentModalContent, #payment-content');
    const footer = trigger.closest('.dtc-review-footer');
    if (!content || !footer) return;

    const opening = trigger.classList.contains('dtc-review-reject-trigger');
    const panel = opening ? document.getElementById(trigger.dataset.target) : content.querySelector('.dtc-review-reject-panel');
    if (!panel || !content.contains(panel)) return;
    panel.classList.toggle('is-open', opening);
    panel.style.display = opening ? 'block' : 'none';
    footer.querySelector('.dtc-review-footer-default').style.display = opening ? 'none' : '';
    footer.querySelector('.dtc-review-footer-reject').style.display = opening ? 'flex' : 'none';
    if (opening) {
        panel.querySelector('textarea')?.focus();
    } else {
        footer.querySelector('.dtc-review-reject-trigger')?.focus();
    }
});
