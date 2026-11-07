# Admission required and optional field audit

Reviewed against the current Blade form, wizard JavaScript, submission controller, and shared validation rules on 2026-11-07. This records implementation and proposes changes; it does not establish DTC admissions policy. No application validation rules were changed by this audit.

## Current requirements

The seven applicant-facing required fields are Applicant Type, Intended Program, First Name, Last Name, Gender, Date of Birth, and Email Address. Their HTML required attributes agree with server validation. The wizard validates these before advancing and again before confirmation. Date of Birth also enforces the agreed 15–100 age range.

Final submission also requires an accepted acknowledgment, supplied only through the confirmation action, and the session-bound submission token. These are submission controls, not additional fields applicants need to type.

All remaining applicant fields are optional. Optional means a blank value is accepted; populated values must still pass format and applicability checks.

## Recommended classification

| Field/group | Current | Recommendation |
|---|---|---|
| Applicant Type, Intended Program | Required | Keep required: identify the application route and intended study. |
| First Name, Last Name | Required | Keep required. Provide a staff-assisted exception if an applicant's official documents have a single name; do not encourage invented names. |
| Date of Birth | Required | Keep required. Treat the configured age limits as application rules, not independently verified institutional eligibility policy. |
| Email | Required | Keep required for this email-based workflow. Format validation does not establish ownership. |
| Gender | Required; male/female choices | Confirm its processing purpose and accepted values with the Registrar. Prefer optional at preliminary application unless a documented requirement needs it. |
| Honorific, Middle Name, Suffix | Optional | Keep optional. Never require a middle name or suffix for applicants who do not have one. |
| Phone | Optional | Keep optional while email is the mandatory communication channel. Require only if the actual follow-up process needs it. |
| Place of Birth, Nationality | Optional | Collect only when needed for records/eligibility; require nationality later only if it drives a defined process. |
| Religion | Optional | Keep optional; avoid using it to block preliminary applications. |
| Marital Status, Spouse Name | Optional; spouse shown when married | Keep optional. Marriage should make spouse details applicable, not automatically mandatory. |
| LRN | Optional | Applicable to applicants with a DepEd LRN. Require conditionally only if the Registrar needs it for a defined applicant route; retain a route for applicants without one. |
| Country, Region, Province, City, Barangay, Street | Optional | Keep optional for preliminary screening unless a current address is operationally necessary. If requiring an address, define a complete-address rule rather than requiring every field universally. |
| Postal Code | Optional | Keep optional; enforce the selected country's supported format when supplied. Do not treat format as proof of delivery. |
| Parent/guardian names, contacts, addresses, occupations | Optional | Keep optional. If an emergency contact is required, collect one name/relationship/contact rather than requiring both parents. Do not require parent occupations for basic application submission. |
| Applicant Occupation | Optional | Keep optional; applies only when relevant. |
| Disability, PWD ID | Optional | Keep voluntary. A disability disclosure must not automatically require possession of a PWD ID. |
| Preliminary digital documents | Optional; type-dependent | Keep optional for the existing preliminary screening workflow. Track later enrollment requirements separately. |
| Confirmation acknowledgment | Required | Keep required at final confirmation. |

## Education: main policy gap

All education fields are currently optional, so an application can be submitted without identifying a previous school. If preliminary academic screening depends on this information, the following are candidates for required fields, subject to Registrar confirmation:

| Applicant type | Candidate minimum information | Keep optional/conditional |
|---|---|---|
| New Student | Most recent school; education/completion status | School year as needed; strand only where applicable; school location need not duplicate the school identity. |
| Transferee | Previous college; previous program; last year/semester attended | Completion status and dates as needed; uploads may follow later. |
| Shiftee | Current program and student-record identification | Reuse the institution's existing school data where possible. No student-number input is currently provided by this form. |
| Returnee | Previous program and last attendance period | Reuse existing student records where available. |
| Cross-Enrollee | Home institution and home program | Last year level and school location unless needed for screening. |

These are proposed minimums, not newly enforced rules. Avoid requiring fields that the chosen route hides or excludes. Where education level is already fixed by the route, derive it instead of requiring a redundant choice.

## Conditional address rules, if address becomes required

- Country and locality should establish which address structure applies.
- Philippine addresses can require a recognized city/municipality and a usable residential description; require region/province selections only to resolve the hierarchy. Independent cities must not require a province.
- Allow manual address entry when a location is missing from the snapshot. Do not make catalog codes mandatory for manual entry.
- International addresses must not require Philippine region, barangay, or postal-code formats. The current international input combines city/state/province; split it only if separate values are necessary downstream.
- Do not require a house number when the applicant's address has none.

## Implementation and content findings

1. **Outdated requirements document:** `admission-field-requirements.md` describes guardians as absent, acknowledgment as a checkbox, affixes as allowlists, and birthdate as merely nonfuture. Those descriptions no longer match the implementation. The current guardian inputs exist, acknowledgment uses final confirmation, affixes are optional bounded strings, and ages are constrained.
2. **Family wording:** “No parent details? Add a guardian instead” can imply a required alternative, although neither is required. Label the whole group optional unless a new contact requirement is adopted.
3. **Defaults:** Blank nationality saves as Filipino, and blank country saves as Philippines. Defaults should not be described as confirmed applicant declarations. Consider preserving unknown nationality rather than assuming it.
4. **Conditional persistence:** Spouse Name and PWD ID have nullable server rules and are persisted directly. Their visibility does not itself enforce server applicability. Clear/exclude stale spouse values when not married and stale PWD details when no relevant disclosure is present, while preserving voluntary disclosure and manual correction.
5. **Review counts:** Filled/total counts include optional fields. They should not imply that an applicant must reach a full count to submit. Distinguish optional omissions from missing required information.
6. **Education descriptions:** Keep the “not required” copy synchronized with any future decision to require education fields. Change the form indicator, browser/server validation, review guidance, and regression coverage together.

## Recommended next decision

Keep the existing required identity/application/email fields while the Registrar decides the education minimums, gender requirement, and whether a complete address or emergency contact is needed at preliminary submission. Do not make all fields required. Document the agreed conditions, then implement them consistently across applicant types.


## Applied fixes

Following authorization, family guidance now explicitly states all details are optional. Review explains filled counts and badges have descriptive tooltips. Nationality starts blank and blank submissions store null; returned and drafted nationality values still display. Spouse Name is excluded server-side unless married, and PWD ID is excluded without a disability disclosure. Hidden stale values are cleared from both the form and draft when applicability changes. Disability disclosure without PWD ID remains accepted. The historical requirements matrix has been refreshed for guardian controls, acknowledgment, age rules, affixes and location codes. Required identity, program, email and acknowledgment rules are unchanged; education, address and other optional fields remain optional pending a concrete institutional decision.


## Authorized requirement changes

The user subsequently authorized implementation of the recommended minimums and explicitly required the address. The current requirements are now recorded in admission-field-requirements.md and supersede the earlier optional education/address recommendations in this historical audit. Gender retains the existing rule; phone, demographic disclosures, family information and preliminary uploads remain optional. No new emergency-contact field or requirement was introduced.
