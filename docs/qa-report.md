# ImportFlow — QA Report (Phase 6)

**Date:** 13 Oct 2026 · **Branch:** `phase-1-tenancy` · **Scope:** the 100k-row benchmark, failure and retry behaviour, the security review, dependency patching and production operations.

## Summary

| Area | Result |
|---|---|
| 100,000-row import | **CSV: 23.5 s, 46 MB peak memory.** XLSX: 31 s analysis + 78 s import. 250,000 rows (CSV): 75 s, with the same 46 MB. |
| Scaling | Linear. The original Laravel Excel chunk reader was quadratic: 100k CSV took 230 s and 250k was projected at more than 16 minutes. It was replaced by a single-pass streaming reader. |
| Failure / retry | A crash inside a chunk rolls back only that chunk. A retry finishes with exact totals and no duplicates or double-counted usage. Proven by tests that inject real database failures. |
| Security review | 7 findings: 1 high, 2 medium, 4 low. **All fixed and covered by regression tests.** No cross-tenant data path was found. |
| Dependencies | `composer audit`: 9 advisories in 4 packages → **0** after patch updates. `npm audit`: 0 in production dependencies. 12 remain in build-only devDependencies. |
| Automated tests | **255 passing** (740 assertions). |

## 1. Benchmark: 100,000-row import

**How to reproduce:** `php artisan import:benchmark --rows=100000 --format=csv --chunk=1000`. Other options: `--format=xlsx`, `--policy=update` and `--invalid=<percent>`.

The command generates a file and runs analysis and the full import through the same queued jobs a worker uses (in-process). It reports time and peak memory, then deletes everything it created.

**Environment** (development laptop, not production hardware):
- Intel Core i5-10210U (4 cores / 8 threads), 16 GB RAM, NVMe SSD, Windows 11
- PHP 8.4.24, MySQL 8.4.3 on the same machine, database queue driver
- One worker. The `contacts` dataset (6 columns), with 1% of rows deliberately invalid.

### After: streaming reader (current code)

| File | Chunk | Analysis | Import | Throughput | Peak memory | Result |
|---|---|---|---|---|---|---|
| 100k CSV (7.3 MB) | 500 | 0.5 s | 30.7 s | 3,262 rows/s | 40 MB | 99,000 added / 1,000 rejected |
| **100k CSV** | **1000 (default)** | **0.5 s** | **23.5 s** | **4,252 rows/s** | **46 MB** | 99,000 / 1,000 |
| 100k CSV | 2000 | 0.5 s | 29.5 s | 3,387 rows/s | 54 MB | 99,000 / 1,000 |
| 100k CSV | 5000 | 0.5 s | 30.5 s | 3,279 rows/s | 66 MB | 99,000 / 1,000 |
| 100k CSV, policy *update* | 1000 | 1.4 s | 43.4 s | 2,307 rows/s | 46 MB | 99,000 / 1,000 |
| 100k XLSX (2.8 MB) | 1000 | 30.9 s | 78.4 s | 1,275 rows/s | 46 MB | 99,000 / 1,000 |
| 250k CSV (18.6 MB) | 1000 | 1.4 s | 75.1 s | 3,327 rows/s | 46 MB | 247,500 / 2,500 |

**Notes on the table:**
- Rejected counts match the 1% invalid rows exactly in every run.
- The *update* run was against an empty company, so it measures the cost of the existence lookups, not of real updates.

### Before: Laravel Excel chunk reading (as of Phase 5)

| File | Chunk | Import | Throughput | Peak memory |
|---|---|---|---|---|
| 20k CSV | 1000 | 24.1 s | 831 rows/s | 56 MB |
| 100k CSV | 1000 | 229.7 s | 435 rows/s | 56 MB |
| 100k CSV | 2000 | 177.8 s | 562 rows/s | 66 MB |
| 100k CSV | 5000 | 132.3 s | 756 rows/s | 88 MB |
| 20k XLSX | 1000 | 26.8 s | 746 rows/s | 64 MB |

**Root cause.** `Maatwebsite\Excel\Jobs\ReadChunk` calls `$reader->load($file)` for every chunk. Each chunk therefore re-reads the file from the start, so the total work grows with rows². Five times the rows took ten times as long, and larger chunks only hid the problem.

**Fix.** `app/Imports/Readers/*` reads the file once, top to bottom, keeping one row in memory:
- **CSV:** `fgetcsv`. It also strips the BOM, detects `;`, tab and `|` delimiters, and converts Windows-1252 to UTF-8.
- **XLSX:** OpenSpout.
- **XLS:** PhpSpreadsheet. A single load, because the format holds at most 65,536 rows.

Chunk identity (`start_row`) is unchanged, so the retry logic is untouched.

**Chunk size.** 1000 is the fastest and stays the default. It is configurable with `IMPORT_CHUNK_SIZE`. Each import records the size it started with, so changing the setting never misaligns a retry.

**Known limitations:**
- **XLSX analysis reads the whole file to count rows** (about 31 s per 100k rows), so the user waits longer before the mapping screen appears. A possible improvement is to read the sheet's `<dimension>` element when present.
- These numbers come from one worker on a laptop. Repeat the benchmark on the production server and record the results here before quoting a figure to customers.

### Bug found while benchmarking

The export of 100k rows took 67 s. PHP 8.4 emits a deprecation on every `fputcsv()` call that doesn't pass `$escape`, and Laravel handles each one. All CSV writing now goes through `Csv::writeRow()`. The export now takes about 10 s, and the error-report download had the same problem and is fixed too.

## 2. Failure and retry behaviour

Every item below is an automated test, mostly in `tests/Feature/QueueReliabilityTest.php`, `ProcessExcelImportTest.php` and `DuplicatePolicyTest.php`.

| Scenario | Verified behaviour |
|---|---|
| Database fails at the end of chunk 2's transaction | Chunk 2 rolls back completely: no records, errors, row keys or usage. Chunk 1 stays committed, and the job error surfaces so the queue retries. |
| Retry after that failure, with `IMPORT_CHUNK_SIZE` changed in between | The retry keeps the original chunk size, skips chunk 1 and finishes. Exactly 2,500 records, 2,500 distinct emails and 2,500 usage rows; the duplicate-tracking keys are cleaned up. |
| Same job delivered twice after it finished | No duplicates and no double counting. |
| Two workers on the same chunk | The second one fails on the unique `(import, start_row)` marker and rolls back. |
| Cancel while queued / running | Cancelled at once / stops before the next chunk. Committed rows are kept and reported. |
| A late failure after a cancel | The status stays *Cancelled*, and no "failed" notification is sent. |
| Unreadable, corrupt or zip-bomb file | Fails immediately, without wasting retries, with a safe message. |
| Final failure (all retries used) | Status *Failed* with a support reference (`import #id`) and no technical details. One notification. |
| Import or company deleted while queued | The job is dropped (`deleteWhenMissingModels`), not failed. |
| Queue `retry_after` vs. job timeouts | Test asserts `retry_after` > every job timeout. |

**Configuration bug fixed.** `config/queue.php` defaulted `retry_after` to **90 s** when `DB_QUEUE_RETRY_AFTER` was missing from `.env`. A running import (timeout 3600 s) would then be handed to a second worker after 90 s. The default is now 3900 s.

## 3. Security review

This was a read-only review of the whole codebase, focused on tenant isolation, authorization, injection, file handling and data exposure. **No cross-tenant read or write path was found.**

The following were checked and found safe:
- Route-model binding runs after the tenant context is set.
- Every `Rule::unique` and `Rule::exists` is scoped to the company.
- Bulk inserts set `company_id` explicitly.
- The owner and super-admin roles cannot be obtained through any request.
- Sort and filter columns are allow-listed.
- There is no unescaped output of user data.
- Invitation tokens are stored hashed, single-use and expiring.

| # | Finding | Severity | Fix | Test |
|---|---|---|---|---|
| 1 | **Account pre-hijacking.** Emails were never verified. An attacker could register a victim's address first and keep that session alive. When the real person reset the password and accepted an invitation, the attacker's session gained access to the company. | High | `User implements MustVerifyEmail`. Workspace, platform and invitation-accept routes require `verified`. `AuthenticateSession` logs out all other sessions when a password changes. | `SecurityHardeningTest` (3 tests) |
| 2 | **Worker denial of service.** A zip-bomb `.xlsx` or an enormous CSV header could exhaust worker memory, and uploads were unthrottled. | Medium | Zip entries are checked before parsing (max 100× expansion and 2 GB), CSV lines are capped at 1 MB, and the 200-column limit is enforced while reading. Uploads are throttled to 20/min, with at most 20 unconfirmed imports per company. | `SecurityHardeningTest` (3 tests) |
| 3 | **Monthly quota bypass.** Confirmed imports still in the queue were not counted, so several imports confirmed together could exceed the allowance many times over. | Medium | Rows of queued and running imports are reserved, and the check runs under a company row lock (`lockForUpdate`). | `SecurityHardeningTest` |
| 4 | **Former members were notified** about imports in a company they had been removed from. | Low | Notify only current members. | `SecurityHardeningTest` |
| 5 | **Platform admins' names and IPs** were visible to tenant owners in the audit log. | Low | Shown to tenants as "Platform admin", without IP. | `SecurityHardeningTest` |
| 6 | **Personal data in audit metadata** (full invitee emails, export search text). | Low | Emails are masked (`j***@corp.com`), and only whether a search was used is logged. | `SecurityHardeningTest` |
| 7 | `/register`, `/forgot-password` and `/reset-password` were unthrottled. | Low | `throttle:6,1`. | `SecurityHardeningTest` |

**A bug found during end-to-end checks.** CSV files that the server detects as `text/plain` were rejected at upload, for example `;`-separated files as Excel saves them in many locales. Uploads now accept `txt` content, but the file name must still be `.csv`, `.xlsx`, `.xls` or `.txt` (tested both ways).

**Still open (out of scope here):**
- A formal external penetration test before paying customers, as the blueprint recommends.
- Moving import analysis to a dedicated queue with its own memory limit.

## 4. Dependencies

- **`composer audit`:** 9 advisories in 4 packages:
  - laravel/framework, a low-severity XSS on the debug page;
  - league/commonmark, several high-severity DoS and XSS issues;
  - league/flysystem, low severity;
  - maatwebsite/excel, high severity, about export path traversal. Our exports don't use it.
- **Fix:** updated within the existing version constraints (`laravel/framework` 12.65 → 12.69.3, and others). Audit now reports **no advisories**, and all tests pass.
- **New dependency:** `openspout/openspout` 4.28 (streaming XLSX reader).
- **`npm audit`:** 0 vulnerabilities in production dependencies. The 12 remaining findings are in build tools (Vite and PostCSS toolchain). Fixing them requires `npm audit fix --force`, which brings breaking major upgrades; this is scheduled separately.

## 5. Production operations

| Item | Status |
|---|---|
| Queue workers under Supervisor | `deploy/supervisor/importflow-worker.conf`: 2 workers, timeout 3600 s, recycled hourly or at 512 MB, graceful stop. |
| Scheduler | `exports:prune` hourly, `imports:prune` daily (originals deleted after 30 days, imports never confirmed cancelled after 7 days, leftover keys removed), `queue:prune-failed` daily. |
| Backups | `deploy/backup/backup.sh` (consistent `mysqldump`, private files, 14-day retention, owner-only permissions) and `deploy/backup/restore-test.sh`. **Syntax-checked only.** No MySQL client is installed on the development machine, so neither script has been run. Do one supervised run on the server. |
| Email | **Now required** (verification and invitations). Configure `MAIL_*` before launch. |
| Debug | `APP_DEBUG=false` and `LOG_LEVEL=warning` in production. Documented in the README. The local `.env` intentionally stays in debug. |

## 6. Before onboarding pilot customers

1. Run `import:benchmark` on the production server and record the results here.
2. Run `backup.sh` and `restore-test.sh` once on the server, and schedule both.
3. Configure a real mail service and check that the verification and invitation emails arrive.
4. Create your own platform admin (`php artisan platform:admin …`). Remove the demo accounts, `platform@example.com` included.
5. Arrange an external security review or penetration test.
