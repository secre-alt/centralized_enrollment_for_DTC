# Payment reports

The report is available at `/reports/payments` to Admin and Cashier accounts. Both roles have a Payment Reports sidebar link. The admin dashboard links to the page; the old `/admin/reports/payment` URL redirects to it and retains validated filters. Enrollment reports are unchanged.

## Audit and authoritative records

The implementation was based on the existing migrations, models, cashier verification/refund controllers, registrar legacy appointment verification, and `DocumentAppointmentBookingService`.

| Source | Collection eligibility | Amount | Collection date | Reference and staff |
| --- | --- | --- | --- | --- |
| `payments` joined to enrollment and payer | `status = verified`, positive amount | `amount` | `verified_at` | Receipt, then reference, then E-ID; verifier, then processor |
| `appointment_payments` joined to appointment and payer | `status = verified`, positive amount | `amount` | `verified_at` | Receipt, then reference, then A-ID; verifier |
| `document_requests` joined to payer | `payment_status = verified`, `is_paid = true`, positive fee and received amount, not a duplicate | `amount_received` | `verified_at` | Receipt, then reference, then D-ID; verifier |
| Enrollment refund in `payments` | `status = verified`, `refund_status = refunded`, positive refund amount | `refund_amount` | `refunded_at` | `refund_reference`; refund confirmer |

Existing methods `walk_in` and `gcash` map to Cash and GCash. Document `gcash_qr` also maps to GCash. Appointment payments, including legacy appointment processing fees, belong to the Documents report category.

Bundle verification writes the authoritative amount to `appointment_payments` with `is_document_bundle = true` and mirrors paid/verified information into each child document request. Child documents are excluded whenever that appointment has a bundle payment. The bundle's status and verification timestamp determine collection eligibility; child records cannot bypass them.

Legacy appointment processing fees can be separate from document fees. A legacy child document is suppressed only when a verified payment on the same appointment has the same amount, normalized method, and nonempty matching receipt or reference. References alone across unrelated appointments are not used for deduplication. Records without reliable matching identity remain separate; no fuzzy matching or historical data mutation is performed.

Booking/enrollment cancellation, rejection after payment, and document release do not erase verified financial events. Refund processing remains the existing cashier workflow. No document/appointment refund state or table exists, and none was invented.

## Reporting rules

- Default period: This Month. Presets use Manila calendar dates; Last 7 Days means today plus the preceding six days. This Month and This Year cover their complete calendar periods, while rolling periods end today. The exact resolved dates always appear.
- Custom dates use strict `Y-m-d` validation, ordered endpoints, and supported years 2000–2100. Queries include the start midnight and exclude midnight after the final date.
- The application currently writes timestamps using `config('app.timezone') = Asia/Manila`. Boundaries and display explicitly use Manila; boundary conversion also supports UTC storage configuration. This does not convert or rewrite historical database values. A future storage timezone change must migrate historical timestamps consistently.
- Only positive verified collection events enter gross totals and the verified transaction count. Zero-fee requests do not count as monetary transactions.
- Confirmed refunds are separate events dated by confirmation, even if collection happened in another period. Net is gross minus confirmed refunds and may be negative. Refund rows carry negative displayed/exported amounts.
- A refunded payment still counts as one verified transaction in its original collection period. The refund event does not add another verified transaction. Table event counts can therefore exceed the verified transaction count.
- Required missing timestamps are never replaced with creation/submission dates. The dashboard/PDF warn about undated eligible events across the selected type/method, outside any date restriction. Legacy paid document records with no received amount are also excluded rather than inferring a monetary amount from a current fee.
- DECIMAL database amounts become integer centavos before aggregation. PHP totals, formatting, and CSV numeric values use integers and strings. Floating point conversion is confined to chart plotting.

## Architecture, exports, and security

`PaymentReportRequest` validates all page/export filters and authorizes Admin/Cashier. Auth and role middleware also protect all three routes. Reports expose payer name, payment reference, dates, method, type, amount, and staff name; they do not expose emails, proof paths, or account information. Responses are private and not cached.

`PaymentReportFilters` resolves the date range. `PaymentReportService` provides the common read-only event query, summary, ordered rows, zero-filled chart buckets, and money/date/CSV helpers. The dashboard paginates 25 events; all filters persist in URLs and export links. The chart uses the existing Chart.js 3.9.1 plugin and theme helpers. Periods through 62 days use daily buckets; longer periods use monthly buckets. An accessible data table remains available when the existing CDN cannot load.

Dashboard reads and PDF dataset reads run within database transactions. CSV uses a read transaction around its bounded 500-row lazy batches to keep pagination stable under the existing MySQL/MariaDB repeatable-read isolation. Separate report requests reflect records as of their own reads; exports are not archived financial snapshots.

PDF uses the existing Dompdf dependency, institution letterhead, exact dates, selected filters, totals, event details, missing-date notice, and Manila generation timestamp. Dompdf retains a complete document in memory, so PDF is limited to 2,000 events. Larger requests redirect to the filtered page with guidance to narrow the range or use CSV; no rows are silently truncated.

CSV streams all matching events, uses UTF-8 BOM and standard CSV quoting, and neutralizes formula prefixes in untrusted text cells, including whitespace/control-prefixed formulas. Refund amounts are intentionally negative numeric values generated from trusted centavos. Filenames include the resolved dates. No migrations, packages, payment-state changes, or payment-processing changes are required.

At substantially larger database volumes, profile this union/anti-join query and add targeted indexes based on EXPLAIN and observed load. No speculative schema migration was added.

## Changed files

New:

- `app/Http/Controllers/PaymentReportController.php`
- `app/Http/Requests/PaymentReportRequest.php`
- `app/Services/Reports/PaymentReportFilters.php`
- `app/Services/Reports/PaymentReportService.php`
- `resources/views/reports/payments/index.blade.php`
- `public/css/payment-reports.css`
- `public/js/payment-reports.js`
- `tests/Feature/PaymentReportsTest.php`
- `docs/payment-reports.md`

Updated for this module:

- `routes/web.php`
- `app/Http/Controllers/Admin/ReportController.php`
- `app/Providers/ViewServiceProvider.php` (report links only)
- `resources/views/admin/reports/payment.blade.php`
- `resources/views/admin/dashboard.blade.php` (payment report link only)
- `lang/en/dashboard.php` and `lang/fil/dashboard.php` (report link label)
- `tests/Feature/EnrollmentRefundReconciliationTest.php` (follow the new legacy-link redirect)

Other pre-existing workspace edits were preserved.

## Validation

- `PaymentReportsTest`: 19 tests covering presets/defaults, custom and Manila/UTC boundaries, statuses, exact decimal totals, cross-period and refund-only periods, bundle and legacy deduplication, free records, date omissions, method/type filtering, chart aggregation, role authorization on every endpoint, filter validation, PDF/CSV/dashboard consistency, formula protection, pagination, 503-event CSV batching, the PDF row limit, and the legacy report link.
- Targeted regression command: `php vendor/phpunit/phpunit/phpunit --filter='PaymentReportsTest|CashierPaymentFlowTest|EnrollmentRefundReconciliationTest|DocumentRequestPaymentTrackingTest|TransactionHistoryTest|SidebarNavigationTest|PaymentHistorySidebarTest' --no-coverage` — 81 tests, 844 assertions pass against the isolated `dtc_testing` database.
- Browser: 24 cases across 1536/1100/768/390px widths, light/dark themes, populated/daily, empty, and yearly/monthly reports. No horizontal page overflow or JavaScript exceptions; custom date controls and mobile scrolling footer work. Chart.js renders real data; blocking it exposes the fallback data table.
- A real populated PDF was generated through the export route. Blade compilation, JS syntax, PHP formatting, and whitespace checks pass.
- Ignored preview artifacts live in `storage/framework/testing/unslop/`: `payment-report-measurements.json`, `payment-report-*.png`, `payment-report.pdf`. Browser previews block external fonts and therefore use fallback fonts.

No commit or push was performed.

### Mobile report follow-up

Chart axes now explicitly skip labels based on canvas width, shorten axis dates while retaining full tooltip dates, and use fewer whole-peso amount ticks. The tick budget recalculates on resize. Below 768px the same transaction cells become labeled stacked rows, preserving every field and the desktop table. No reporting query or export logic changed.

Validation: 19 module tests (183 assertions), the existing 24 browser cases, and 10 additional full-month chart cases at 320/390/412/768/1536px in both themes pass. Additional checks measure adjacent date labels for overlap and verify all mobile transaction fields fit without horizontal page scrolling. Artifacts: `payment-report-mobile-measurements.json` and `payment-report-mobile-*.png` in the ignored preview folder.
