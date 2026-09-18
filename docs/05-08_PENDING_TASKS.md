# Better Talk — Prioritized Pending Tasks

## Current Priority — Functional Modules

Prioritize complete operational features and end-to-end modules. Defer cosmetic corrections, visual polish, favicon work, and non-blocking domain cleanup to P3 QA.

| Order | Workstream | Acceptance Criterion |
|---:|---|---|
| 1 | Restore contact-to-lead journey | Form accepts typing and one synthetic submission creates the correct linked client/lead record |
| 2 | Complete P0 controls | IDs, duplicate handling, role security, and audit trail are verified in production |
| 3 | Complete P1 portal operations | Lead, users, doctors, notes, payments, appointments, and doctor workspace operate end-to-end |
| 4 | Complete P2 system linkage | Website, WhatsApp, IVR, calls, payments, appointments, and returning-client history link correctly |
| 5 | Complete P3 QA | Edge cases, permissions, mobile use, privacy, backups, domain routing, favicon, and deferred fixes pass |

## Active Workstream — Appointment Module

| Priority | Task | Acceptance Criterion / Next Action |
|---|---|---|
| P0 | Review appointment pull request | PR #1 is open and PHP CI run `35345948594` passed; review before merge and do not deploy directly from the feature branch |
| P0 | Confirm deployment access | Confirm production database migration method and working HostBreak cPanel/SFTP or equivalent access |
| P0 | Deploy Easy!Appointments | Install a compatible open-source release on HostBreak, secure it, configure its database, and verify API access without exposing its raw staff UI to customers |
| P0 | Implement identifier migration | Client ID → Case ID → multiple Agent Call IDs → Payment → Doctor ID + Appointment ID → multiple Doctor Call IDs; preserve existing records and audit history |
| P0 | Implement availability/durations | Doctor self-service plus Admin override; recurring hours, breaks, exceptions/leave; permitted 15/30/45/60-minute durations |
| P0 | Implement 15-minute slot holds | Atomic first-wins hold, interval blocking, visible expiry, schedule conversion, automatic release, and conflict response |
| P0 | Complete three-role synchronization | Admin, Agent, and Doctor views/actions use the same availability and appointment state |
| P0 | Build customer appointment access | `bettertalk.pk/customer-login`; normalized mobile + password; OTP recovery; appointment/history/payment view; pseudonym/no picture; controlled requests |
| P0 | End-to-end appointment tests | Payment gate → doctor allocation → hold → schedule → doctor call → completion; concurrent agents; expiry; reschedule/cancellation approval; role-negative tests |

### Current Production-Access Blocker

- A fresh Cloud Browser session opened the authenticated HostBreak account and confirmed the active hosting service.
- HostBreak one-click cPanel redirected to `cp8.mywebsitebox.com:2083` but returned `502 Bad Gateway — Connection refused`.
- The embedded HostBreak file-manager route was blocked by Cloud Browser URL policy, and later tab operations again timed out.
- The live contact form's DOM shows enabled fields and the correct `https://portal.bettertalk.pk/lead-intake` action, but focus/typing could not be conclusively exercised before the browser failed.
- The prior blank deployment was traced to an incompatible server-hydration bundle. A corrected HostBreak-only SPA artifact is now generated from main commit `52e759d`; main CI run `35275076542` passed and verified `createRoot`, `/lead-intake`, static assets, and Apache route fallback.
- Production has not been changed. Resume P0 only when reliable HostBreak file access or a secure GitHub-to-HostBreak deployment route is available. Do not move to P1 while the contact-to-lead acceptance test remains incomplete.
- Latest recovery attempt also failed before tab creation; this is an environment/hosting-access blocker, not a missing business requirement.
- Option 1 (cPanel/File Manager) was selected, but the fresh cPanel attempt still timed out before access; HostBreak support must restore the cPanel endpoint or provide a working File Manager URL.
- Do not mark production work complete from source inspection or a successful build alone; verify the live URL and record evidence.
- Known QA items: `portal.catalogs.pk` currently falls through to Better Talk, and the live favicon remains incorrect/unverified.
- The `portal.catalogs.pk` cause is now verified: HostBreak shows no redirect rule; its subdomain record has the wrong document root, `/home/catalogs/public_html/portal.bettertalk.pk`. The user confirmed that `catalogs.pk` and `portal.catalogs.pk` are separate applications. The requested source folders now exist as `/home/catalogs/catalogs-site` and `/home/catalogs/catalogs-app`. Both available Catalogs archives contain the website-style package, including an `admin` area, rather than a distinct portal build. HostBreak rejects the direct document-root change and refuses to delete `portal.catalogs.pk` unless `portal.bettertalk.pk` is deleted first. Do not move the live primary website or delete/alter Better Talk. Ask HostBreak support to detach `portal.catalogs.pk` from the `portal.bettertalk.pk` addon-domain dependency, then set its document root to the approved Catalogs portal path.

## P0 — Verify the Core Journey

| Task | Acceptance Criterion |
|---|---|
| Deploy and test website lead sync | Corrected source artifact is ready and CI-tested; blocked by production file access/browser instability. Preserve the rollback archive, deploy the new artifact, verify all fields accept typing, then submit one approved synthetic lead and confirm correct fields/source |
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
| Appointment module | Active workstream above; Agent schedules doctor without conflicts and full lifecycle works |
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
| Domain isolation | `portal.catalogs.pk` serves only the Catalogs application and never Better Talk |
| Branding assets | Production favicon and cached branding assets are correct |

## Business Decisions Still Needed

- Confirm the actual Catalogs portal package or the intended portal entry point. The available `catalogs-app/public_html` package is the Catalogs website with an `admin` area, not a distinct portal application.
- Confirm whether `catalogs.pk` is permitted to change from its current primary `public_html` document root to `public_html/catalogs.pk`; do not move its live files until HostBreak confirms that mapping.
- Request HostBreak support to detach the `portal.catalogs.pk` subdomain from the `portal.bettertalk.pk` addon domain. Their panel currently blocks its deletion with that dependency and produces an invalid duplicate-domain error during an in-place root update.

- Final V1 price and introductory offer.
- Exact two questions for all WhatsApp categories not already finalized.
- Final payment providers enabled at launch.
- Refund/override authority and rules.
- Call recording announcement, consent, and retention policy.
- Doctor-session note visibility and retention.
- Customer account provisioning/initial password setup method, unless the existing portal registration flow already defines it securely.

## Appointment Implementation Blockers / Required Access

- Portal repository was located and the appointment foundation was published on `feat/appointment-foundation-20260918` with explicit user approval.
- Pull request #1 is open; native PHP syntax and appointment service tests passed in GitHub Actions run `35345948594`.
- PR review/merge and production deployment are separate actions and remain pending.
- Easy!Appointments deployment requires HostBreak cPanel/File Manager or SFTP/SSH access and permission to create/configure its database and installation path/subdomain.
- OTP password recovery requires an SMS provider/API configuration. Automated appointment reminders are not required.

## Recommended Work Instruction

> Review the current Better Talk website and `portal.bettertalk.pk` against the files in this documentation set. Start with P0, then implement P1 in priority order. Do not redesign completed features unnecessarily. Test each role and update `07_CURRENT_STATUS.md` with Complete / Failed / Pending / Blocked and concise evidence. Stop only for credentials, destructive actions, or unresolved business decisions.

## Production Safety Notes

- Preserve the existing live-site rollback archive before changing HostBreak files.
- Do not redeploy the previously failed incompatible frontend artifact.
- Use the smallest compatible production change and verify each functional acceptance criterion immediately.
- A synthetic lead submission and deletion/recreation of the existing `portal.catalogs.pk` DNS record require action-time confirmation.
