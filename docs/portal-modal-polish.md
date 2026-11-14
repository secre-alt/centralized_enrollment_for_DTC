# Portal modal polish

## Scope and direction

Applies to authenticated AdminLTE portals, including Student, applicant/alumni, Registrar, Cashier and Admin dialogs. The public sign-in and application layouts do not load the new stylesheet.

- Product: enrollment, academic review and payment dialogs for DTC staff and students.
- Character: calm, precise, approachable.
- World: Registrar records and service counters.
- Emphasis: neutral surfaces, blue primary actions and red destructive confirmations.
- Signature: left-aligned titles, 24px body padding (16px on small screens), 12px corners and 44px actions.

The user approved this direction for all dialogs inside the portals.

## Audit and treatment

- Shared Bootstrap rules, custom confirmations, review modals, user dialogs and profile dialogs had competing spacing and radius rules. A portal-only stylesheet now supplies the common frame without changing Bootstrap/modal lifecycle code.
- Review headers used strong blue panels and white text; confirmations and photo dialogs used oversized colored decorations and strong shadows. Headers now use the existing neutral theme tokens, icons are compact, and dark mode relies on borders rather than shadows.
- Body/footer padding and control heights varied. Shared spacing, button radii, minimum action sizes and visible keyboard focus now apply to the portal dialog families.
- Student welcome used celebratory uppercase text, an oversized emblem and confetti. Its heading is now “Enrollment approved,” with a compact emblem and no visible confetti. Acknowledgement, focus trapping, duplicate protection and redirects are unchanged.
- Status and destructive actions retain their meaning. Image cropping, actual avatars, payment details, receipts, field validation, asynchronous loading and review decisions are preserved.
- No generated imagery is appropriate for these task dialogs; no images or new dependencies were added. Existing reduced-motion preferences remain supported.

## Files

- `public/css/portal-modals.css`: shared portal dialog treatment.
- `resources/views/vendor/adminlte/page.blade.php`: loads the stylesheet after page styles and layout rules.
- `resources/views/components/dtc/student-welcome.blade.php`: concise welcome heading.
- `tests/browser/portal-modals.cjs`: representative local markup with actual stylesheets, six families, light/dark themes and 320/390/768px frames.

## Verification

### Narrow review dialog follow-up (2026-11-14)

Payment review, Registrar review and receipt identities now wrap long names/emails. At phone and tablet widths the dismiss control stays in the upper-right corner, while status badges sit below the identity. Below 400px, shared review facts use one column so program names and payment timestamps have sufficient space. Review sections use 24px separation, facts use 16px gaps, and footer labels may wrap rather than overflow. Payment instructions and the fee use neutral text; the payment review action uses DTC blue. No form actions or payment transitions changed.

The browser fixture now includes the real review-header structure, a long name/email, pending badge, fee and payment footer, with explicit identity/close-button checks. Current execution results must be distinguished from the historical verification below. Authenticated manual acceptance remains outstanding.

- 22 PHP feature tests / 345 assertions passed: StudentWelcomeTest, StaffProfileSettingsAccessTest, PortalPaymentPagesTest and RegistrarWorkspaceLayoutTest.
- Browser fixture passed 36 cases covering dialog width, corner radius, primary control height, neutral surfaces, confirmation alignment and title hierarchy. Its screenshot was visually inspected. Artifacts are ignored under `storage/framework/testing/portal-modals/`.
- 12 of 14 JavaScript checks passed, including modal opener/focus restoration, cancelled dismissal, password visibility and welcome lifecycle behavior.
- Two existing checks in `tests/js/student-welcome-theme.test.cjs` fail because they execute the beginning of `dtc-app.js` in a minimal DOM stub that lacks `document.addEventListener` (or `document`). The existing focus-lifecycle block precedes the theme functions. This change modifies neither that JavaScript file nor those tests; the failures concern their extraction/stubs rather than the new modal stylesheet.
- Whitespace checks passed. The browser fixtures do not establish that every authenticated dialog was manually exercised with live data.

Manual review: open representative confirmation, user-management, enrollment review, payment review/receipt, document status, appointment/slot, Registrar contact, crop/photo and welcome dialogs in both themes. Check narrow screens, long names/text, scrollable bodies, Escape/dismissal, keyboard focus and the original form actions.

Design process: unslop by Damir Sokolovsky, [heydamir.design/unslop](https://heydamir.design/unslop), CC BY 4.0. Applied as a polish pass using DTC's existing visual system.
