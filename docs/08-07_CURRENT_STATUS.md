# Better Talk — Current Status

**Status date:** 19 September 2026  
**Important:** Deployed code and verified live functionality are tracked separately. Nothing is complete until its production acceptance test passes.

## 0. Immediate Production State

| Item | Status | Evidence / Next Check |
|---|---|---|
| Better Talk public website | Restored | A failed HostBreak frontend deployment was rolled back and the earlier live site restored |
| Contact form usability | Failed / interaction test blocked | Live DOM inspection on 17 September showed enabled inputs with normal `pointer-events`, but the Cloud Browser timed out while attempting focus/typing; the reported production failure remains open |
| Contact-form runtime | Partially verified | The live `/contact` DOM currently exposes a native form whose action is `https://portal.bettertalk.pk/lead-intake`; successful focus, typing, submission, and portal receipt remain unverified |
| Contact-form source | Updated in GitHub / deployment not confirmed | GitHub source uses the same native `POST`; do not infer production deployment only from the live DOM because the rollback/build identity has not been established |
| HostBreak build package | Complete in source / production deployment pending | Root cause of the previous blank deployment was confirmed: the old static package used `hydrateRoot(document, ...)` with an empty HTML shell. Commit `52e759d` adds a separate HostBreak SPA build using `createRoot`, Apache route fallback, and CI rejection of the incompatible hydration entry point. Main-branch CI run `35275076542` passed on 17 September 2026 |
| Lead-intake backend | Deployed / end-to-end unverified | Database migration and `/lead-intake` handler were deployed |
| Lead and duplicate testing | Pending | Submit one synthetic lead, verify IDs/source/timestamp, then repeat it to test duplicate prevention |
| `portal.catalogs.pk` | Blocked by HostBreak domain dependency | Live test on 18 September opened `https://portal.catalogs.pk/login` and displayed Better Talk. HostBreak has no redirect rule; the subdomain has the wrong document root: `/home/catalogs/public_html/portal.bettertalk.pk`. The requested source layout is now in place: `/home/catalogs/catalogs-site` for the Catalogs website and `/home/catalogs/catalogs-app` for the intended Catalogs portal. HostBreak rejects the in-place document-root update and states that `portal.catalogs.pk` cannot be deleted unless `portal.bettertalk.pk` is deleted first. Do not delete or alter the working Better Talk portal to force this change. |
| Better Talk favicon | Source updated / QA | Source points to the Better Talk logo; production favicon remains unverified after rollback |
| HostBreak/cPanel access | Restored for appointment deployment | On 18 September, authenticated HostBreak, cPanel, Backup, and phpMyAdmin access worked. The Better Talk production database was identified as `catalogs_btp`. A completed full-account backup is visible in cPanel as `backup-9.18.2026_18-05-11_catalogs.tar.gz`. The appointment migration was subsequently imported exactly once and validated successfully; do not rerun it. |

## 1. Business and Channels

| Item | Status | Note |
|---|---|---|
| Better Talk public website | Restored / functional fix required | Site is online; contact form is unusable |
| `portal.bettertalk.pk` | Reported | Deployed; admin login reported working |
| Facebook page | Complete | Page exists |
| Instagram | Complete | `@bettertalkpk` exists |
| WhatsApp Business Platform | In progress | WABA/PIN setup reported; V1 flow requires completion/testing |
| Allied Bank business account | Complete | Reported opened |
| Meezan business account | In progress | Latest reported state |
| JazzCash merchant | Complete | JazzCash Business merchant account reported active |
| Easypaisa merchant/corporate | In progress | Latest reported state |
| IVR supplier/setup | In progress | Requirements/pricing discussions ongoing |

## 2. Portal Functions

| Function | Status | Required Check |
|---|---|---|
| Admin login | Reported | Confirm production login and session security |
| Website lead sync | Partially deployed / To verify | Backend exists; fix live form, submit one synthetic lead, and trace its portal/database record |
| Lead status changes | Pending/To verify | Confirm role-based dropdown and audit history |
| User management | Pending/To verify | Confirm admin can add/edit/deactivate roles |
| Doctor management | Production schema deployed / application tests pending | PR #2 is on `main`; the production schema and Doctor ID backfill are validated. Easy!Appointments configuration plus Admin/Doctor role tests remain pending |
| Agent intake notes | Pending/To verify | Confirm structured and internal notes |
| Agent payment management | Pending/To verify | Confirm request, proof, status, reference |
| Agent doctor scheduling | Production schema deployed / application tests pending | PR #1 merged as `ba05d6a`; production schema validation passed. Easy!Appointments, role, concurrency, and live workflow tests remain pending |
| Doctor portal access | Pending/To verify | Confirm assigned appointments only |
| Doctor direct calling | Pending/To verify | Confirm direct call without agent bridging |
| Call IDs/history | Pending/To verify | Confirm linkage to client and appointment |
| Returning-client lookup | Pending/To verify | Confirm phone/ID/reference search |
| Audit logs | Pending/To verify | Confirm actor/time/before-after values |
| Easy!Appointments deployment | Installed files / setup pending | Official 1.6.0 archive hash-verified and extracted to `/public_html/portal.bettertalk.pk/scheduler`; public route now reaches EasyAppointments, which correctly reports that `config.php` is missing. Dedicated database/user exist; complete configuration and installer next. |
| 15-minute temporary slot holds | Production schema deployed / behavior pending | `appointment_holds` exists in production; concurrent-agent, expiry, conversion, and conflict tests remain pending |
| Customer login and appointment page | Pending | Approved route and access rules; not yet implemented/tested |
| Case/Agent Call/Doctor Call identifier model | Production schema deployed / linkage tests pending | Migration completed successfully; existing appointment, call, doctor, and patient backfills have zero missing required values. End-to-end linked-call tests remain pending |

## 3. Approved Requirements Already Captured

- Central client history across website, WhatsApp, IVR, payment, and appointments.
- Admin has full operational visibility.
- Agents conduct intake, manage payment, and schedule doctors.
- Doctors directly call their assigned clients through portal access.
- Call ID and payment reference link the full journey.
- Returning clients should be retrievable and may request the same doctor.
- WhatsApp V1 remains customer-initiated within the 24-hour service window.
- Better Talk Portal is the master operational system and Easy!Appointments is the integrated scheduling engine.
- Doctor availability is self-managed with Admin override; doctor durations are selected from 15/30/45/60 minutes.
- Booking uses a 15-minute temporary hold; timeout/abandonment releases the slot.
- Customer login uses normalized mobile number + password with OTP recovery and displays doctor pseudonym/public details without a picture.
- Customer cancellation/reschedule actions create requests requiring Agent/Admin approval; no automatic reminders are included in V1.
- Identifier chain is Client ID → Case ID → multiple Agent Call IDs → Payment → Doctor ID + Appointment ID → multiple Doctor Call IDs.

## 3A. Appointment Requirements Documentation — 18 September 2026

- Updated master, portal, scheduling, IVR, payment, and role requirements with the approved appointment/customer-access decisions.
- Cross-checked identifiers, permissions, payment prerequisite, 15-minute hold lifecycle, customer visibility, and no-reminder boundary across the documents.
- This evidence verifies documentation consistency only. No application code, database migration, Easy!Appointments deployment, or production behavior was tested in this documentation session.

## 3B. Appointment Foundation Implementation — 18 September 2026

- Located the `AbdurRehman10572/bettertalk-portal` source repository and created local branch `feat/appointment-foundation-20260918`.
- Local implementation commit `45bf6ed` adds an additive appointment migration, Easy!Appointments API client, payment-gated availability, permitted 15/30/45/60-minute services, atomic 15-minute holds, overlap checks, hold-to-appointment conversion, sync failure/retry tracking, portal booking routes, and focused tests/CI.
- Static PHP parsing passed for the portal entry point, both new appointment classes, configuration template, and unit-test file. `git diff --check` also passed.
- With explicit user approval, feature branch `feat/appointment-foundation-20260918` was published and pull request #1 was opened at `https://github.com/AbdurRehman10572/bettertalk-portal/pull/1`.
- GitHub Actions run `35345948594` completed successfully: native PHP syntax checks and `tests/appointment_service_test.php` passed.
- Pull request #1 was merged to `main` on 18 September 2026 as commit `ba05d6a` after successful CI. No production database, HostBreak files, or Easy!Appointments installation was changed.
- Database migration, API mapping, concurrent-agent behavior, expiry, role restrictions, and end-to-end production behavior remain **Pending** until a safe staging/production deployment is available.

## 3C. Doctor Availability Implementation — 18 September 2026

- Pull request #2 adds the Admin/Doctor availability workspace, recurring weekly plans, two break windows per day, date exceptions, leave/unavailability periods, Admin provider/service mapping, and permitted 15/30/45/60-minute durations.
- Availability changes save to the Better Talk Portal and synchronize provider working plans/unavailability records to Easy!Appointments. Failed or incomplete synchronization is visible and blocks new scheduling for the affected doctor; locally active leave also participates in conflict detection.
- New doctors receive a permanent Doctor ID and customer-facing pseudonym fields. Doctor self-service is restricted to the logged-in doctor, while Admin may select and override any doctor; changes use CSRF protection and audit events.
- GitHub Actions run `35346914933` passed native PHP syntax checks and the expanded appointment/availability tests for working plans, multiple breaks, invalid ranges, date exceptions, and Pakistan-to-UTC leave conversion.
- Pull request #2 was merged to `main` on 18 September 2026 as commit `a45d795` after successful CI. No production database migration, Easy!Appointments installation, HostBreak file, or live portal behavior was changed or tested.

## 3D. Appointment Production Migration — 18 September 2026

- Authenticated HostBreak access, one-click cPanel, and phpMyAdmin were working in this session.
- phpMyAdmin confirmed that `catalogs_btp` is the Better Talk production database; its existing tables include `appointments`, `calls`, `cases`, `doctor_profiles`, `patients`, `payments`, and `users`.
- Direct browser downloads of the phpMyAdmin/cPanel SQL backup failed because the browser download channel returned `Fetch domain is not enabled`; no local SQL file was claimed or used.
- A server-side cPanel full-account backup completed successfully and is listed as `backup-9.18.2026_18-05-11_catalogs.tar.gz` with timestamp 18 September 2026 18:05:11.
- After the pause, `portal/sql/appointment_module_migration.sql` was imported exactly once into `catalogs_btp`. phpMyAdmin reported: `Import has been successfully finished, 20 queries executed.` Only MySQL deprecation warnings were shown; no migration error occurred.
- Read-only validation passed: all 8 expected new tables exist; `BT-15`, `BT-30`, `BT-45`, and `BT-60` are active; the existing appointment has no missing Appointment ID/end time; both existing calls have call codes/kinds; the existing doctor has a Doctor ID; and both patients with normalized phones have normalized login mobiles.
- Softaculous does not offer Easy!Appointments on this host. Official stable release `1.6.0` was downloaded from the upstream GitHub release and matched the published SHA-256 `299f8da75583ea185992ece4ac0b10e98f673825edbf75116446d99001911e60`.
- The dedicated Easy!Appointments database/user and application files were created/extracted later in the same deployment sequence; see section 3E. Do not repeat those steps.

## 3E. Easy!Appointments Installation Milestone — 18 September 2026

- The hosting plan had reached its subdomain limit, so the approved installation target changed from `schedule.bettertalk.pk` to `https://portal.bettertalk.pk/scheduler`.
- Dedicated database `catalogs_ea` and user `catalogs_ea` were created; the user has full privileges on that dedicated database only. Do not expose the password in documentation, source, logs, or chat.
- The official EasyAppointments `1.6.0` archive was downloaded again on the server only after verification of its published SHA-256 (`299f8da75583ea185992ece4ac0b10e98f673825edbf75116446d99001911e60`), then extracted into `/home/catalogs/public_html/portal.bettertalk.pk/scheduler`. `storage` permissions were set writable for the hosting account.
- The existing portal front controller intercepted the scheduler route despite the physical folder. A reversible portal backup was made, then a narrow `/scheduler` hand-off was added before the portal bootstrap. This preserves existing portal routes while allowing EasyAppointments to respond.
- Live verification at `/scheduler/index.php` now reaches EasyAppointments and reports only: root `config.php` is missing. This is the expected pre-setup state.
- **Pause milestone:** do not repeat database creation, archive download, extraction, migration, or route work. Resume by copying `scheduler/config-sample.php` to `scheduler/config.php`, enter the dedicated database settings, run the EasyAppointments setup, create/configure the administrator and API access, then test Portal ↔ scheduling synchronization.

## 3F. Scheduler Integration Source Alignment — 19 September 2026

- Verified the portal source on `main`: `EasyAppointmentsClient.php` already targets `/index.php/api/v1/*` and supports both administrator Basic Auth and Bearer API-key authorization.
- Updated `portal/app/config.example.php` on branch `chore/scheduler-config-checkpoint-20260919` so the production scheduler base URL is `https://portal.bettertalk.pk/scheduler`, matching the installed HostBreak path.
- No live HostBreak file, scheduler database, administrator account, API credential, provider mapping, or appointment record was changed in this source-only step. Production setup remains pending from the existing `config.php` checkpoint.

## 3G. Exact Live Resume Contract — 19 September 2026

Before opening the Easy!Appointments setup wizard, the server-side `scheduler/config.php` must be created from the installed 1.6.0 `config-sample.php` with these non-secret values:

- `BASE_URL = 'https://portal.bettertalk.pk/scheduler'` (no trailing slash).
- `LANGUAGE = 'english'`.
- `DEBUG_MODE = false` in production.
- `DB_HOST = 'localhost'` unless HostBreak exposes a different MySQL hostname in the already-created database connection details.
- `DB_NAME = 'catalogs_ea'`.
- `DB_USERNAME = 'catalogs_ea'`.
- `DB_PASSWORD` must be taken from the existing HostBreak database-user credential and must never be committed to GitHub or copied into project documentation.

The installed Easy!Appointments 1.6.0 API exposes resources under `/index.php/api/v1/` and supports Bearer-token or Basic authentication. The Better Talk portal client already uses this path. After setup, validate API authentication before mapping providers/services.

Production acceptance sequence after setup: scheduler login works → authenticated API request succeeds → provider mapping → `BT-15/30/45/60` service mapping → availability read → one temporary hold → booking conversion → portal appointment sync. Do not mark the scheduler integration complete before this sequence passes.

## 3H. Scheduler Health Diagnostic — 19 September 2026

- Added an Admin-only `/scheduler-health` portal page in source.
- It never displays API credentials. It reports whether the portal scheduler integration is configured and, when credentials exist, performs an authenticated read-only Easy!Appointments API request against `services`.
- A successful check proves portal → scheduler HTTPS/API authentication only. Provider/service mappings and booking behavior still require separate production tests.
- This source change is not a production pass until deployed and exercised against the live scheduler.

## 3I. Scheduler Mapping Discovery — 19 September 2026

- Added Easy!Appointments provider/service discovery to the portal integration client.
- Admin Doctor Availability now loads available scheduler providers and services from the authenticated API and presents selectable mappings instead of requiring manual external-ID entry.
- If the scheduler API is not configured or unreachable, the Admin screen shows the mapping-discovery error instead of silently accepting guessed IDs.
- This remains source-only until deployed and tested against the live scheduler.

## 4. Next Status Update Method

After each implementation/testing session, move functions into `Complete`, `Failed`, `Blocked`, or `Pending`, and record test evidence such as test lead ID, test role, timestamp, and observed result. Do not store live client-sensitive data in this document.

## 4A. 17 September Production Inspection Evidence

- Fresh Cloud Browser session successfully opened the authenticated HostBreak account and confirmed the active `catalogs.pk` cPanel hosting service.
- The live Better Talk contact page rendered the expected form. DOM inspection showed enabled `name`, `phone`, `email`, and `message` controls and the correct `/lead-intake` destination.
- Focus/typing could not be conclusively tested because the browser interaction timed out and the session's tab service then became unavailable.
- GitHub source review confirmed that the portal handler creates/finds the client, creates a separate lead and case, records source/form answers/referrer/campaign/timestamp, deduplicates identical website submissions for five minutes, audits successful creation, and logs sync failures. These are source findings only until verified in production.
- The failed HostBreak artifact was inspected and confirmed to use `hydrateRoot(document, ...)` against an empty static HTML shell, explaining the earlier blank frontend.
- Pull request `#4` was created, validated, and squash-merged as commit `52e759d`. The corrected artifact uses a standalone `createRoot` entry, contains the `/lead-intake` destination, includes `.htaccess` route fallback, and passed both pull-request CI and main-branch CI (`35275076542`).
- No production hosting files, database records, or DNS records were changed during this stage. Live contact-field and end-to-end lead tests remain pending.
- A further fresh browser session on 17 September again timed out while creating a HostBreak tab; no hosting inspection or production action was possible.
- After selecting the cPanel/File Manager route, another fresh session on 17 September again timed out before a HostBreak tab could be created; the cPanel route remains unavailable.
- On 18 September, authenticated HostBreak access worked. The Domain Redirects list was empty, while the Subdomains list showed `portal.catalogs.pk` mapped to `/home/catalogs/public_html/portal.bettertalk.pk` and marked “not redirected.” This confirms a wrong shared-document-root mapping, not an HTTP redirect. The user confirmed that `catalogs.pk` and `portal.catalogs.pk` are separate applications. File Manager verified the Catalogs website is live in `/home/catalogs/public_html`; the two separate Catalogs portal candidates checked (`/home/catalogs/catalogs/public_html` and `/home/catalogs/catalogs-operations-v2`) contained no deployable portal files. No hosting mapping or files were changed.
- The same HostBreak File Manager session confirmed that `catalogs-operations-v2.zip` (44.7 KB) is present alongside the empty `/home/catalogs/catalogs-operations-v2` directory. The user authorized inspection and deployment. Extraction and subdomain remapping are pending the required action-time confirmation; no server files or mappings have changed yet.
- On 18 September, `catalogs-operations-v2.zip` was extracted into its isolated matching folder. Its contents are the Catalogs website application (`index.html`, public submission files, assets, and admin folder), so it must not be used for `portal.catalogs.pk`. The package and existing live website remain intact.
- On 18 September, the user-directed naming was applied in HostBreak File Manager: `/home/catalogs/catalogs-operations-v2` was renamed to `/home/catalogs/catalogs-site`, and `/home/catalogs/catalogs` was renamed to `/home/catalogs/catalogs-app`. `catalogs-admin-dashboard-v1.zip` was then extracted into `/home/catalogs/catalogs-app`. Its `public_html` has the same Catalogs website-style files (`index.html`, `admin`, `assets`, public submission files, and no separate portal entry point). No live `public_html` files or subdomain mappings were changed. The requested `public_html/catalogs.pk` and `public_html/portal.catalogs.pk` arrangement must not be created by moving the live primary-site root until the hosting document-root configuration and the actual portal package are confirmed.
- On 18 September, an in-place HostBreak document-root update for `portal.catalogs.pk` was attempted with the prepared `catalogs-app/public_html` target. HostBreak rejected it with an invalid generated domain (`portal.catalogs.pk.catalogs.pk`), and no mapping changed. The delete-and-recreate fallback was then attempted with user confirmation. HostBreak blocked deletion with: `You cannot delete the portal.catalogs.pk subdomain until you delete the portal.bettertalk.pk addon domain.` No domain was deleted. This establishes that the two portal domains are structurally coupled in the current HostBreak configuration; HostBreak support must separate the addon/subdomain relationship or provide a way to change the dependent subdomain's document root without deleting `portal.bettertalk.pk`.

## 5. Functionality-First Execution Order

1. Complete the existing Easy!Appointments installation at `https://portal.bettertalk.pk/scheduler`: create server-side `config.php` from the sample, enter the already-created dedicated database credentials, run setup, create/configure the administrator and API access, and keep the Better Talk Portal as the operational master.
2. Map the four services and doctor provider, configure portal API credentials, and validate Portal ↔ Easy!Appointments synchronization.
3. Deploy the CI-verified HostBreak SPA artifact while preserving the existing rollback archive, then reproduce and fix any remaining contact-form focus/typing failure with the smallest compatible production change.
4. Verify one synthetic lead end-to-end, including Client ID, Lead ID, source, timestamp, fields, and duplicate prevention.
5. Complete P0 role-security and audit-trail verification.
6. Complete P1 modules: lead statuses, user management, doctor profiles, agent notes, payment management, appointments, and doctor workspace/direct calling.
7. Complete P2 linkage: Call IDs, payment references, WhatsApp/IVR lead creation, and returning-client history.
8. Perform P3 QA, including `portal.catalogs.pk`, favicon, mobile behavior, edge cases, privacy, backup/export, and visual polish.

Do not redesign working pages or use the previously prepared static contact-page hotfix unless the user explicitly changes direction. Fix `portal.catalogs.pk` earlier only if it blocks module testing or creates an immediate security/privacy risk.
