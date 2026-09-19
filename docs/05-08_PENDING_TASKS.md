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
| P0 | Migrate appointment database | Complete: backup `backup-9.18.2026_18-05-11_catalogs.tar.gz` is verified; migration imported once; 20 queries succeeded; 8/8 tables, four services, and existing-row backfills validated |
| P0 | Confirm deployment access | Complete: authenticated HostBreak, cPanel, Backup, and phpMyAdmin access worked on 18 September 2026; re-authenticate in the next session if the cPanel session has expired |
| P0 | Complete Easy!Appointments setup | Files and dedicated DB/user already exist at `portal.bettertalk.pk/scheduler`; create server-side `config.php`, run setup, create/configure administrator/API access, restrict raw UI, then deploy/use Admin `/scheduler-health` to verify Portal API authentication |
| P0 | Deploy and verify identifier migration | Source migration is merged; back up the database, run it once, verify existing records, then test Client ID → Case ID → Agent Calls → Payment → Doctor/Appointment → Doctor Calls |
| P0 | Deploy and verify availability/durations | Merged source implements Doctor self-service, Admin override, recurring hours, breaks, exceptions/leave, provider/service mapping, and 15/30/45/60-minute durations; migrate, configure, and role-test |
| P0 | Deploy and verify 15-minute slot holds | Source is merged; test atomic first-wins holds, interval blocking, visible expiry, schedule conversion, automatic release, and conflict response against the migrated database |
| P0 | Complete three-role synchronization | Admin, Agent, and Doctor views/actions use the same availability and appointment state |
| P0 | Build customer appointment access | `bettertalk.pk/customer-login`; normalized mobile + password; OTP recovery; appointment/history/payment view; pseudonym/no picture; controlled requests |
| P0 | End-to-end appointment tests | Payment gate → doctor allocation → hold → schedule → doctor call → completion; concurrent agents; expiry; reschedule/cancellation approval; role-negative tests |

### Current Production Deployment Checkpoint

- HostBreak/cPanel/phpMyAdmin access worked on 18 September 2026, and `catalogs_btp` was confirmed as the Better Talk production database.
- A cPanel server-side full-account backup completed and is listed as `backup-9.18.2026_18-05-11_catalogs.tar.gz` (18 September 2026 18:05:11).
- Browser-initiated SQL downloads failed with `Fetch domain is not enabled`; no local database backup was created. The server-side full-account backup is the verified recovery point.
- The migration is complete and must **not** be rerun. phpMyAdmin reported 20 successful queries; validation found all 8 expected tables and zero missing required backfill values in existing appointments, calls, doctors, or patients.
- The four active services are `BT-15`, `BT-30`, `BT-45`, and `BT-60`.
- Softaculous does not include Easy!Appointments. Official stable release `1.6.0` has been server-downloaded and SHA-256 verified before extraction.
- **Paused installation milestone:** `catalogs_ea` plus its dedicated DB user are created; the archive is extracted at `/home/catalogs/public_html/portal.bettertalk.pk/scheduler`; the public `/scheduler` route reaches EasyAppointments and reports the expected missing `config.php` precondition. A reversible portal front-controller hand-off was added solely for `/scheduler`.
- Resume with: copy `config-sample.php` to `config.php`, set `BASE_URL` to `https://portal.bettertalk.pk/scheduler`, set the dedicated database connection without recording its password in source/docs, run setup, configure administrator/API access, restrict the raw scheduler UI appropriately, and test Portal API synchronization.
- Source alignment on 19 September 2026: `portal/app/config.example.php` now points to `https://portal.bettertalk.pk/scheduler`; this is source preparation only and does not prove live scheduler configuration.
- Scheduler health diagnostic is implemented in source as Admin-only `/scheduler-health`; deploy it with the portal after live scheduler setup, then require a successful authenticated API check before provider/service mapping.

### Historical Production-Access Blocker

- A fresh Cloud Browser session opened the authenticated HostBreak account and confirmed the active hosting service.
- HostBreak one-click cPanel redirected to `cp8.mywebsitebox.com:2083` but returned `502 Bad Gateway — Connection refused`.
- The embedded HostBreak file-manager route was blocked by Cloud Browser URL policy, and later tab operations again timed out.
- The live contact form's DOM shows enabled fields and the correct `https://portal.bettertalk.pk/lead-intake` action, but focus/typing could not be conclusively exercised before the browser failed.
- The prior blank deployment was traced to an incompatible server-hydration bundle. A corrected HostBreak-only SPA artifact is now generated from main commit `52e759d`; main CI run `35275076542` passed and verified `createRoot`, `/lead-intake`, static assets, and Apache route fallback.
- At that time production had not been changed. HostBreak/cPanel/phpMyAdmin access was subsequently restored on 18 September; the contact-to-lead acceptance test still remains incomplete.
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

- Appointment foundation pull request #1 passed PHP CI and was merged to `main` as commit `ba05d6a` on 18 September 2026.
- Doctor availability pull request #2 passed native PHP syntax and expanded appointment/availability tests in GitHub Actions run `35346914933`, then merged to `main` as commit `a45d795`.
- Production backup and appointment migration are complete and validated. Easy!Appointments database/user creation, archive verification, extraction, and scheduler routing are also complete. Server-side `config.php`, setup wizard, administrator/API configuration, mappings, and integration tests remain pending.
- Completing Easy!Appointments requires authenticated HostBreak cPanel/File Manager or SFTP/SSH access to create `scheduler/config.php` and finish the setup wizard. Required non-secret values are now fixed: `BASE_URL=https://portal.bettertalk.pk/scheduler`, `DB_HOST=localhost` unless HostBreak shows otherwise, `DB_NAME=catalogs_ea`, `DB_USERNAME=catalogs_ea`, `LANGUAGE=english`, `DEBUG_MODE=false`. Use the existing database-user password only on the server; never commit or document it. Do not recreate the existing database/user or reinstall the archive.
- OTP password recovery requires an SMS provider/API configuration. Automated appointment reminders are not required.

## Recommended Work Instruction

> Review the current Better Talk website and `portal.bettertalk.pk` against the files in this documentation set. Start with P0, then implement P1 in priority order. Do not redesign completed features unnecessarily. Test each role and update `07_CURRENT_STATUS.md` with Complete / Failed / Pending / Blocked and concise evidence. Stop only for credentials, destructive actions, or unresolved business decisions.

## Production Safety Notes

- Preserve the existing live-site rollback archive before changing HostBreak files.
- Do not redeploy the previously failed incompatible frontend artifact.
- Use the smallest compatible production change and verify each functional acceptance criterion immediately.
- A synthetic lead submission and deletion/recreation of the existing `portal.catalogs.pk` DNS record require action-time confirmation.
