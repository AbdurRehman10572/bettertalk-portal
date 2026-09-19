# Better Talk — Roles and Permissions

**Last approved update:** 18 September 2026

## 1. Role Summary

| Area | Admin | Agent / Coordinator | Doctor |
|---|---|---|---|
| Dashboard | Full | Own/relevant work | Own schedule |
| Users | Create/edit/deactivate all | No | Own profile, limited |
| Doctors | Full management | View profile/availability | Own profile, limited |
| Leads | All | Assigned/relevant | No general list |
| Client profile | Full operational view | Intake/operations view | Assigned minimum context |
| Initial calls | View/manage | Make/receive | No |
| Payment | Full/manage | Create/verify as permitted | Status only if needed |
| Scheduling | Full | Create/reschedule/cancel | View/confirm own |
| Doctor calls | Oversight | View status | Initiate assigned calls |
| Reports | Full | Own/team if allowed | Own activity only |
| Audit log | Full | Own actions | Own actions |
| Customer requests | Full approve/reject | Review/approve/reject | Own appointment awareness only |

## 2. Admin

- Has full visibility across business operations.
- Manages users, roles, doctor details, categories, availability, and permissions.
- Creates permanent Doctor IDs, controls customer-facing pseudonyms, and assigns each doctor's permitted 15/30/45/60-minute durations.
- Assigns/reassigns leads and appointments.
- Reviews calls, payments, exceptions, reports, and audit history.
- Can correct data through controlled actions with mandatory reasons.
- Does not need access to raw passwords, payment secrets, PINs, or OTPs.

## 3. Agent / Coordinator

- Handles initial client contact and understands requirements.
- Adds intake information and operational notes.
- Sends/records payment information and confirms payment according to permission.
- Selects doctor and schedules the final call.
- May create a 15-minute temporary slot hold while completing a booking.
- May reschedule/cancel with reason.
- Reviews and approves/rejects customer reschedule/cancellation requests.
- Does not participate in the final doctor-client call.
- Cannot manage user roles, view unrelated confidential session notes, or perform unrestricted financial overrides.

## 4. Doctor

- Sees own assigned appointments only.
- Receives the minimum necessary intake details.
- Initiates the scheduled direct call from their user access.
- Records call outcome and authorized session notes.
- May maintain own recurring availability, breaks, exceptions, and leave; Admin retains override control.
- Cannot see unrelated clients, other doctors' work, full financial data, or system administration.

## 5. Permission Controls

- Enforce permissions on the server/API, not only by hiding buttons.
- Users can hold only approved role combinations.
- Deactivated users cannot log in or initiate calls.
- Sensitive downloads and exports require explicit permission.
- Record login, assignment, status, payment, appointment, and permission changes.
- Use separate note visibility levels if clinical/private notes and agent operational notes differ.

## 6. Status Changes by Role

| Status Action | Admin | Agent | Doctor |
|---|---:|---:|---:|
| New → Contacted | Yes | Yes | No |
| Contacted → Initial Call Completed | Yes | Yes | No |
| Payment status update | Yes | Yes, permitted actions | No |
| Appointment schedule/change | Yes | Yes | Own confirmation/request |
| Temporary slot hold | Yes | Yes | No |
| Customer cancellation/reschedule approval | Yes | Yes | No |
| Session started/completed/no answer | Yes | No | Yes, own appointment |
| Refund/financial override | Yes, authorized | Restricted/No | No |
| Close/archive record | Yes | Limited with reason | No |

## 7. Customer Access (Not a Staff Role)

- Customer login at `bettertalk.pk/customer-login` uses normalized mobile number plus password; password recovery sends a newly generated temporary password to the registered customer email.
- Customer can view only their own upcoming/past appointments and permitted payment/status information.
- Customer sees the doctor's approved pseudonym and public profile information, without picture, private contact details, Call IDs, or internal/clinical notes.
- Customer may submit cancellation/reschedule requests but cannot approve them, alter appointments directly, self-book, or access staff functions.
