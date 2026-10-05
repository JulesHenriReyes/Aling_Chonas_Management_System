# Implementation report: staff confirmation before payment

Date: 5 October 2026  
Result: **Implemented locally and verified.** The planning documents remain unchanged; this report records their execution.

The flow is now **Submit request → staff confirms feasibility → exact 50% deposit → Owner verifies payment → Preparing → Ready for pickup → remaining balance at actual collection → Completed**.

Active Owners and Assistants can confirm a request after acknowledging its design, quantities, pickup schedule and capacity. The Owner can decline an unpaid request with a customer-visible reason. Review decisions record the actual reviewer and time. Confirmation creates no payment or income.

Unreviewed requests have no payment invitation, order QR access, receipt submission or deposit recording. The server enforces these rules as well as the UI. Generic status updates cannot bypass confirmation, and an unpaid approval cannot enter Preparing. Receipt rejection preserves approval and allows correction without asking the buyer to pay again. Missing QR settings withhold a new payment invitation while preserving correction of a reported transfer.

The public catalog, customization, checkout and private status pages explain review first. Staff order pages, dashboard, queues and pickup views distinguish review, awaiting deposit, receipt verification and paid bookings. Existing exact-payment, pickup settlement, cancellation, refund, reporting, phone and calendar rules remain covered by regression tests.

## Database and preservation

The additive [migration](C:/Users/User/Desktop/IT12_Project/database/migrations/2026_10_05_000001_add_order_staff_review.php) adds nullable `review_status`, `reviewed_by` and `reviewed_at` to `orders`. It was rehearsed on populated disposable SQLite and MariaDB databases, then applied to the regular local `aling_chona_db` database after a private backup.

All **six existing orders, five payments and seven receipt records** retained their old values. All existing review fields remain null, so no historical approval was invented. The migration register gained one row. Subsequent GET smoke checks changed framework sessions only; business-table hashes remained unchanged. The catalog, login and existing private order page returned HTTP 200, with payment withheld on the unreviewed request.

The backup contains schema and rows and is outside the repository. Its local path and SHA-256 are in [backup metadata](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/regular-backup.json). [Preservation evidence](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/regular-preservation.json) records the migration comparison. `.env`, `composer.lock`, unrelated tracked navigation/login/supplies work, and **946 historical QA files** retained their baseline hashes. Existing skeleton components were retained.

Existing records needing human decisions were identified without changing them:

| Local order ID | Existing state | Owner follow-up |
|---|---|---|
| 2 | Pending; reported receipt awaiting verification; no verified funds | Check the actual account. If feasible, explicitly confirm and verify the existing deposit. If impossible and funds arrived, use legacy reconciliation and full refund. |
| 4 | Pending; previously rejected receipt; no verified funds | Investigate the reported transfer before requesting another payment or declining. Receipt correction and legacy refund handling preserve the history. |
| 3 | Pending; past pickup; no receipt or verified funds | Review a revised request with the customer or decline with a reason; normal confirmation rejects the past pickup. |

Previously paid work continues under the documented compatibility rule. A narrow Owner-only action can verify an exact pre-change deposit and request its full refund atomically when fulfilment is impossible, without approving the request. Completing that refund still requires actual return of the money. Other inconsistent amounts require Owner reconciliation.

## Verification

| Check | Final result | Evidence |
|---|---|---|
| Full guarded SQLite regression, PHP 8.2.12 / PHPUnit 11.5.56 | **138 tests, 1,861 assertions; exit 0** | [Passed log](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/regression-passed.txt), [JUnit](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/regression-passed-junit.xml) |
| Selected guarded MariaDB 10.4.32 regression on port 33317 | **61 tests, 1,075 assertions; exit 0** | [Log](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/mariadb-release.txt), [JUnit](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/mariadb-release-junit.xml) |
| Real MariaDB contention | **12 cases, 24 separate guarded workers; held locks and all state/history assertions passed** | [Contention results](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/mariadb-contention.json) |
| Populated migration upgrade | SQLite and MariaDB preservation passed | [SQLite](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/migration-sqlite.json), [MariaDB](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/migration-mysql.json) |
| Rendered UI | **216 captures, seven workflow interactions, zero final failures**; widths 360/390/430/768/1024/1440; public/Owner/Assistant; eight 200% text captures | [Final browser register](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/ui-final-register.json) |
| Syntax and whitespace | 49 changed/new production, migration, view and test PHP files linted; zero failures; `git diff --check` exit 0 | [Lint results](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/verification/evidence/php-lint-complete.json) |

Browser interactions exercised acknowledgment and server errors, keyboard confirmation, Assistant approval, receipt upload, Owner verification, declined legacy history, retained checkout validation, successful customer submission, and missing QR configuration. Screenshots use synthetic data and redact bearer links. Selected screenshots were visually inspected. This is targeted desktop-browser verification; physical devices and a full accessibility certification were outside these checks.

Early failed runs remain as diagnostics. The final browser register replaces six earlier deposit-caption captures with the corrected wording. The contention JSON records all 12 cases; an inherited console summary originally said eight and has been corrected in the runner.

## Executed plan

| Package | Status and result |
|---|---|
| SC-00 | Complete — source/read-only data baseline, exception classification and preserved unrelated work. |
| SC-01 | Complete — additive metadata, shared eligibility, two-engine upgrade preservation. |
| SC-02 | Complete — authorized atomic review/decline, attribution, acknowledgment/reason and replay guards. |
| SC-03 | Complete — QR/receipt/deposit gating and verified-deposit preparation prerequisite. |
| SC-04 | Complete — review-first customer/staff controls, receipt correction and configuration handling. |
| SC-05 | Complete — four workflow queues and truthful booking/report/schedule distinctions. |
| SC-06 | Complete — compatibility and guarded legacy reconciliation; actual human decisions listed above remain operational follow-up. |
| SC-07 | Complete — functional, financial, permission, reporting and real contention checks. |
| SC-08 | Complete — targeted rendering, responsive/text resizing, keyboard/error/submit checks. |
| SC-09 | Complete — implementation evidence, migration/rollback guidance and future gateway boundary. |

| Acceptance cases | Result and principal evidence |
|---|---|
| AC-01–05 | Passed — submission and direct payment guards; `StaffConfirmationBeforePaymentTest`, ordering regression and browser submission. |
| AC-06–11 | Passed — review authorization/audit/decline/replay; new review tests and confirmation/decline contention. |
| AC-12–18 | Passed — receipt, exact verification, replacement and preparation rules; workflow/payment/receipt tests and browser interactions. |
| AC-19–21 | Passed — advance caps, atomic collection and refund distinctions; existing policy/financial/report regression and contention. |
| AC-22–24 | Passed — terminal guards, zero-money review effects and queue reconciliation; feature tests and rendered state/queue checks. |
| AC-25–28 | Passed — two-engine upgrade, paid history and legacy financial handling; rehearsal, fixture tests and actual read-only exception register. |
| AC-29 | Passed — saved links/draft/error preservation, missing settings and stale/repeated action guards; ordering/receipt tests and browser interactions. |
| AC-30 | Complete as documentation — automatic gateway implementation remains a later phase. |

## Reproduction and handoff

Run from `C:/Users/User/Desktop/IT12_Project`. The PHPUnit harness selects guarded disposable databases and separate storage before application testing. Set `BAKERY_QA_EVIDENCE_DIR` to this folder's `verification/evidence` so historic QA logs are not appended.

```powershell
& 'C:\xampp\php\php.exe' vendor/phpunit/phpunit/phpunit
```

For the selected MariaDB run, first prepare/start the disposable server and verify its actual PDO identity using this folder's helpers. Set `BAKERY_QA_MYSQL_MANIFEST` to its manifest, then run:

```powershell
& 'C:\xampp\php\php.exe' vendor/phpunit/phpunit/phpunit --filter 'StaffConfirmationBeforePaymentTest|ImplementationPolicyTest|ContactAndPickupCalendarTest|OperationalReliabilityAcceptanceTest|ApprovedWorkflowReportAcceptanceTest|FixedCatalogAndPaymentReviewTest|OrderAndPaymentBusinessRulesTest'
& 'C:\xampp\php\php.exe' docs/qa/2026-10-05/staff-confirmation-before-payment/mariadb-concurrency.php
```

The disposable server was verified against its separate port/datadir and shut down after testing; its fixture data/evidence remain. Browser helpers use the isolated preview manifest on port 8134 and require access to the application's existing Tailwind/font CDN resources. The final UI register contains each actual browser version, viewport, role, state and screenshot path.

For another existing checkout/database, release the code with the additive migration and run only the reviewed migration after backup/preflight:

```powershell
& 'C:\xampp\php\php.exe' artisan migrate --path=database/migrations/2026_10_05_000001_add_order_staff_review.php --force --no-interaction
```

Retain the review fields/history when reverting behavior. Old payment-first code assumes confirmed means paid, so reverting it after confirmed-but-unpaid requests exist needs a data-aware transition; blindly dropping the new columns would discard actual review evidence.

Manual GCash receipt verification remains the current payment method. The future automatic gateway must create a payment session/QR only after staff approval, use the saved exact deposit, record trusted verified events exactly once, and reconcile late/duplicate/cancelled-order transfers. Provider selection, callbacks, expiry/holds and automatic refunds belong to that later implementation. Capacity approval remains a staff decision.
