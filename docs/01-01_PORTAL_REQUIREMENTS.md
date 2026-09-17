# Better Talk — Portal Requirements

## 1. Applications

| Application | Purpose |
|---|---|
| Public Better Talk website | Explain service, capture leads, and provide contact/WhatsApp actions |
| `portal.bettertalk.pk` | Internal operations for admin, agents, and doctors |

## 2. Website-to-Portal Lead Sync

When a customer submits a website form:

1. Validate the input.
2. Search for an existing client by normalized phone number.
3. Create a Client ID if no safe match exists.
4. Create a new Lead ID even when the client already exists.
5. Save source, form answers, date/time, and available campaign/referrer data.
6. Display the new lead to authorized portal users without manual re-entry.
7. Prevent accidental duplicates from repeat clicks while retaining legitimate repeat enquiries.
8. Log and alert on failed sync attempts so no lead silently disappears.

## 3. Admin Dashboard

Admin must have 100% operational visibility, subject to secure handling of credentials/secrets.

Dashboard should show:

- New and unassigned leads.
- Leads by status, source, category, and assigned agent.
- Payments pending/paid/failed/refunded.
- Appointments today/upcoming/missed/completed.
- Doctors available/unavailable and active/inactive.
- Call activity and exceptions requiring attention.

## 4. Lead Management

| Capability | Admin | Agent | Doctor |
|---|---:|---:|---:|
| View all leads | Yes | Configurable/assigned | No |
| View assigned lead | Yes | Yes | Appointment context only |
| Update lead status | Yes | Yes, allowed stages | No |
| Add intake notes | Yes | Yes | No |
| Assign/reassign agent | Yes | No | No |
| Link calls/payments/appointments | Yes | Yes | View relevant appointment |

Each lead screen should display client summary, source, screening answers, activity timeline, calls, payment state, appointments, and authorized notes.

## 5. User Management

Admin must be able to:

- Add, edit, activate, deactivate, and reset access for users.
- Assign roles: Admin, Agent/Coordinator, Doctor.
- Create doctor details: full name, phone, email, gender, specialties/categories, qualifications/experience, availability, active status, and internal notes.
- Control whether a doctor can initiate calls through the portal.
- View last login and important account actions.
- Prevent deletion of users who own history; deactivate them instead.

## 6. Agent Workspace

Agent should be able to:

- Receive/view relevant new leads.
- Call the client for initial intake.
- Add structured intake information and internal notes.
- Update permitted lead statuses.
- Send/record payment information and update payment status with proof/reference.
- View doctor profiles and availability.
- Schedule, reschedule, or cancel appointments according to policy.
- Retrieve returning-client history.

## 7. Doctor Workspace

Doctor should be able to:

- View only assigned appointments and the minimum necessary client context.
- See schedule, client contact method, category, and authorized intake notes.
- Initiate the scheduled client call through assigned calling access.
- Mark `Started`, `Completed`, `No Answer`, or `Needs Rescheduling`.
- Add protected session outcome/notes according to Better Talk policy.
- Not view unrelated clients, other doctors' schedules, or business-wide payments.

## 8. Search and History

Search should support Client ID, Call ID, appointment ID, payment reference, phone/WhatsApp number, and client name. The client profile should present a dated timeline of all linked interactions.

## 9. Acceptance Tests

- A new website submission appears once in the portal with correct source and answers.
- A repeat client creates a new lead under the correct existing client.
- Admin can create a doctor and set availability/calling access.
- Agent can move a lead through allowed statuses, record payment, and schedule a doctor.
- Doctor sees the appointment and can record the call outcome.
- Admin sees the complete audit history across all roles.
- Unauthorized roles cannot see or change restricted data.

