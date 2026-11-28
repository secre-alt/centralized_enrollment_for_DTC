# Enrollment advising appointments

Students and alumni can request an enrollment-advising consultation from the portal. Topics are enrollment guidance, transfer-credit questions, returning-student concerns, and other enrollment questions. Requests use Registrar-managed appointment slots and remain pending until a Registrar or Admin confirms them. Students can cancel a pending request.

Advising appointments are separate from document requests, pickup, and payment. Ready documents remain available for walk-in claiming under the posted Citizen's Charter; no pickup appointment is required. Transfer-credit guidance is preliminary until official records and approved school rules are reviewed. The app does not calculate equivalencies or enforce prerequisites, unit limits, or credit decisions through this consultation flow.

The flow reuses the existing `appointments` and `appointment_slots` tables and appointment lifecycle history. It uses the existing shared slot capacity, so advising requests and any other active bookings for a slot count toward the same configured capacity. No schema migration or new fee is introduced.
