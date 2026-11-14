# Toast notification polish

Approved scope: all shared flash toasts on public/authenticated layouts and incoming portal notification banners. Direction follows DTC's neutral surfaces, concise messages, small status icons, 12px corners, 16px padding and bold dismiss X.

## Changes

- `public/css/dtc-theme.css`: quiet border/shadow, consistent typography, compact semantic icons, accessible dismiss controls, subtle countdown and short opacity/translation motion. Dark mode has no shadow. Reduced motion retains a minimal animation because dismissal cleanup depends on `animationend`.
- `public/css/notifications.css`: matching incoming banner surface, width, typography, dismissal and focus treatment. Incoming updates sit at the bottom; flash results stay at the top, preventing the two systems from sharing the same screen area under normal stack sizes.
- `public/js/notification-toasts.js`: replaces the text Dismiss action with a labeled bold X. Notification messages, grouping, safe links, timers, focus/hover/visibility pauses, unread state and replay protection are preserved.
- `tests/browser/toasts.cjs`: runs the actual toast scripts and styles in disposable local browser fixtures. Covers four flash variants plus incoming updates in both themes at 320px and 768px.

## Validation

Payment review notification follow-up (2026-11-14): stored `/cashier/payments/{enrollment}` notification links now open the existing payment list with `review={enrollment}`, which automatically loads that enrollment into the shared review modal. Admin/Cashier access is required. Missing enrollments fall back to the list; other roles fall back to notification history. The list validates the review ID and omits it from pagination links. Existing standalone detail routes remain available to the modal loader and other callers. No stored notifications or payment records are rewritten and no migration is needed.

17 existing JavaScript tests passed: notifications, polling, incoming toasts and session feedback. Browser fixtures check widths, dismissal labels/touch size, surfaces and separated stacks. A preview is generated under ignored `storage/framework/testing/toast-polish/` and was visually inspected. These fixtures do not replace manual interaction with every application workflow.

No backend, financial workflow, notification persistence, migration or dependency changes.

Process credit: unslop by Damir Sokolovsky, [heydamir.design/unslop](https://heydamir.design/unslop), CC BY 4.0.
