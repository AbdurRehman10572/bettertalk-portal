# Better Talk — Master Requirements

**Last approved update:** 18 September 2026

## 1. Business Objective

Better Talk is a counseling-support service with two main lead-entry routes:

1. A client submits information or starts WhatsApp from the public website/Meta ad.
2. A client calls the business number and speaks with an agent through the IVR/call system.

Both routes must create or update one central client record in `portal.bettertalk.pk`. The portal must support intake, call history, payment tracking, appointment scheduling, doctor access, and returning-client history.

## 2. Approved Service Journey

| Stage | Owner | Required Result |
|---|---|---|
| Lead received | System | Create/search client and create lead |
| Initial contact | Agent | Understand issue and record notes |
| Payment request | Agent | Send payment details/link with reference |
| Payment confirmation | Agent | Record evidence and mark status |
| Doctor selection | Agent | Choose suitable doctor and availability |
| Appointment | Agent | Schedule doctor-client call |
| Final session | Doctor | Doctor calls client directly through assigned portal access |
| Completion | Doctor/Agent | Store outcome, notes, and status |
| Return visit | Agent | Retrieve history and offer same doctor where suitable |

The agent does not stay between the doctor and client during the scheduled session. The agent schedules and exits; the doctor initiates the call at the scheduled time.

## 3. Core Records

| Record | Minimum Information |
|---|---|
| Client | Client ID, name if available, normalized phone/login number, WhatsApp number, city, consent, created date |
| Case | Case ID, Client ID, category/issue, source, status, responsible agent, timestamps |
| Lead | Lead ID, Client ID, Case ID, source, category, answers, status, assigned agent, timestamps |
| Agent Call | Unique Agent Call ID, Case ID, Client ID, direction, agent, start/end, recording reference, notes |
| Doctor Call | Unique Doctor Call ID, Appointment ID, Case ID, Client ID, Doctor ID, start/end, recording reference, outcome, notes |
| Payment | Payment ID, Case ID, Client ID, lead/appointment, amount, method, reference, proof, status, timestamps |
| Doctor | Admin-created Doctor ID, real/internal profile, customer-facing pseudonym, gender, specialties, availability, permitted durations, active status, calling permission |
| Appointment | Appointment ID, Case ID, client, doctor, agent, date/time/timezone, duration, status, payment status, related Agent/Doctor Call IDs |
| User | User ID, role, permissions, active status, audit information |

## 4. Identifier and Linking Rules

- Every client must have one persistent **Client ID**. `User ID` is reserved for authenticated portal accounts and must not replace Client ID.
- Every distinct counseling matter/service journey must have a **Case ID**. One Client ID can have multiple Case IDs.
- All pre-appointment intake, follow-up, payment, and scheduling calls must receive unique **Agent Call IDs**. Multiple Agent Call IDs can belong to one Case ID.
- Every doctor-session call attempt must receive a unique **Doctor Call ID** linked to its Appointment ID, Case ID, Client ID, and Doctor ID. Multiple Doctor Call IDs can belong to one appointment.
- **Doctor ID** is a separate permanent entity created by Admin.
- A payment belongs to a Case ID. Successful payment enables doctor allocation and appointment scheduling, except for a reasoned Admin override.
- An Appointment ID links Client ID + Case ID + Doctor ID + assigning Agent/User ID + Payment ID and the related call records.
- IDs are never overwritten or reused; later attempts create new records while preserving history.
- A payment request should carry a unique reference linked to the relevant client, case, originating Agent Call ID, and appointment/lead when available.
- Phone number matching should help find an existing client, but staff must be able to handle changed/shared numbers without overwriting the wrong person.
- Returning clients must be searchable by phone, Client ID, Case ID, name, Payment ID/reference, Agent Call ID, Doctor Call ID, or Appointment ID.
- All status changes must store who changed the status and when.

## 5. Lead Sources

| Source | Entry | Required Portal Linkage |
|---|---|---|
| Website form | Public website | Automatically create lead and client record |
| Meta WhatsApp CTA | Customer starts WhatsApp | Store source/campaign where available and screening answers |
| Incoming IVR call | Business number | Create Call ID and find/create client |
| Manual | Authorized agent/admin | Record creator and reason |
| Returning client | Any channel | Attach new interaction to existing Client ID |

## 6. Appointment and Customer Access Rules

- The Better Talk Portal is the master operational system; open-source Easy!Appointments is the scheduling engine integrated behind it.
- Doctors may edit their own recurring availability, breaks, exceptions, and leave. Admin may edit or override any doctor's availability.
- Admin assigns each doctor one or more permitted appointment durations from `15`, `30`, `45`, and `60` minutes.
- When an agent starts the booking action for an available slot, the portal places a **15-minute temporary hold**. Other agents see the slot as unavailable during the hold.
- The first valid hold/booking transaction wins. Confirming the booking converts the hold into a scheduled appointment; timeout or abandonment releases it automatically.
- A temporary hold is not a final appointment and does not receive the final Appointment ID.
- Customer access is provided at `bettertalk.pk/customer-login` using a normalized mobile number plus password. Equivalent Pakistani phone formats must resolve safely to the same account. Forgotten-password recovery uses OTP.
- Customers can view upcoming and past appointments, including cancelled/no-answer outcomes, and payment status. They see the doctor's approved pseudonym and public profile details without a picture or private contact details.
- Customers may submit reschedule or cancellation requests. The appointment changes only after an Agent or Admin approves and processes the request.
- No automated appointment reminders are included in V1.

## 7. Lead Statuses

Recommended V1 statuses:

`New` → `Contacted` → `Initial Call Completed` → `Payment Pending` → `Paid` → `Appointment Scheduled` → `Session Completed`

Alternate outcomes: `No Answer`, `Follow-up Required`, `Cancelled`, `Refunded`, `Closed / Not Converted`.

The developer may implement a controlled dropdown, but users must only see transitions permitted for their role.

## 8. Non-Functional Requirements

- Role-based access and least-privilege permissions.
- Mobile-friendly portal for agents and doctors.
- Search, filters, pagination, and exports where authorized.
- Audit log for assignments, status changes, payment changes, and appointment changes.
- Pakistan Standard Time displayed by default; timestamps stored consistently.
- Sensitive client and counseling information must not appear in unnecessary screens or notifications.
- Call recordings, payment proofs, and notes require controlled access.
- No silent deletion of operational records; prefer deactivate/archive with an audit trail.
- Store passwords only as secure hashes. Never display or store plaintext passwords or OTPs.
- Normalize phone numbers for matching and login while preserving the originally entered display value and protecting shared/changed-number cases.

## 9. V1 Boundaries

- Payments and confirmations may be manually managed by agents.
- Doctor profiles are managed by Admin; doctors may maintain their own availability while Admin retains override control.
- Agents manually schedule appointments.
- WhatsApp messaging remains customer-initiated within the active 24-hour window.
- Business-initiated marketing/utility templates after 24 hours are not part of V1.
- Advanced automated payment reconciliation, AI transcripts, and complex integrations may follow after the basic workflow is stable.
