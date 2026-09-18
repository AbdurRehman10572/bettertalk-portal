# Better Talk — Doctor Scheduling

**Last approved update:** 18 September 2026
**Scheduling engine:** Open-source Easy!Appointments, integrated behind the Better Talk Portal

## 1. Preconditions

Before scheduling, the agent should have:

- Confirmed the client's requirement/category.
- Recorded client time preference and timezone.
- Confirmed payment according to business policy.
- Selected an appropriate active doctor.
- Checked the doctor's availability and conflicts.

## 2. Doctor Profile and Availability

Admin creates the permanent Doctor ID and maintains the real/internal name, customer-facing pseudonym, gender, specialties/categories, qualifications/experience, contact information, active status, calling access, and permitted durations.

Doctors may edit their own recurring working hours, breaks, date exceptions, leave/vacation, and temporary unavailable periods. Admin can edit or override availability for any doctor; every override is audited.

Each doctor may support one or more durations selected by Admin from `15`, `30`, `45`, and `60` minutes. Only that doctor's permitted durations are offered during scheduling.

## 3. Appointment Workflow

`Available` → `Temporarily Held` → `Scheduled` → `Confirmed` → `In Progress` → `Completed`

Hold release path: `Temporarily Held` → `Hold Expired` → `Available`.

Alternate statuses: `Reschedule Requested`, `Cancellation Requested`, `Rescheduled`, `Cancelled by Client`, `Cancelled by Better Talk`, `Doctor No-show`, `Client No-answer`, `Payment Hold`.

## 4. Temporary Hold Flow

1. Agent selects a doctor, permitted duration, date, and available start time and opens the booking action.
2. The portal atomically creates a 15-minute hold and immediately removes that time range from availability for other agents.
3. The hold stores doctor, Case ID, agent/user, duration, start time, created time, and expiry time, but it is not yet a final appointment and receives no final Appointment ID.
4. Agent completes the booking information and clicks **Schedule**.
5. The portal rechecks payment eligibility, doctor/activity status, hold ownership/expiry, and conflicts, then atomically creates the appointment and converts/releases the hold.
6. If the agent abandons the flow or 15 minutes elapse, the hold expires and the slot becomes available again.
7. If another transaction has already won or the hold expired, the agent receives `Slot no longer available` and must select another slot.

## 5. Scheduling Rules

- Prevent double-booking a doctor.
- Display availability in Pakistan Standard Time by default.
- Save appointment time with an unambiguous timezone.
- Link Client ID, Case ID, Doctor ID, assigning agent, Payment ID, and all relevant Agent Call IDs.
- Keep an immutable change history for doctor/time/status changes.
- Require a reason for reschedule/cancellation.
- Restrict scheduling with inactive doctors or unpaid cases unless Admin overrides with reason.
- Treat overlapping held and scheduled intervals as unavailable, including holds/appointments with different permitted durations.
- The Better Talk Portal is the operational source of truth and Easy!Appointments supplies synchronized provider, service/duration, availability, appointment, and unavailability data.

## 6. Scheduled Call

1. Doctor opens the assigned appointment.
2. At the scheduled time, doctor initiates the call through portal calling access.
3. Calling system creates a new Doctor Call ID and links it to the Appointment ID, Case ID, Client ID, and Doctor ID.
4. Agent is not bridged into the session.
5. Doctor records outcome: completed, no answer, failed, or reschedule needed.
6. Portal updates the appointment timeline and history.

## 7. Customer Appointment View and Requests

- Customer logs in at `bettertalk.pk/customer-login` with normalized mobile number and password; forgotten-password recovery uses OTP.
- Customer sees all upcoming/past appointments and simple statuses, including cancelled and no-answer outcomes.
- Show doctor pseudonym and approved public profile information without picture, real/internal name, contact details, Call IDs, or internal/clinical notes.
- Customer can submit reschedule or cancellation requests. Agent/Admin reviews and approves/rejects; the request alone does not release or alter the booked slot.
- Customer cannot self-book in V1.

## 8. Notifications

No automated appointment reminders are included in V1. Any human-led communication must respect channel consent and WhatsApp's active 24-hour service window; do not rely on unapproved business-initiated WhatsApp templates.

## 9. Returning Client and Same Doctor

- Open the existing Client ID.
- Display previous doctors and completed appointments to authorized staff.
- Allow the agent to request the same doctor.
- Check the doctor remains active, suitable, and available.
- Create a new appointment and payment record; never reuse/overwrite the old appointment.

## 10. Acceptance Tests

- Agent cannot book the same doctor into overlapping appointments.
- Doctor sees a newly assigned appointment but not unrelated clients.
- Doctor can initiate the call and the resulting Call ID appears on the appointment.
- Rescheduling preserves the original schedule in audit history.
- Returning client can be booked with the prior doctor while retaining all earlier records.
- Two agents selecting the same slot cannot both hold/book it; the first valid hold wins.
- A 15-minute hold blocks the entire time interval and automatically releases after expiry.
- Only durations permitted for the selected doctor are displayed and schedulable.
- Customer cancellation/reschedule requests do not alter the appointment until Agent/Admin approval.
- Customer sees the doctor pseudonym and approved public details, with no picture/private identity.
