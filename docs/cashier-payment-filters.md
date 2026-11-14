# Cashier enrollment payment filters

The existing Process Payments filter panel supports program, academic year, year level, semester, payment method, and inclusive enrollment submission dates alongside status and rows per page. Academic choices come from recorded enrollments, including historical terms; program choices come from the program directory. Filters use each enrollment's own term rather than the currently configured term.

Dates select enrollment `created_at`, using the application's Asia/Manila storage convention. They do not select payment verification or refund dates. Payment method matches the latest payment attempt, as do the existing status choices. An enrollment with no payment record does not match a selected payment method. Filters combine with search before pagination and persist in pagination links. Clear all removes the filters. No records, payment state, permissions, migrations, or dependencies change.

Validation rejects invalid dates, reversed ranges, unsupported methods, unknown programs, and nonpositive year/semester values. An end date alone is supported. The focused feature check covers combinations, individual filters, date boundaries, end-only dates, invalid inputs, search and student access denial. Browser/manual role acceptance has not been performed for these additions.
