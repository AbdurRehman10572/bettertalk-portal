# Better Talk — Prioritized Pending Tasks

## P0 — Verify the Core Journey

| Task | Acceptance Criterion |
|---|---|
| Test website lead sync | One test submission appears once with correct fields/source |
| Confirm client/lead IDs | Persistent Client ID and separate Lead ID are visible/linkable |
| Confirm role security | Admin, agent, and doctor see only permitted functions |
| Confirm audit trail | Status/assignment/payment changes show actor and time |

## P1 — Complete Portal Operations

| Task | Acceptance Criterion |
|---|---|
| Lead statuses | Authorized users can use controlled statuses; history retained |
| User management | Admin can add/edit/deactivate agents and doctors |
| Doctor profiles | Specialty, gender, availability, active/calling status supported |
| Agent notes | Intake and operational notes save on client/lead timeline |
| Payment management | Request, proof, provider reference, status, and verifier supported |
| Appointment module | Agent schedules doctor without conflicts; full lifecycle works |
| Doctor workspace | Doctor sees assigned appointments and initiates direct calls |

## P2 — Complete Cross-System Linkage

| Task | Acceptance Criterion |
|---|---|
| Call ID integration | Initial and doctor calls link to correct client/appointment |
| Payment references | Link/payment can be traced to client, call, and appointment |
| WhatsApp lead creation | Screening data creates/updates correct portal records |
| IVR lead creation | Incoming call finds/creates client and saves Call ID |
| Returning-client search | Staff can retrieve history by phone and IDs/references |

## P3 — QA and Operational Readiness

| Task | Acceptance Criterion |
|---|---|
| Permission testing | Negative tests prove blocked access for every role |
| Duplicate handling | Repeat form/call does not corrupt or duplicate client identity |
| Scheduling edge cases | Overlap, leave, cancellation, and reschedule tested |
| Payment exceptions | Failed, expired, duplicate, mismatch, refund tested |
| Mobile usability | Agent/doctor core journey works on phone-sized screens |
| Backup/export | Authorized recovery/export method documented and tested |
| Privacy review | Sensitive notes, recordings, proofs, and exports restricted |

## Business Decisions Still Needed

- Final V1 price and introductory offer.
- Exact two questions for all WhatsApp categories not already finalized.
- Final payment providers enabled at launch.
- Refund/override authority and rules.
- Call recording announcement, consent, and retention policy.
- Doctor-session note visibility and retention.
- SMS wording and appointment reminder method.

## Recommended Work Instruction

> Review the current Better Talk website and `portal.bettertalk.pk` against the files in this documentation set. Start with P0, then implement P1 in priority order. Do not redesign completed features unnecessarily. Test each role and update `07_CURRENT_STATUS.md` with Complete / Failed / Pending / Blocked and concise evidence. Stop only for credentials, destructive actions, or unresolved business decisions.

