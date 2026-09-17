# HostBreak Deployment Notes

## Production paths

| Item | Path |
|---|---|
| Portal document root | `/home/catalogs/public_html/portal.bettertalk.pk/` |
| Public website document root | `/home/catalogs/public_html/bettertalk.pk/` |
| Portal database | `catalogs_btp` |

## Production config

Production DB credentials must remain on HostBreak only.

Expected local template:

`portal/app/config.example.php`

Expected production-only file:

`portal/app/config.php`

## Safe deployment flow

1. Commit changes to GitHub.
2. Build or package only the files required for the task.
3. Back up the production file before replacement.
4. Apply SQL migrations once.
5. Run `php -l` on modified PHP files.
6. Verify the changed route in the live portal.
7. Update `docs/08-07_CURRENT_STATUS.md` and `docs/05-08_PENDING_TASKS.md`.

## Task 1 deployment record

Task 1 website lead synchronization migration and portal update were deployed manually on 17 September 2026.

Observed terminal output:

- `Migration applied`
- `Portal index.php updated`
- `No syntax errors detected in index.php`

Backup created:

`index.php.pre-task1-20260917005828`

Remaining verification:

- Submit labelled test lead from the public website.
- Confirm Lead ID, Client ID, source, timestamp, case link.
- Repeat the same submission once to verify duplicate prevention.

