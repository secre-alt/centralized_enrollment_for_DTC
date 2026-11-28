# Enrollment advising appointments

Students and alumni can request a free Registrar consultation from the portal. The active default service is **General Registrar Consultation / Advising**. Requests use Registrar-managed appointment slots and remain pending until a Registrar or Admin confirms them. Registrar staff can confirm, reject with a reason, check in, complete, record a no-show after the scheduled end, and add private staff remarks. Students can reschedule or cancel eligible upcoming requests. Applicants see a service only when Registrar configuration explicitly includes their role.

Advising appointments are separate from document requests, pickup, and payment. Ready documents remain available for walk-in claiming under the posted Citizen's Charter; no pickup appointment is required. Transfer-credit guidance is preliminary until official records and approved school rules are reviewed. The app does not calculate equivalencies or enforce prerequisites, unit limits, or credit decisions through this consultation flow.

The flow reuses `appointments`, `appointment_slots`, and appointment lifecycle history, with an additive `appointment_services` catalog and consultation lifecycle fields. It uses the existing shared slot capacity, so consultation requests and active historical bookings for a slot count toward the same configured capacity. The catalog seeds eight inactive examples; Registrar staff may configure their eligibility and approval behavior. Services with appointments are retained and cannot be deleted.

## Student portal navigation

Consultations are booked and managed on the portal's **Book Appointment** page. **Historical document appointments** is read-only history for older scheduled visits linked to document requests. Document status, fees, and release information belong under **Document Requests**; payment records belong under **Payment History**. New document requests and walk-in release do not need an appointment. Historical claiming bookings, when present, remain under **Past claiming bookings** and are separate from both flows.

The Registrar consultation workspace includes a date-range summary grouped by service and lifecycle status. Historical document appointment records remain read-only in appointment operations; their original payments, receipts, and document status are preserved in their existing workspaces. See [the independent workflow audit](independent-appointments-document-requests-audit.md) for migration and verification status.
