# Personal Payment History exports

Download PDF and Export CSV now appear on the existing student/alumni `/portal/transactions` page. The existing search, status tabs, advanced filters, summary cards, responsive table, transaction details, and individual receipt links remain.

## Retrieval and authorization

- Routes: `portal.transactions.export.pdf` (`/portal/transactions/export/pdf`) and `portal.transactions.export.csv` (`/portal/transactions/export/csv`). They use the existing portal authentication middleware plus `role:student|alumni`. New applicants and staff-only accounts cannot download them.
- Both controller actions also check the portal role. They derive the owner ID from the authenticated account and pass the portal role to `TransactionHistory::query`. Request-supplied user IDs or role names cannot widen scope, including when a student/alumni also holds a staff role.
- The page and both exports share one filter validation method. Search, category, normalized payment method, status, and Manila recorded-date boundaries all apply. Role-specific categories remain: students may include enrollment, standalone documents, and appointment payments; alumni may include only documents/appointments.
- Export links contain only validated history filters. Page/per-page values never limit the export query. Deterministic ordering is the same as the screen, including transaction-key tie breaking.
- Responses are private/no-store. CSV additionally uses `nosniff`. Generated filenames use a fixed prefix and a Manila timestamp. Neither export exposes other payers, emails, proof paths, or processing credentials.

## Financial meaning and identity

Both exports reuse `TransactionHistory`, which already delegates document mirror detection to `DocumentPaymentIdentity`. Explicit bundle payments appear once; mirrored child document rows are excluded. A legacy duplicate requires matching appointment, amount, normalized method, and nonempty matching receipt/reference. Separate processing/document charges remain distinct. No payment records are mutated or backfilled.

Listed amounts are not presented as total paid. Each row includes its actual status plus a separate verified-collection amount; pending, unpaid, and rejected entries have zero verified collection. Verification dates are shown only for verified entries, since some legacy rejection workflows also write the timestamp column. Confirmed refunds are separate amounts requiring the existing confirmed state/date. Original verified collections remain visible after refunds.

The PDF summaries reflect the exact export filters, including the selected status. Existing screen cards continue their established behavior across status tabs. Date filters keep the history page's recorded/submitted-date meaning, not the staff reporting module's collection/refund event dates. Open-ended ranges are supported and labeled accordingly.

## Formats and limits

`PersonalPaymentHistoryExport` centralizes row presentation, column order, currency formatting, and CSV safety. DECIMAL amounts convert through integer centavos; generated monetary values never pass through floating-point aggregation.

PDF uses existing Dompdf and institution report styling. It includes **Personal Payment History**, account holder, selected recorded-date range and filters, Manila generation timestamp, verified/pending/refund summaries, full transaction details, and refund information. It explicitly states that it is not an official receipt. Individual receipt endpoints and eligibility rules are unchanged.

Dompdf retains the document in memory. Following the existing reporting convention, PDF supports up to 2,000 filtered records. Larger histories return to the same page with a notice to narrow filters or use CSV. No records are silently truncated. Query counts and bounded PDF reads run in a read transaction.

CSV streams every matching record in 500-row lazy batches within a read transaction, using the existing MySQL/MariaDB repeatable-read configuration. It uses UTF-8 BOM, consistent quoted columns, explicit PHP amount columns, and the existing formula-injection protection for all text fields, including whitespace/control-prefixed formulas. Numeric values come solely from formatted database DECIMALs. It includes transaction date/key, receipt/reference, description, type, method, listed amount, status, verification date, verified collection, and refund details. Separate requests reflect current stored state rather than an archived snapshot.

## Files

Added:

- `app/Services/PersonalPaymentHistoryExport.php`
- `resources/views/transactions/history-pdf.blade.php`
- `tests/Feature/PersonalPaymentHistoryExportTest.php`
- `docs/personal-payment-history-exports.md`

Updated:

- `app/Http/Controllers/TransactionHistoryController.php` — shared validation and authorized export actions.
- `app/Services/TransactionHistory.php` — optional exact-status summaries for exports; default screen behavior preserved.
- `resources/views/transactions/index.blade.php` — export controls and large-PDF notice on the existing page.
- `public/css/payment-history.css` — compact, responsive export controls.
- `routes/web.php` — two role-protected download routes.

No migrations, new dependencies, payment workflow changes, commits, or pushes.

## Validation

The ten new automated tests cover student/alumni ownership with injected owner/role parameters and secondary staff roles, unauthorized downloads, shared filters and pagination, Manila/UTC boundaries and open ranges, bundle/legacy deduplication, preserved separate charges, decimal values and status semantics, CSV quoting/formula safety/UTF-8, real populated and empty PDFs, receipt eligibility remaining separate, invalid filters, 502-row batching, and the PDF size guard.

Targeted regression command:

```text
php vendor/phpunit/phpunit/phpunit --filter='PersonalPaymentHistoryExportTest|PortalPaymentHistoryEnhancementsTest|TransactionHistoryTest|PaymentReportsTest|EnrollmentRefundReconciliationTest|PaymentHistorySidebarTest|CashierPaymentFlowTest|DocumentRequestPaymentTrackingTest' --no-coverage
```

Result: **95 tests, 890 assertions passed**, using the isolated `dtc_testing` database. No production payment data was changed.

Browser fixtures pass 32 existing-page checks across student/alumni lists and details, four widths, and both themes. The existing filter toggle, summaries, table, detail receipt navigation, and page overflow checks remain valid with the added controls. Populated personal-history PDFs and existing individual receipt PDFs were generated for both roles. Preview artifacts are ignored under `storage/framework/testing/unslop/` (`personal-history-*.pdf`, `history-*.png`, `history-enhancement-measurements.json`). External fonts are blocked in previews and fall back to system fonts.

Blade compilation, PHP formatting, and whitespace checks pass.
