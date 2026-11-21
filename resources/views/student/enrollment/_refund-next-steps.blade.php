<section class="dtc-refund-next-steps" aria-label="Next steps after paid enrollment rejection">
    @if($refundFullyConfirmed)
        <h3>Next step: submit a new enrollment request</h3>
        <p>The Cashier recorded confirmation of the full refund for enrollment #{{ $enrollment->id }}. Review the Registrar’s reason before submitting again. Current term and eligibility checks still apply.</p>
        <a class="dtc-btn dtc-btn-primary" href="{{ route('portal.enrollment.create') }}">Start a new enrollment request</a>
    @else
        <h3>Next step: visit the Cashier’s Office</h3>
        <ol>
            <li>Bring your valid ID and show enrollment #{{ $enrollment->id }}, your payment receipt or GCash transaction reference, and the Registrar’s rejection reason.</li>
            <li>Ask the Cashier to reconcile the paid enrollment and arrange the full refund. GCash refunds are handled outside this portal.</li>
            <li>After the refund is completed, the Cashier must record its reference and confirmation here. Return to this page to check the status before submitting a new request or paying again.</li>
        </ol>
        <p><strong>Refund not yet confirmed.</strong> Rejection does not mean the payment has been refunded. Do not pay again while reconciliation is pending.</p>
        <p>If the rejection was caused by full or unavailable subjects, contact the Registrar before arranging a refund. The Registrar can check with the Cashier whether this enrollment can be returned for subject replacement without another payment. You cannot reopen it yourself.</p>
    @endif
</section>
