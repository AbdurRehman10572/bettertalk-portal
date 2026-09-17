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

