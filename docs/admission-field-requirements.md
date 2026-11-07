# Admission field requirements

Updated 2026-11-07 following user authorization to implement the recommendations and require an address. These are project application rules, not an independently verified statement of institutional policy. This replaces the October matrix. See admission-validation.md for format rules and admission-required-optional-audit.md for the audit history.

## Required for all applicants

- Applicant Type and Intended Program.
- First Name and Last Name.
- Gender: existing male/female rule retained pending a separate decision.
- Date of Birth: valid date, age 15 through 100.
- Email Address.
- Country from the shared local country list.
- Residential description: street, purok, sitio, or another usable residential description; a numeric house number is not required.
- City/locality: Philippine city/municipality, or the international city/state/province field for foreign addresses.

A Philippine city can be supplied through a valid catalog code or manually entered text. Region and province remain optional unless needed to resolve a chosen catalog hierarchy; supplied codes must belong together. Independent cities do not require a province. Barangay and postal code remain optional. International addresses do not require Philippine location fields or codes. Country defaults to Philippines in the UI but cannot be blank on submission.

## Required education minimums

| Applicant type | Required fields |
|---|---|
| New Student | Most recent school; completion status. |
| Transferee | Previous college; previous program; last year level/semester. |
| Shiftee | Current or previous program. |
| Returnee | Previous program; last school year attended. |
| Cross-Enrollee | Home institution; home program. |

Other applicable education fields remain optional, including education level, school location and SHS strand. Route-inapplicable fields are disabled in the browser and excluded on the server. AdmissionValidation::REQUIRED_EDUCATION shares the required names with the browser. Returnee attendance year retains its year/consecutive-school-year format check.

## Optional fields

- Honorific, Middle Name, Suffix.
- Phone/contact number.
- Place of Birth, Nationality, Religion. Blank nationality saves as null.
- Marital Status; Spouse Name is applicable only when married and otherwise excluded.
- All parent/guardian details and occupations; no emergency-contact requirement was introduced.
- Applicant Occupation, Disability and PWD ID. ID remains optional for a disclosed disability and is excluded without disclosure.
- Barangay, Postal Code, and address components not required above.
- Preliminary digital documents applicable to the applicant route. Marriage certificate remains optional when married. Physical enrollment requirements belong to a later stage.

Optional fields still have format and applicability validation when populated.

## Submission and Review

Final confirmation explicitly acknowledges accuracy, Terms and Privacy Policy. The browser sets the hidden acknowledgment only on final confirmation, and the server enforces acceptance. A session-bound submission token is also required for replay protection. Neither requires another typed applicant field.

Review retains expanded sections and Edit controls. Filled/total badges count supplied details, including optional values; they are not required-completion quotas. Browser validation checks required fields before advancing and again before confirmation; server validation also enforces direct posts.

## Verification

42 PHP tests passed in an isolated admission test database, covering required education for every route, manual and independent-city addresses, international addresses, optional disclosures, files and acknowledgment. 14 JavaScript checks passed. Browser checks cover route-specific required attributes and blocking an empty residential description.

Applicant occupation, disability/accessibility needs and optional PWD ID are collected under Additional Personal Details and reviewed within Personal Information. Family contains parent/guardian details only. Parent/guardian names and contact numbers are optional; there is no separate emergency-contact name/relationship/phone record in the current form.
