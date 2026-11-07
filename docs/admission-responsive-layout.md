# Admission responsive layout audit

Date: 2026-11-06. Audit completed before implementation.

## Existing constraints

- Installed framework: Laravel 9.52.21 (composer requires ^9.19), Blade, plain JavaScript, public CSS. No frontend build is needed for wizard assets.
- `application-wizard.css` is also loaded by the application status page. Changes must be scoped to the create form, including its modal overlays.
- Several generations of wrapper, panel, field, and breakpoint rules compete. The final floating-label block reduces labels to about 10px and overrides earlier external labels. Late panel padding expands the body to 32px/40px vertically.
- The back link occupies a separate row. Progress labels reserve two lines. The notice is already collapsed correctly.
- Controls vary between 46px, 48px and a later 50px minimum. Personal inputs have an explicit 48px height. Mobile tracker labels fall to 8px.
- Contact includes email, phone and seven address fields, arranged mostly in pairs. Family and uploads share one very long panel. These need logical separation rather than smaller controls.
- Optional personal details, guardian, spouse, PWD, international address, applicant-specific education and marriage uploads change height. Existing visibility and enabled/disabled behavior must be retained.
- Review builds six expanded sections. Legal overlays lack dialog semantics, initial focus, focus containment and restoration.
- Main shared navbar is 88px tall. Browser chrome reduces a 768px screen further; screen resolution alone cannot establish viewport fit.

## Original steps and decisions

Counts exclude CSRF, anti-replay, location-code and acknowledgment hidden inputs. Heights are rough content-only estimates before browser measurement; labels and real values can wrap.

| Original step | Visible controls | Conditional content | Existing layout / estimated height | Decision |
|---|---|---|---|---|
| 1 Admission | 2 selects | Cross-enrollee notice | Two columns; about 180px | Retain pairs |
| 2 Personal | 7 (including title and suffix) | 5 optional demographic controls; spouse when married | Three name groups, identity pair; about 380px collapsed | Retain groups, external labels, compact section gaps |
| 3 Contact | 9 | International location | Paired fields plus full-width street; about 600px | Separate Contact (2) and Address (7) |
| 4 Education | 5 shared | 1 new-student, 2 college, 3 cross-enrollee controls | Pairs plus full-width sections; about 500–700px | Two-column grid across shared and applicable fields |
| 5 Family & Documents | 8 family/other controls; 0–4 upload controls | Guardian (2), PWD (1), marriage upload (1), checklist tab | Four family rows followed by upload cards; over 1000px | Separate Family and Documents; upload cards in two columns |
| 6 Review | No editable controls | All entered values and filenames | Six expanded sections; over 1000px | Native expandable review sections with edit actions |

## Verification target

Measure `window.innerWidth`, `window.innerHeight`, document overflow and navigation bounds. Test 1366x660 and 1920x960 as conservative browser-content proxies for the requested screens, plus the full resolution viewports, mobile, tablet, short desktop and 200% equivalent reflow. Actual browser chrome varies, so proxies must not be reported as a measurement of the user's physical browser. Standard means no error messages or expanded disclosures. Applicant-specific education and normal uploads are standard for the applicable applicant type. Conditional expansions and enlarged text may scroll. Never clip form content.

## Implementation and verification results

The wizard now has eight steps. Contact/Address and Family/Documents are separate panels in the same form. Field names, backend rules, persistence, applicant-type handling, anti-replay token and final submission remain intact. The JavaScript derives the step count from the form; review edit destinations and server-error destinations follow the new numbering.

### Final layout

| Step | Standard visible controls | Additional content | Desktop layout |
|---|---:|---|---|
| 1 Admission | 2 | Cross-enrollee notice | Two columns |
| 2 Personal | 7 | Five optional demographics, spouse when married | Three name groups, two identity columns; optional disclosure preserved |
| 3 Contact | 2 | Validation feedback | Email and phone in two columns |
| 4 Address | 7 | International location | Full-width street, six location fields in three columns at 1100px+ |
| 5 Education | 6 new student; 7 transferee/shiftee/returnee; 8 cross-enrollee | Applicant-type changes may display a re-selection hint | Three columns at 1100px+, two on tablet; inactive controls stay disabled |
| 6 Family | 8 | Two guardian fields, PWD ID | Father, mother and parent-contact groups in three columns; occupation/disability pair below. Guardian disclosure in the panel header |
| 7 Documents | 3 new student; 4 transferee; 0 other types | Marriage upload, checklist tab | Two upload columns; repeated format/size instructions consolidated in the header |
| 8 Review | No editable inputs | Seven expandable review sections | Two columns, accessible native summaries and Edit buttons |

At widths below 768px, fields use one column and the tracker becomes a four-column, two-row grid with visible 12px labels. Name affixes retain their relationship to the corresponding name. Main cards use 24px padding, reduced to 20px on short desktop windows and 16px on mobile. Controls remain 48px, input text 16px, labels 14px. Existing theme colors and dark styles remain in use. Spacing, focus color, borders and radii use scoped custom properties with existing DTC token fallbacks.

Legacy late presentation rules are isolated from `.admission-wizard`, preserving status-page behavior. A scoped `.d-none` utility keeps conditional sections hidden even when the Bootstrap CDN fails. Required controls were not moved into disclosures. There is no content-height cap or clipping on form panels.

Privacy and Terms dialogs now expose dialog semantics, focus the dialog, make background siblings inert while open, contain Tab/Shift+Tab, support Escape and restore the triggering focus. Dialog bodies scroll within the available dynamic viewport height; footer actions remain outside the scrolling body. The existing shared submission confirmation already handles focus and cancellation and was retained.

### Modified files

- `public/css/application-wizard.css`: scoped tokens, compact layout, form grids, readable labels, responsive tracker, modal sizing; status rules isolated.
- `resources/views/public/application/create.blade.php`: compact header/notice, eight panel layout, dialog semantics, updated error destinations, redundant headings/instructions removed.
- `resources/views/public/application/partials/document-input.blade.php`: consolidated repeated upload instructions into the Documents header; upload inputs and errors retained.
- `public/js/application-wizard.js`: derived step count, updated education/review destinations, expandable review sections, legal-dialog focus management, concise redundant helper copy.
- `tests/Feature/PublicApplicationEducationTest.php`: rendering expectations updated for the separated sections.
- `tests/js/application-education.test.cjs`: education-panel selector updated.
- `tests/browser/admission-layout.cjs`: dependency-free local Edge/Chrome CDP verification and screenshot capture; never submits a live application.
- `docs/admission-responsive-layout.md`: audit and verification report.

### Automated verification

- PHP: 31 tests, 680 assertions passed. Covers education, birthdate, applicant education levels, locations, submission rate limiting and the shared application-status page, including valid/invalid submissions and conditional data.
- JavaScript: nine checks passed across validation, education switching/levels, confirmation acknowledgment, draft lifecycle and text normalization.
- Blade compilation (`php artisan view:cache`), JavaScript syntax and `git diff --check` passed.
- Headless Edge: all five applicant types, eight steps and nine actual emulated browser-content viewports = 360 layout cases. Viewports: 1366x660, 1920x960, 1366x768, 1920x1080, 768x1024, 390x844, 320x640, 1366x500 and 683x330. Desktop fit is required at heights of 660px and above; shorter/mobile views may scroll. Horizontal overflow is checked in every case.
- Additional browser checks cover invalid required-field focus, Next/Previous, saved draft values, optional/conditional visibility, populated review, file selection and filename review, Privacy/Terms focus behavior, and cancelling final confirmation. Eight dark-mode desktop steps are checked separately.
- Raw JSON results and screenshots are stored in `storage/framework/testing/admission-layout/`. Run a local Laravel server on port 8765, then `node tests/browser/admission-layout.cjs`. Override `ADMISSION_URL` or `BROWSER_PATH` if needed.

All 360 layout cases passed horizontal-overflow checks; all 160 cases in the four desktop target viewports passed standard-field and navigation fit. All eight additional dark-mode steps passed. The table records the greatest navigation bottom coordinate across the five applicant types; each is below its viewport height.

| Step | Navigation bottom at 1366x660 | Navigation bottom at 1920x960 | Result |
|---|---:|---:|---|
| Admission | 493px | 529px | Fits |
| Personal | 609px | 637px | Fits |
| Contact | 407px | 435px | Fits |
| Address | 535px | 579px | Fits |
| Education | 593px | 645px | Fits |
| Family | 535px | 579px | Fits |
| Documents | 609px | 637px | Fits |
| Review | Natural scrolling | Natural scrolling | Expanded layout |

### Scope and remaining manual checks

The measured fit means the page heading, tracker, standard fields and navigation are visible at once. Expanded notices, review sections, optional demographics, guardian details, errors and conditional additions can extend the page naturally. The footer may remain below the fold; form content is not clipped to suppress the document scrollbar.

1366x660 and 1920x960 are conservative viewport proxies, not measurements of the instructor's browser chrome. The 683x330 case verifies the reflow dimensions corresponding to 200% zoom of 1366x660; native browser zoom and an independent enlarged-text setting still need manual checking. A screen reader and native OS file/date/select pickers also need manual checks. No live application was submitted during browser QA; submission is verified by the existing server and JavaScript tests. External Google Fonts/Lucide availability in the user's browser should be checked separately from the headless layout measurements.

No schemas, dependencies, unrelated workspace edits, commits or staging were changed.

## Subsequent visual refinement

The Application notice dropdown was removed at the user's request. Required-field guidance is in Admission and screening/original-document instructions remain in Documents. The header tracker now places labels beneath consistent 32px circles, with connector lines between the circles and a subtler active ring. Animated floating labels are restored (14px at rest, approximately 12px when floated), and honorific/first-name and last-name/suffix controls are joined again. Reduced-motion behavior, normal-flow errors, 48px controls and the eight-step workflow are preserved. The updated 360-case browser run passed; 13 PHP feature tests and six JavaScript validation/submission checks passed. The table above reflects this refinement.

## Spacing and grouping refinement

Following the laptop screenshots, editable fields now use 24px gaps normally and 16px gaps on short desktop windows, replacing the previous 8px compact gap. Card body padding stays at 24px on desktop; personal subsections have 24px separation and 16px between the identity heading and its controls. The original review card/accordion presentation, counts, Edit actions and acknowledgment remain in place, with its established compact spacing retained for short desktops.

Admission and Contact field rows are capped at 1040px. The Education grid gives school location and previous program appropriate spans so the final college row contains the program and year level together. Expanded personal details use a shared three-column grid on wide screens, preserving DOM and focus order. The Family panel now uses the users icon; the malformed LRN helper text is corrected. Animated labels and joined name controls are preserved.

Review upload badges now count selected files against applicable upload controls; the no-file fallback message does not count as an upload. Review education profiles now use the applicant type value instead of its display label, restoring all relevant education fields without changing the review format.

Verification for this revision: 17 PHP tests (548 assertions), eight JavaScript checks, all 360 standard layout cases and eight dark-mode steps passed. Expanded Personal was also checked at 1920x960, 1536x728, 1366x660 and 390x844: no horizontal overflow, and actions reachable at all four sizes. It fits without scrolling at 1920x960; expanded optional content naturally scrolls at the three shorter sizes. This intentionally preserves readable spacing rather than clipping content. Results are in expanded.json and the expanded personal screenshots alongside the standard measurements. The per-step table above has been refreshed.

## Review layout restored

At the user's explicit request, the review-only no-scroll target is retired. Review now restores the original full-width, single-column sections with all label/value rows expanded and Edit actions in each section header. Accordion summaries and plus/minus toggles are removed. Review scrolls naturally, including its acknowledgment and Continue action. Corrected applicant-specific education content and real upload counts are retained. Browser fit assertions now exempt Review while retaining horizontal-overflow and functional checks. Earlier Review fit measurements describe the previous collapsed presentation.

## Single admission container

The progress heading, segmented navigation, active form panel and Previous/Next/Continue actions now share one white (dark-theme compatible) card. The top shows an announced Step X of 8 count and current section name. Progress segments remain named keyboard-operable buttons with aria-current and aria-controls, while decorative circles and connectors are replaced by bars. Step fields, labels, joined name controls, validation and submission retain their existing behavior. The card grows naturally; no empty fixed-height area is introduced. Expanded Review remains a full-width, expanded summary within the same container.

Desktop card padding is 32px, with 24px top/side padding and a 12px bottom inset for shorter desktop windows; mobile uses 24px/16px. The admission-only sticky footer uses a slimmer inset to keep controls clear. All 360 layout cases passed, including a new check that standard desktop entry actions are above the sticky footer. Progress metadata, form navigation, legal dialogs, review and submission cancellation passed browser checks. The revision also passed 17 PHP tests (548 assertions) and eight JavaScript checks.


### Contact and address combined

Address fields are restored beneath email and phone in Contact Information. The wizard now has seven steps; server error routing, education controls and Review Edit links follow the new numbering. Review retains its separate Current Address section. The combined Contact step may scroll naturally on short screens to preserve comfortable spacing.


### Location entry refinement

Contact now starts its address section with Country, followed by searchable native suggestions for Region, Province / Independent City, and City / Municipality. Suggestions use the local PSGC snapshot and work without external requests. Parent changes clear downstream text and codes. Independent cities hide the redundant city field. Unmatched text remains a valid manual fallback; catalog matches resolve to canonical server names. Barangay stays free text because the bundled catalog does not include barangays. International entry hides and disables Philippine fields and uses the existing city / state / province field. Postal code remains editable without speculative autofill. Gray floating labels and two-column desktop / single-column mobile spacing are retained.


### Barangay suggestions

Barangay now uses searchable native suggestions from a bundled 42,010-row PSGC snapshot. Selecting a city loads only its barangays from the local Laravel endpoint; independent cities work the same way. Changing the city clears the selected barangay, and late responses cannot replace suggestions for a newer city. Existing values are resolved again after draft/server redisplay. Loading and lookup failure messages are announced beside the field; manual text remains available without a catalog match, including when suggestions cannot load. A matched barangay submits its code for server validation against the selected city and canonicalizes the stored name. No database migration is needed: the existing barangay name column remains the persisted value. This supersedes the earlier free-text-only barangay limitation.


### Country suggestions

Country uses a local searchable list with Philippines as the default. Gray floating labels and current sizing are retained. Names are canonicalized on blur and on the server; unsupported country names block advancement/submission. Blank remains optional. Common United States of America / USA / UK aliases resolve to their catalog names. Switching abroad clears Philippine codes and shows international entry; draft restoration now restores the selected country alongside its address.


### Location picker reliability

Location fields now render an in-page searchable combobox over their local datalist data, avoiding inconsistent native popup behavior. Clicking a populated field reopens all choices; typing filters them. Arrow keys, Enter, Escape and Tab are supported without advancing the wizard on selection. Menus stay within the field width and support dark mode. The cascade handles both input and change events, preserves descendants on duplicate events, and avoids rebuilding unchanged lists. Country searches keep the current address visible until a recognized country is chosen. Native datalists remain available if enhancement is unavailable.


### Desktop field balance and spacing

The Admission row now spans the available card width with equal columns, matching the card heading and action divider. Field gaps remain 24px on short desktops instead of shrinking to 16px. Header, section and navigation spacing is increased, and the short-desktop card retains bottom padding. Selects and location comboboxes share a gray chevron with 16px inset and 44px text clearance; disclosure chevrons are also gray. Mobile continues to use a single column.

The admission main area reserves 64px below its content so actions can scroll clear of the sticky footer. Browser checks verify reachable actions rather than requiring every action to remain above the footer before scrolling. This retains the requested spacing on short desktop viewports.


### Location refresh restoration fix

Location initialization now runs after the wizard restores drafts at DOMContentLoaded. Previously the deferred location script could initialize first and treat the subsequently restored city as a user change, clearing its barangay. Page restoration and field focus rebuild the dependent suggestions while preserving restored values. Both location enhancement scripts use file-versioned URLs to avoid stale browser assets. Browser regression now performs a real reload with saved Region VII / Bohol / Danao / Remedios and verifies menus open and city/barangay codes resolve. All 28 targeted layout cases and reload interactions passed.


### Required address and education

Country, residential description, and city/locality are required. Philippine catalog and manual city entry are supported; independent cities do not require a province. Foreign addresses use the international locality field. Region, province, barangay and postal code are not universally mandatory. Education uses route-specific minimums from AdmissionValidation::REQUIRED_EDUCATION. Required markers, HTML attributes, errors and final confirmation validation update together when applicant type or country changes. Other applicable inputs remain optional.


### Description and spacing cleanup

Admission descriptions are shorter and avoid repeating field instructions. Address uses one consistent grid at every breakpoint: 24px rows, 32px above the section, and 6px between heading and description. Repeated country/location instructions and routine barangay help remain available to assistive technology without taking separate visual rows. Barangay loading and error messages remain visible. Desktop keeps equal columns and street/postal pairing; mobile stacks fields. Required rules, Review structure, gray labels, and 112x44 navigation buttons are unchanged. Verified with 28 targeted browser layout cases and 14 JavaScript checks.
