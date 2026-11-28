# DTC EMS design system inventory

This inventory describes the current source architecture and the measured public-page results. It does not certify all portal roles.

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
| Typography | Poppins in public DTC UI; AdminLTE configuration also permits Source Sans Pro |
| Icons | Lucide `data-lucide`; Font Awesome assets remain installed with AdminLTE |
| Authenticated shell | `resources/views/vendor/adminlte/master.blade.php`, `adminlte::page` |
| Public shell | `resources/views/layouts/public.blade.php` and `resources/views/auth/welcome.blade.php` |
| Theme state | `public/js/auth-theme.js`, `dtc-ems-theme` storage key, `dtc-dark` class |
| Shared components | `resources/views/components/dtc/` (page header, stat card, status badge, empty state, toast, confirm modal, student welcome, avatar, password guidance) |

## Theme and rendered evidence

The dark theme is primarily implemented in `public/css/dtc-theme.css`, with public-specific selectors in `welcome.css`, `public-navbar.css`, and `signin-modal.css`. The 60-render public matrix (six routes, five viewports, light/dark) verified expected theme state and zero horizontal overflow. Axe contrast findings decreased from 48 repeated instances at baseline to zero after targeted changes in the sampled public matrix. This does not measure role layouts.

Poppins and Lucide load remotely in the public shell; Bootstrap CSS also loads from jsDelivr. The browser environment denied those requests, so screenshots use font fallback and may lack icons/framework styles. AdminLTE Source Sans Pro and Font Awesome appearance were not browser verified.

## Adoption status and architecture limits

Tokens/components exist, but source presence does not establish consistent use. Authenticated screens and role-specific theme overrides were not rendered, so cross-role token adoption and component consistency remain unmeasured. Page-specific styles remain in place; no broad consolidation was attempted without role coverage and ownership evidence.

## Evidence files

- Baseline: `docs/ui-ux-evidence/baseline/` (60 PNG screenshots and axe-enabled `results.json`).
- After targeted public fixes: `docs/ui-ux-evidence/after-final/` (60 PNG screenshots and axe node-level evidence).
- The carousel arrow stacking issue found at 768�1024 was corrected and the full matrix reports zero axe violations.

## Latest evidence

The 60 screenshots in docs/ui-ux-evidence/final/ reflect the corrected carousel stacking order. The 120 screenshots in baseline and intermediate evidence were retained.
