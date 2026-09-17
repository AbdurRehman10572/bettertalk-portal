# Better Talk — Master Requirements

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
| Client | Client ID, name if available, phone, WhatsApp number, city, consent, created date |
| Lead | Lead ID, client ID, source, category, answers, status, assigned agent, timestamps |
| Call | Unique Call ID, client ID, direction, type, agent/doctor, start/end, recording reference, notes |
| Payment | Payment ID, client ID, lead/appointment, amount, method, reference, proof, status, timestamps |
| Doctor | Doctor ID, profile, gender, specialties, availability, active status, calling permission |
| Appointment | Appointment ID, client, doctor, agent, date/time/timezone, status, payment status, related Call IDs |
| User | User ID, role, permissions, active status, audit information |

## 4. Identifier and Linking Rules

- Every client must have one persistent **Client ID**.
- Every inbound/outbound call must have its own **Call ID**.
- One client can have multiple leads, calls, payments, and appointments.
- A payment request should carry a unique reference linked to the relevant client and appointment/lead.
- A doctor session call must link to its appointment and Client ID.
- Phone number matching should help find an existing client, but staff must be able to handle changed/shared numbers without overwriting the wrong person.
- Returning clients must be searchable by phone, Client ID, name, Payment ID/reference, or Call ID.
- All status changes must store who changed the status and when.

## 5. Lead Sources

| Source | Entry | Required Portal Linkage |
|---|---|---|
| Website form | Public website | Automatically create lead and client record |
| Meta WhatsApp CTA | Customer starts WhatsApp | Store source/campaign where available and screening answers |
| Incoming IVR call | Business number | Create Call ID and find/create client |
| Manual | Authorized agent/admin | Record creator and reason |
| Returning client | Any channel | Attach new interaction to existing Client ID |

## 6. Lead Statuses

Recommended V1 statuses:

`New` → `Contacted` → `Initial Call Completed` → `Payment Pending` → `Paid` → `Appointment Scheduled` → `Session Completed`

Alternate outcomes: `No Answer`, `Follow-up Required`, `Cancelled`, `Refunded`, `Closed / Not Converted`.

The developer may implement a controlled dropdown, but users must only see transitions permitted for their role.

## 7. Non-Functional Requirements

- Role-based access and least-privilege permissions.
- Mobile-friendly portal for agents and doctors.
- Search, filters, pagination, and exports where authorized.
- Audit log for assignments, status changes, payment changes, and appointment changes.
- Pakistan Standard Time displayed by default; timestamps stored consistently.
- Sensitive client and counseling information must not appear in unnecessary screens or notifications.
- Call recordings, payment proofs, and notes require controlled access.
- No silent deletion of operational records; prefer deactivate/archive with an audit trail.

## 8. V1 Boundaries

- Payments and confirmations may be manually managed by agents.
- Doctor profiles and availability may be manually maintained by admin.
- Agents manually schedule appointments.
- WhatsApp messaging remains customer-initiated within the active 24-hour window.
- Business-initiated marketing/utility templates after 24 hours are not part of V1.
- Advanced automated payment reconciliation, AI transcripts, and complex integrations may follow after the basic workflow is stable.

