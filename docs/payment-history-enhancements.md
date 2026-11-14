# Student and alumni Payment History enhancements

The existing `/portal/transactions` list and `/portal/transactions/{transaction}` detail page remain in place. Search, status tabs, expandable Filters, pagination, responsive table, and detail navigation are preserved. No separate history page, migration, dependency, verification flow, or refund workflow was introduced.

## Audit and changes

`TransactionHistory` already normalized enrollment payments, document requests, and appointment payments, but it included both bundle payments and their mirrored child documents. Its existing ownership predicates were correct and continue to apply to every portal request, including accounts with secondary staff roles.

The existing Filters panel already contained dates, category, and method inputs. These controls are reused. Categories now reflect account scope: students have Enrollment, Document request, and Appointment / bundled documents; alumni have only the last two. Method choices are compact Cash/GCash groups covering the underlying `walk_in`/`cash` and `gcash`/`gcash_qr` variants. Older explicit method filter values remain accepted. Search additionally matches recorded official receipt numbers. End-date-only searches are supported, and date boundaries explicitly use Manila days while retaining the existing recorded/submitted-date meaning.

Three compact cards show verified payment amounts, pending transaction counts (unpaid or awaiting verification), and confirmed refund amounts. They follow search/type/method/date filters across all statuses, so switching status tabs does not redefine the cards. Monetary aggregation uses integer centavos from stored DECIMAL amounts. Verified totals include collections later refunded; refunds are separately displayed, not silently subtracted. Date filters select payment records by their recorded/submitted date, not refund confirmation date. The page explains this distinction; the staff Payment Reports module remains the period-based collections/refunds report.

Document totals use actual `amount_received` and require the paid flag and positive fee. Missing received amounts are not estimated from fees. Confirmed refund totals require verified payment status, `refund_status = refunded`, a positive amount, and `refunded_at`. Existing refund status, reference, amount, and confirmation-date details are retained.

## Identity and deduplication

`DocumentPaymentIdentity` now holds the rule shared by Payment Reports and Payment History. Bundle children are suppressed whenever the appointment has an explicitly marked bundle payment. That appointment payment supplies the single history row, status, amount, and receipt. Bundle rows identify themselves as document bundles instead of implying that the first document is the whole charge.

Legacy appointment processing fees may be separate charges. A legacy document mirror is suppressed only when a verified appointment payment has the same appointment, normalized method, amount, and nonempty matching receipt/reference. Separate charges remain visible; no global reference deduplication or fuzzy matching is performed. Ambiguous legacy data remains separate rather than guessing that money was collected only once.

Paid, verified document records remain visible after cancellation, so cancellation cannot erase a collection. Unpaid cancelled standalone requests continue to follow the existing exclusion. Duplicate child detail and receipt URLs return 404 rather than issuing duplicate receipts.

Pending bundle payments are normalized before filtering: Cash/Walk-in appears under Unpaid, and GCash appears under Pending verification. Verified and rejected bundle statuses and legacy appointment payment statuses are preserved. This changes the read-only history projection, not stored payment records. Bundle descriptions explicitly read `Document bundle — Appointment #…` throughout the list, detail page, receipt, and history exports, rather than attributing the full charge to the first document.

## Receipts and access

The new `portal.transactions.receipt` route is `/portal/transactions/{transaction}/receipt`. Existing portal authentication/role middleware applies. The controller resolves the exact E-/D-/A- transaction through the same ownership-scoped history service; another user's key returns 404, even if the requesting portal account also has a cashier/admin role.

Eligibility requires verified status, positive actual amount, a recorded verification date, and a nonblank official receipt number. Document records also require paid status and positive fee through the normalized actual amount. Missing identifiers are not manufactured. Ineligible, free, pending, rejected, duplicate, and foreign records cannot download receipts.

The PDF reuses the existing Dompdf dependency and institution report styling. It is an electronic copy of the recorded official receipt and contains the existing receipt number, transaction, payer, payment description, method/reference, verified amount/date, and processing staff. Enrollment refund notices are included when applicable so the receipt does not imply that a refunded original collection is still retained. Downloads and history responses are private/no-store. Existing cashier receipt and payment-processing endpoints are unchanged.

## Files

- New: `app/Services/DocumentPaymentIdentity.php`, `resources/views/transactions/receipt.blade.php`, `tests/Feature/PortalPaymentHistoryEnhancementsTest.php`, this document.
- Updated: `app/Services/TransactionHistory.php`, `app/Http/Controllers/TransactionHistoryController.php`, `routes/web.php`, `resources/views/transactions/index.blade.php`, `resources/views/transactions/show.blade.php`, `public/css/payment-history.css`.
- `app/Services/Reports/PaymentReportService.php` now delegates its existing identity rule to the shared helper; its report rules are unchanged.
- Pre-existing workspace changes are preserved. No commit or push was performed.

## Validation

The follow-up bundle audit fixes pass 96 tests / 942 assertions using the command below with `PersonalPaymentHistoryExportTest` added to the filter. Regression assertions cover student/alumni bundle status tabs, single-count summaries, unchanged legacy statuses and descriptions, and bundle descriptions in the list, detail page, and receipt. Targeted Pint formatting, PHP syntax, and whitespace checks also pass.

85 tests / 778 assertions pass with:

```text
php vendor/phpunit/phpunit/phpunit --filter='PortalPaymentHistoryEnhancementsTest|TransactionHistoryTest|PaymentReportsTest|EnrollmentRefundReconciliationTest|PaymentHistorySidebarTest|CashierPaymentFlowTest|DocumentRequestPaymentTrackingTest' --no-coverage
```

The nine new tests cover summaries and exact centavos; role and ownership scope; all three receipt sources; ineligible/free/negative/missing-data receipts; refund disclosure; bundle and legacy identities; separate charges; pending bundles; paid cancelled documents; search/method/date/category filters; and authentication. Existing reporting and workflow tests guard the shared identity extraction and unchanged processing behavior.

Browser fixtures pass 32 cases: student/alumni list and detail pages, four widths (1536/768/390/320), both themes. Checks verify the existing Filters toggle, table presence, three summary cards, alumni category restrictions, receipt links, and no horizontal overflow. Actual student/alumni receipt PDFs were generated. Preview artifacts are ignored under `storage/framework/testing/unslop/history-*`; external fonts are blocked in this fixture environment, so screenshots use fallback fonts.

PHP formatting, Blade compilation, and whitespace checks pass. Historical payment records are not modified or backfilled; receipts missing required official data remain unavailable until the normal authorized process records that data.
