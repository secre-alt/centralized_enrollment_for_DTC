# DTC EMS design system inventory

This is a source inventory, not a declaration that every screen follows the system. It was prepared during a partial baseline on 2026-11-28.

## Foundations found

| Area | Existing implementation |
|---|---|
| Main theme stylesheet | `public/css/dtc-theme.css` |
| Brand primary | `--dtc-primary: #0F4CDB` |
| Accent | `--dtc-accent: #FFC72C` |
| Light surfaces | `--dtc-bg: #F5F7FA`, `--dtc-surface: #FFFFFF`, `--dtc-surface-soft: #F8FAFC` |
| Text and borders | `--dtc-text: #1E293B`, secondary `#526174`, muted `#66758A`, border `#E2E8F0` |
| Semantic colors | success `#16A34A`, warning `#D97706`, danger `#DC2626` |
| Shape and effects | 8/10/12/16px radius tokens, two shadows, 160ms transition token |
| Typography | Poppins in DTC stylesheet/public layout; AdminLTE config also permits Source Sans Pro |
| Icons | Lucide `data-lucide`; Font Awesome assets remain installed with AdminLTE |
| Authenticated shell | `resources/views/vendor/adminlte/master.blade.php`, `adminlte::page` |
| Public shell | `resources/views/layouts/public.blade.php` |
| Theme state | `public/js/auth-theme.js`, `dtc-ems-theme` local storage key, `dtc-dark` class |
| Shared components | `resources/views/components/dtc/` (page header, stat card, status badge, empty state, toast, confirm modal, student welcome, avatar, password guidance) |

## Dark theme

Dark overrides are defined in `public/css/dtc-theme.css` under `body.dtc-dark`; `public/js/auth-theme.js` reads the saved preference or system color scheme. Public layout also preloads the dark class to reduce first-paint flashing. Browser verification was incomplete, so page-by-page token parity and contrast remain unknown.

## Architecture limits

The audit found multiple page-specific CSS files and direct style declarations in Blade. The source also loads typography and icon assets from external CDNs. This is inventory only: do not consolidate selectors or declare duplication harmful without a completed rendered audit and ownership review. Token usage across every role has not been measured.

## Adoption status

Unknown. A source token or reusable component being present does not establish that screens use it consistently. Review pages in the final audit and add examples and measured contrast values only after browser evidence is captured.
