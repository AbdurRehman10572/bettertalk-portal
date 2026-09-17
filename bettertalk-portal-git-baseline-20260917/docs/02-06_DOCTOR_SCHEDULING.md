# Better Talk — Doctor Scheduling

## 1. Preconditions

Before scheduling, the agent should have:

- Confirmed the client's requirement/category.
- Recorded client time preference and timezone.
- Confirmed payment according to business policy.
- Selected an appropriate active doctor.
- Checked the doctor's availability and conflicts.

## 2. Doctor Profile and Availability

Admin maintains doctor name, gender, specialties/categories, qualifications/experience, contact information, active status, calling access, working hours, breaks, leave, and appointment duration/buffer rules.

## 3. Appointment Workflow

`Draft` → `Scheduled` → `Confirmed` → `In Progress` → `Completed`

Alternate statuses: `Reschedule Requested`, `Rescheduled`, `Cancelled by Client`, `Cancelled by Better Talk`, `Doctor No-show`, `Client No-answer`, `Payment Hold`.

## 4. Scheduling Rules

- Prevent double-booking a doctor.
- Display availability in Pakistan Standard Time by default.
- Save appointment time with an unambiguous timezone.
- Link Client ID, doctor, assigning agent, payment, and relevant intake Call ID.
- Keep an immutable change history for doctor/time/status changes.
- Require a reason for reschedule/cancellation.
- Restrict scheduling with inactive doctors or unpaid clients unless admin overrides with reason.

## 5. Scheduled Call

1. Doctor opens the assigned appointment.
2. At the scheduled time, doctor initiates the call through portal calling access.
3. Calling system creates a new Call ID and links it to the appointment and Client ID.
4. Agent is not bridged into the session.
5. Doctor records outcome: completed, no answer, failed, or reschedule needed.
6. Portal updates the appointment timeline and history.

## 6. Notifications

V1 notifications must respect channel consent and WhatsApp's active 24-hour service window. Do not rely on unapproved business-initiated WhatsApp templates. SMS or human-led confirmation may be used according to configured vendor capability and consent.

## 7. Returning Client and Same Doctor

- Open the existing Client ID.
- Display previous doctors and completed appointments to authorized staff.
- Allow the agent to request the same doctor.
- Check the doctor remains active, suitable, and available.
- Create a new appointment and payment record; never reuse/overwrite the old appointment.

## 8. Acceptance Tests

- Agent cannot book the same doctor into overlapping appointments.
- Doctor sees a newly assigned appointment but not unrelated clients.
- Doctor can initiate the call and the resulting Call ID appears on the appointment.
- Rescheduling preserves the original schedule in audit history.
- Returning client can be booked with the prior doctor while retaining all earlier records.

