# Admission validation audit and improvements

Updated: 2026-11-07.

The browser previously checked only empty required values; optional values and malformed dates/emails could pass Next. The server accepted arbitrary strings for all contact numbers and LRN, and accepted loose date parsing. Malformed array values could also break the form's error redisplay. These gaps are now covered on both sides without changing required fields, the database, or submission acknowledgment.

| Field | Validation |
|---|---|
| Birthdate | Required real calendar date, strict YYYY-MM-DD on the server, ages 15 through 100 inclusive, calculated from the current date; future dates and implausibly old years are rejected. Browser uses the server-rendered minimum and maximum, avoiding client timezone/date assumptions. Leap days are checked. |
| Phone, parent contact, guardian contact | Optional. Philippine mobiles use 09 plus nine digits or +639 plus nine digits; landlines include their area code. Other international numbers begin with + and have 7–15 digits. Spaces, parentheses, periods and hyphens are supported. Letters, misplaced plus signs and incorrect local lengths are rejected. Formatting is retained in storage. |
| Names | Unicode letters/combining marks, spaces, apostrophes, periods and hyphens; at least one letter; maximum 255 characters. Existing optionality remains. Honorific/suffix remain free-text affixes with their existing 20-character limit. |
| Email | Existing required server email validation retained; browser checks the email input's native format validity before advancing. No DNS or ownership assertion is made. |
| LRN | Optional, exactly 12 ASCII digits when supplied; leading zeroes preserved. |
| Postal code | Optional; four digits for Philippines/default country. Other countries allow up to 20 letters/digits/spaces/hyphens. International entry is no longer limited by the previous four-character HTML maximum. |
| School year | Optional four-digit year or consecutive range (2024-2025); en/em dash ranges also accepted and retained. Reversed/nonconsecutive ranges rejected. |
| Documents | Optional; server checks upload validity, detected type, extension, non-empty size and the effective upload limit (5 MB policy ceiling, or lower PHP limit). Only known document keys accepted. Browser checks extension, non-empty size, per-file size and combined request size before Next/Continue; server MIME checks remain authoritative. Existing applicant-type handling and marriage-certificate condition retained. |

`App\Support\AdmissionValidation` exports shared patterns to the Blade page for JavaScript. No dependencies were added. Existing geographic hierarchy, applicant education-level validation, rate limiting, anti-replay token and final acknowledgment remain in force.

All applicable fields, including populated optional fields, are checked before advancement and again before confirmation. Forward progress clicks validate earlier steps; backward navigation remains available. Errors retain `aria-invalid` and `aria-describedby`, appear separately from input controls, open the relevant disclosure/tab, and focus/scroll to the first invalid field. File input IDs are unique across applicant sections; server error redisplay selects the applicable enabled file input. Non-scalar old input is discarded before rendering, preserving errors without a Blade type error.

## Verification

- 36 PHP tests, 899 assertions passed across public admission, birthdate, education, locations, status, rate limiting, token replay and the new validation cases.
- 13 JavaScript checks passed, including browser validation against the exact PHP-exported patterns.
- Targeted browser run: 16 layout cases plus functional checks for future birthdate, invalid email/mobile/LRN, hidden-field error disclosure, oversized upload, blocked progress jumps, corrected values, review, and confirmation cancellation passed.
- PHP and JavaScript syntax and `git diff --check` passed.

Minimum admission age is 15, as agreed. A maximum age of 100 is used as a plausibility guard against accidental years such as 1600 or 1800. Both boundaries move with the server date and are enforced in HTML, JavaScript and Laravel. No new required contact fields, nationality restriction, document requirement or school-year age policy was added. Phone/email checks establish format, not ownership or service availability. No live application was submitted by browser QA; persistence and rejection paths are covered by integration tests.

## Runtime upload limits

The inspected PHP runtime has `upload_max_filesize=2M` and `post_max_size=8M`, despite the old page advertising 5 MB. The form now derives the effective per-file limit from the running PHP configuration, capped at the application's existing 5 MB ceiling; messages and browser/server validation use the same limit. A browser check reserves 64 KB for multipart/form metadata when comparing combined files to the configured request limit.

No machine-wide PHP settings were changed. To offer the full 5 MB allowance for several documents, the serving PHP configuration must permit at least 5 MB per file and sufficient total POST size (for example, 32 MB). The UI automatically reflects that configuration after it is changed and the PHP server is restarted.


## Barangay selections

The optional barangay code must belong to the selected city (including independent cities). A valid selection stores its canonical catalog name in the existing barangay column. Unmatched manual text stays optional and retains the existing length/string validation. Invalid codes return the applicant to Contact and focus the visible barangay field. The lookup endpoint rejects unknown city codes.


## Country selections

Country remains optional and defaults to Philippines in the form. Populated values must match the bundled English country/territory list, case-insensitively. United States of America, USA and UK are supported aliases; persisted values use the catalog spelling. Browser and server use the same names and aliases. No country-code schema migration is needed. The admission regression suite passed 39 PHP tests (959 assertions) and 14 JavaScript checks after this update.


## Postal-code format refinement

Postal code remains optional. Philippine values must be four ASCII digits and cannot be 0000; leading zeroes are retained. International values allow up to 20 letters or digits with single spaces or hyphens between groups, rejecting trailing/repeated separators and symbols. Browser and server share the patterns. Country changes update input mode, length and help text, and revalidate populated values without silently truncating them. These are format checks, not confirmation of postal assignment or address deliverability; the postal reference reviewed was not introduced as a mandatory address registry.


## School-year date bounds

Single years and both endpoints of consecutive school-year ranges must fall between the server current year minus 100 and the current year, inclusive. In 2026 that is 1926 through 2026: 2025-2026 is allowed, while 2026-2027 and 2027-2028 are rejected. Browser and server use the same exported bounds. This rolling lower bound is an input plausibility guard, not a claim about formal institutional eligibility. Optionality remains route-dependent; the attendance year remains required for Returnees. The field includes accessible guidance describing the allowed bounds.
