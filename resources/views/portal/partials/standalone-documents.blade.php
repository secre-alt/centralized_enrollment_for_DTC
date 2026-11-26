@if ($standaloneRequests->isNotEmpty())
    <section class="card card-body" aria-label="Document requests">
        <h2 class="h4">Document requests</h2>
        <p>Submission and claiming appointments are not required. Collect ready, payment-satisfied documents at the Registrar during published office hours with a valid ID.</p>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Reference</th><th>Document</th><th>Copies</th><th>Status</th><th>Payment</th><th>Details</th></tr></thead>
                <tbody>
                @foreach ($standaloneRequests as $document)
                    <tr>
                        <td>D-{{ $document->id }}</td><td>{{ $document->document_label }}</td><td>{{ $document->copies }}</td>
                        <td>{{ ['submitted' => 'Submitted', 'processing' => 'Processing', 'ready' => 'Ready for Pickup', 'released' => 'Released', 'cancelled' => 'Cancelled'][$document->status] ?? $document->status }}@if ($document->released_at)<div>Physically released {{ $document->released_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} by {{ $document->releaser?->name ?? 'Recorded staff' }}</div>@endif</td>
                        <td>{{ $document->paymentNotRequired() ? 'No Payment Required' : ucwords(str_replace('_', ' ', $document->payment_status)) }} · PHP {{ number_format($document->fee, 2) }}</td>
                        <td><a href="{{ route('portal.payments.document', $document) }}">Payment details</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
