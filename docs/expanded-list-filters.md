# Expanded list filters

The existing five list pages retain their routes, actions and role checks. No migration, dependency or asset build is required.

| Page | Added filters | Date meaning |
| --- | --- | --- |
| Registrar enrollment review | Program, academic year, year level, semester, load classification, dates | Enrollment submission (`enrollments.created_at`) |
| Cashier document payments | Document type, payment method, dates | Standalone request creation or bundle payment creation |
| Admin audit logs | Actor, dates | Audit log creation |
| Registrar applications | Dates alongside existing type/program/status/search | Application submission (`applications.created_at`) |
| Alumni request history | Document type, search, dates | Document request creation |

Dates are inclusive Manila calendar days under the application's existing local timestamp storage convention. End-date-only filters work; malformed dates and reversed ranges fail validation. The shared `ListDateRange` uses the following midnight as an exclusive upper bound.

Enrollment options include recorded historical terms and use the enrollment's own values. Load classification selects the stored `is_irregular` flag (Regular/Irregular); it does not infer admission type or recalculate academic eligibility. The review queue retains its pending, paid and latest-payment-verified restrictions.

Document payment filters apply to both standalone requests and appointment bundles. A document-type match selects a bundle containing that type once, including its full existing payment amount. It does not allocate or split fees. Mirrored children stay excluded. Status counts respect search, method, document type and dates across status choices. The date labels say “Record created” because the two existing record types have different creation timestamps; these filters are not verification-date or refund-date reports.

Audit actor options come from users attached to audit logs. The audit export remains the existing separate Activity-log export; this change does not alter its scope or claim that it exports the filtered AuditLog list.

Alumni queries retain `user_id = Auth::id()` even with a secondary staff role. Existing stage buttons operate on the filtered collection. The overview and stage counts reflect that collection. The request-history page retains its current nonpaginated collection; staff lists preserve filters in pagination links. Application AJAX requests include both dates when searching, changing filters and paging. New search matches request ID, purpose, receipt or reference; document names are selected through the type filter.

## Layout and checks

All five forms use `dtc-fl-expanded`: equal flexible desktop tracks of at least 200px, 16px field gaps, 24px panel padding (16px on mobile), 44px controls, two columns on tablets and one column below 480px. Active fields keep the panels open; filter counts ignore default status values. Clear/Reset links remove filters and paging. Existing theme tokens and focus treatments remain.

`ExpandedListFiltersTest` covers combined queries, historical terms, queue eligibility, pagination links, date boundaries, end-only dates, bundle deduplication/counts, actor/search combinations, application AJAX responses, alumni ownership with a secondary role, invalid ranges and staff access denial. `tests/js/list-filters.test.cjs` covers live count updates and date preservation in application AJAX URLs. `tests/browser/list-filters.cjs` supplies 40 representative fixtures with actual CSS across five page families, two themes and four widths; fixture execution outcomes are reported separately from authenticated manual acceptance.

Authenticated manual checks remain outstanding: exercise Apply/Clear, both pagination controls on document payments, application AJAX navigation, alumni stage buttons, keyboard focus, long option labels, and light/dark layouts in real sessions. Automated fixture success alone does not establish manual acceptance.

Current verification: focused feature tests passed (6 tests, 54 assertions); both JavaScript checks passed; the 40 responsive filter fixtures passed after allowing the browser process to launch outside the restricted environment. PHP syntax, Blade compilation and whitespace checks passed. The initial restricted Chrome attempt timed out; no fixture pass is claimed from that attempt.

The full MySQL `dtc_testing` regression suite passed: 582 tests, 6,782 assertions, with no failures reported.
