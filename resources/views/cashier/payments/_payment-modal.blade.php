{{-- Shared "Process Payment" modal.
     Include this once per page anywhere a `.dtc-payment-btn` (with a
     `data-url` pointing at the enrollment's cashier payment show route)
     may appear — e.g. the cashier payments index table. --}}
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" aria-hidden="true"@if(isset($reviewEnrollment) && $reviewEnrollment) data-auto-review-url="{{ route('cashier.payments.show', $reviewEnrollment) }}"@endif>
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content dtc-review-modal-content" id="paymentModalContent">
            <div class="dtc-review-loading">
                <div class="dtc-spinner u-spinner-sm" ></div>
                <p>Loading payment details…</p>
            </div>
        </div>
    </div>
</div>

@push('js')
<script src="{{ asset('js/cashier-payment-actions.js') }}"></script>
<script>
(function () {
    var $modal        = $('#paymentModal');
    var $modalContent = $('#paymentModalContent');

    var loadingMarkup = '\
        <div class="dtc-review-loading">\
            <div class="dtc-spinner u-spinner-sm" ></div>\
            <p>Loading payment details...</p>\
        </div>';

    var errorMarkup = '\
        <div class="dtc-review-loading">\
            <i data-lucide="alert-triangle" class="u-danger-lg"></i>\
            <p class="text-danger mb-0">Couldn\'t load this payment. Please try again.</p>\
        </div>';

    // Open modal + lazy-load its content from the existing "show" route.
    function openPayment(url) {
        $modalContent.html(loadingMarkup);
        $modal.modal('show');

        $.get(url)
            .done(function (html) {
                var content = $('<div>').html(html).find('#payment-content').html();
                $modalContent.html(content || errorMarkup);
            })
            .fail(function (xhr) {
                window.dtcHttpFailure(xhr.status);
                $modalContent.html(errorMarkup);
            });
    }

    $(document).on('click', '.dtc-payment-btn', function () {
        openPayment($(this).data('url'));
    });

    var autoReviewUrl = $modal.data('auto-review-url');
    if (autoReviewUrl) {
        $(function () { openPayment(autoReviewUrl); });
    }

    // Reset to the loading state once the modal has fully closed, so the
    // next enrollment doesn't briefly flash the previous one's details.
    $modal.on('hidden.bs.modal', function () {
        $modalContent.html(loadingMarkup);
    });

})();
</script>
@endpush
