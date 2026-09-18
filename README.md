# Better Talk Portal

Lightweight PHP/MySQL portal for Better Talk operations on HostBreak shared hosting.

## Scope

- Public website lead intake synchronization
- Admin/agent/doctor portal operations
- Client, case, lead, appointment, payment, and call-history workflows
- Provider-adapter style IVR/calling integration for Jazz/Zong later

## Hosting constraints

The current production target is HostBreak shared cPanel hosting, so this app is intentionally conservative:

- PHP/MySQL
- No always-running Node/.NET service
- No background worker dependency
- Server-rendered portal pages
- Migrations stored as SQL files

## Important security rule

Do not commit production credentials.

Use `portal/app/config.example.php` as the template and keep the real `portal/app/config.php` only on the server.

## Main folders

| Folder | Purpose |
|---|---|
| `portal/` | Live PHP portal application source |
| `portal/sql/` | Schema, seed, and migration files |
| `docs/` | Better Talk source requirements and status files |
| `deployment/` | Deployment notes/scripts that are safe to commit |

## Current deployment note

Task 1 lead-sync code has been deployed manually to HostBreak. The production file was backed up as:

`index.php.pre-task1-20260917005828`

End-to-end test submission still needs completion from a stable browser/client path.

## Appointment module development

The appointment foundation is implemented on a feature branch and is not a production deployment.

1. Back up the portal database.
2. Run `portal/sql/appointment_module_migration.sql` once after `lead_sync_migration.sql`.
3. Install Easy!Appointments separately on the same HostBreak account or another approved host using PHP 8.2+, MySQL, and HTTPS.
4. Create the four Easy!Appointments services (15/30/45/60 minutes), map their IDs in `appointment_services`, and map each doctor to an Easy!Appointments provider.
5. Add the `easyappointments` values to the server-only `portal/app/config.php`.
6. Assign each doctor their permitted rows in `doctor_appointment_services`.
7. Test payment gate, availability, 15-minute holds, concurrent agents, expiry, scheduling, synchronization, and role restrictions before deployment is marked complete.

The portal remains the operational source of truth. A locally scheduled appointment is retained with a visible sync failure if Easy!Appointments is temporarily unavailable, so the slot stays blocked and Admin can retry safely.
