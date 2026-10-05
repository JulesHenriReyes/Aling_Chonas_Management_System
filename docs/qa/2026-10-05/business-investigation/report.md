# Functionality and reporting investigation — 5 October 2026

The system is **not ready to sign off against the current business requirements**. Focused server checks confirmed a customer-cancellation reporting mismatch, empty normalized phone contacts, and two pickup-calendar problems. Current regular-database totals reconcile in the two sampled periods, but those records do not exercise the fully paid customer-cancellation case.

This phase covers **B functionality/business rules and C reporting accuracy**. Per the owner's instruction, working rule **4**, section **A**, and section **D** were not executed in this phase. No browser screenshots, viewport/design/accessibility assessment, migration-readiness checks, replay/concurrency/rollback/recovery tests, or direct access/security checks are claimed. No fixes were implemented.

## Evidence and environment

| Item | Current evidence |
|---|---|
| Repository | `C:\Users\User\Desktop\IT12_Project`; HEAD `9dbd138bf7f02fae780fc7b6e2896e0a6a94b389` |
| Worktree | Existing untracked `docs/qa/` artifacts were retained. Only separate audit documents, evidence and QA support/tests were added here. |
| Regular application | `http://127.0.0.1:8000`; local Laravel 12.69.2 / PHP 8.2.12 |
| Regular database | Actual PDO database `aling_chona_db`, `mysql` connection/driver, **MariaDB 10.4.32**; read-only SELECT-based reconciliation |
| Calendar | Storage UTC; business and pickup Asia/Manila. Audit dates use the client's Asia/Singapore date. |
| Mutating tests | Disposable **SQLite `:memory:`**, actual `PRAGMA database_list` main file empty; separate runtime storage and forced uncached QA configuration |
| Roles | Synthetic Owner and Assistant fixtures; no regular staff account/password changes or live business mutations |
| Browser/preview | No browser execution or preview server created in this phase. Port 8123 was not substituted. |
| Discovery | **88 application route entries** from bootstrapped routing; public ordering/payment, auth, dashboard, customers, catalog/options/add-ons/settings, orders/payments/proofs/refunds, supplies/operations/history, expenses/audits, reports/records/export, pickup schedule and users |

Registers: [environment](evidence/environment.json), [actual routes](evidence/routes.json), [requirement/execution matrix](matrix.md), [completed checklist](checklist.md), [final verification](evidence/final-verification.json).

The test bootstrap checks the actual PDO database before Laravel's test setup can migrate/write. It rejects non-SQLite/non-disposable connections, removes the regular connection definitions, verifies connection-name consistency, and uses isolated view/storage paths. This is a safety prerequisite; it does **not** establish database reliability or migration readiness under D.

Original DOCX files were read through their native `word/document.xml`; the important rules are not sourced from an unverified old text extract. Exact text/paragraph IDs and SHA-256 hashes are in [company-original-sources.json](evidence/company-original-sources.json):

| Original | SHA-256 |
|---|---|
| Milestone 1.docx | `1ba38e06261c320d847c28c52b9c6fc93063f47c99e27d05de570bac1d307ca8` |
| Milestone 2 (Revised).docx | `c82c82cc67427392a48d43f671f9723b67e1f8634373225c4762d88392d04c69` |
| Milestone 3 upd.docx | `d1b4b27160be9862b6a3339a22ec8966740ee8af86b84d12393aece64360adbb` |

M1-P034/039/042 and M3-P131 support 50% deposits, cash/GCash, actual transaction verification and settling the balance before completion. M3-P139/158 describe centralized order/customer specifications; M3-P161/164 identify financial breakdown and stock-tracking needs. M3-P366 defines the narrower proposed Assistant role. The direct 2 October owner upgrade instructions supply the detailed staged ordering, stock, expense, reporting and design requirements; implementation-history documents were not used as authority.

## Owner decisions and requirement conflicts

| ID | Conflict / missing requirement | Decision or current limit |
|---|---|---|
| CR-01 | M2-P112 and M3-P113 describe broad daily cooperation; M3-P366 specifies narrower system permissions. Current route/gate definitions grant broader Assistant mutations. | **Resolved expectation:** Assistant views records/updates order statuses, retaining inventory/expense management. Source configuration still conflicts; direct requests deferred D. |
| CR-02 | Original company documents do not specify treatment of a final balance paid before customer cancellation. Code/report definitions recognize only deposits. | **Resolved expectation:** retain **all verified payments** for customer cancellation. Confirmed mismatch in B/C fixture below. |
| CR-03 | M1-P020/035 mentions local delivery coordination; M3-P368's scope section ends with M3-P370 “Insert text here.” | **Resolved expectation:** pickup tracking only; delivery arrangements outside the system. No delivery-module defect is asserted. |
| CR-04 | M1-P045 says no PCs/laptops; M3-P199 refers to existing personal computers/mobile devices. | **Resolved device assumption:** phones are current bakery devices; tablet/desktop responsive support remains requested. Pass to A; update documentation separately if approved. |
| CR-05 | M3's scope/limitations section is unfinished. Precise phone-number format, policy effective dates, and same-day booking cutoff are not defined. | Missing requirements, not invented rules. Rejecting a phone that becomes empty and a pickup on an already-past business date requires no invented regional format or cutoff. Clarify historical application before any policy-driven recalculation. |

Source review for CR-01: `C:\Users\User\Desktop\IT12_Project\routes\web.php:49` places customer management and payment/refund endpoints under the shared staff group, while `C:\Users\User\Desktop\IT12_Project\app\Providers\AppServiceProvider.php:34` grants both roles the management gates. Customer/payment controllers and `StaffAccess` accept active staff without the narrowed Owner boundary. **This is a source-confirmed requirement discrepancy; runtime denial/permission coverage is Not tested.** Prioritize verification of Assistant customer writes, order creation, payment/proof mutations and refund actions in D. Do not silently reinterpret financial mutations as order-status updates.

## Confirmed findings

### BC-001 — P1 — Final payment omitted from retained cancellation income

**Expected/source:** Owner decision CR-02 retains all verified payments. The approved operational formula uses retained cancellation money, separately from payment collections.

**Observed:** A synthetic ₱1,000 order has a verified ₱500 deposit and ₱500 final payment, then customer cancellation. Both ledger entries remain and no refund is created. Gross/net collections correctly show ₱1,000, but retention and operational result show **₱500**, rather than ₱1,000. Trend values, drill-down and CSV consistently copy the same undercount. Consistency does not make that business result correct.

**Reproduce:** Execute the isolated `test_fully_paid_customer_cancellation_recognizes_all_verified_retained_money` in [BusinessInvestigationTest.php](tests/BusinessInvestigationTest.php). It creates only disposable fixture records. The retained query filters `payment_type = down_payment` at `C:\Users\User\Desktop\IT12_Project\app\Services\FinancialReportService.php:32`.

**Evidence:** [observation](evidence/customer-cancellation.json), [actual synthetic CSV](evidence/customer-cancellation.csv), [independent Decimal comparison](evidence/csv-independent-decimal-check.json), [failed test output](evidence/business-qa.txt). The existing regular September/October samples have no retained cancellation amount, so they cannot prove this case.

**Proposed correction:** Align retention calculation, labels, buyer/staff cancellation explanation, drill-down/export and QA expectations with the current decision. Agree on historical policy application before recalculating earlier cancellation results. No payment records should be rewritten merely to fix a report.

### BC-002 — P2 — Required customer phone can be saved empty

**Expected/source:** Staff/public controllers require a phone contact; M3-P139/158 require organized customer/order details. A required contact should remain nonempty after the system's own normalization. No unsupported country-specific format is assumed.

**Observed:** `phone_number=abcdefgh` satisfies string/length validation, then `Customer::normalizePhoneNumber` removes all nondigits. Staff customer creation saves an empty phone. Public checkout also creates an order and customer with an empty phone. Both return successful redirects without phone validation errors.

**Reproduce:** The isolated `test_required_customer_contact_cannot_normalize_to_empty` posts the value to `/customers`; `test_public_contact_cannot_normalize_to_empty` submits a valid synthetic package through `/order` with the same value.

**Evidence:** [staff observation](evidence/customer-phone.json), [public observation](evidence/public-phone.json), [failed tests](evidence/business-qa.txt). Sources: [Customer model](C:/Users/User/Desktop/IT12_Project/app/Models/Customer.php), [staff controller](C:/Users/User/Desktop/IT12_Project/app/Http/Controllers/CustomerController.php), [public controller](C:/Users/User/Desktop/IT12_Project/app/Http/Controllers/PublicOrderController.php).

**Proposed correction:** Normalize before validating the persisted contact, reject an empty normalized value consistently across public/staff/inline create/edit, and obtain approval for any stricter phone-format rule. Do not invent a fixed national format.

### BC-003 — P2 — Dashboard “today” uses the UTC date instead of the pickup calendar

**Expected/source:** M1-P020/042 and M3-P131 require tracking agreed pickup dates; the configured pickup calendar is Asia/Manila. “Today's pickups” should select that calendar's day.

**Observed:** At frozen `2026-10-04 17:00 UTC` = `2026-10-05 01:00 Asia/Manila`, the dashboard selects the synthetic **4 October** pickup and omits the **5 October** pickup. `DashboardController@index` filters with application `today()` (UTC), rather than the pickup calendar.

**Reproduce/evidence:** `test_dashboard_today_pickups_uses_the_bakery_calendar`; [observation](evidence/dashboard-pickup-date.json) and [failed test](evidence/business-qa.txt). Source: `C:\Users\User\Desktop\IT12_Project\app\Http\Controllers\DashboardController.php`. This is a verified server-side collection error; its actual browser presentation is deferred A.

**Proposed correction:** Derive the operational date in the configured pickup timezone; check default schedule filtering and displayed operational dates for the same assumption. Those follow-up checks are recommendations, not additional confirmed failures.

### BC-004 — P2 — Public checkout accepts an already-past bakery pickup date

**Expected/source:** Existing validation explicitly rejects past pickup dates; M1-P020/035 and M3-P131 define scheduled fulfillment. The guard must interpret date/time in the configured pickup calendar.

**Observed:** At the same frozen timestamp, `/order` accepts pickup `2026-10-04 15:00 Asia/Manila` although bakery time is already `2026-10-05 01:00`. The order is created. `after_or_equal:today` and `OrderService`'s date check use the UTC date, so the previous bakery day is accepted as application “today.”

**Reproduce/evidence:** `test_new_public_order_rejects_a_past_bakery_pickup_date`; [observation](evidence/past-pickup-date.json), [failed test](evidence/business-qa.txt). Sources: [request rules](C:/Users/User/Desktop/IT12_Project/app/Http/Requests/CatalogOrderRules.php), [order service](C:/Users/User/Desktop/IT12_Project/app/Services/OrderService.php).

**Proposed correction:** Use the configured pickup calendar consistently for server validation and date controls. Same-day lead time/cutoff remains an owner decision; this finding does not invent one. Staff entry shares the rules/service, but its boundary case was not executed here.

## Fresh automated results

| Run | Actual result | Exit | Proof |
|---|---|---:|---|
| Selected existing B/C methods | **28 tests, 264 assertions, passed** | 0 | [output](evidence/business-regression.txt), [JUnit](evidence/business-regression-junit.xml), [exact method allowlist](evidence/selected-test-methods.txt) |
| Separate targeted B/C QA | **15 tests, 109 assertions; 10 passed, 5 failed** | 1 | [output](evidence/business-qa.txt), [JUnit](evidence/business-qa-junit.xml), [test source](tests/BusinessInvestigationTest.php) |
| Independent CSV Decimal check | Summary/trend/drill-down internally reconcile; current-policy retention fails | 0 for evidence checker | [comparison](evidence/csv-independent-decimal-check.json) |
| Production-code/config fingerprint | No differences in the recorded tracked-file/.env baseline | 0 | [verification](evidence/final-verification.json) |

The five failed methods correspond to four findings: BC-002 has separate staff/public reproductions. No full regression suite or JavaScript/browser/reliability suite was executed in this phase. An initial QA assertion required a float representation rather than numeric equality; that QA-only assertion was corrected and its preliminary output retained in `evidence/business-qa-before-test-assertion-correction.txt`. It is not counted as a product defect. Final counts above use the current test source.

Commands, from `C:\Users\User\Desktop\IT12_Project`:

```powershell
& 'C:\Users\User\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe' docs/qa/2026-10-05/business-investigation/build-inputs.py
& 'C:\xampp\php\php.exe' docs/qa/2026-10-05/business-investigation/route-and-report-register.php
$qaBusinessFilter = (Get-Content -LiteralPath 'docs\qa\2026-10-05\business-investigation\evidence\selected-test-filter.txt' -Raw).Trim()
& 'C:\xampp\php\php.exe' vendor/phpunit/phpunit/phpunit --configuration docs/qa/2026-10-05/business-investigation/phpunit-business.xml --testsuite 'Current regression' --filter $qaBusinessFilter --log-junit docs/qa/2026-10-05/business-investigation/evidence/business-regression-junit.xml
& 'C:\xampp\php\php.exe' vendor/phpunit/phpunit/phpunit --configuration docs/qa/2026-10-05/business-investigation/phpunit-business.xml --testsuite 'Audit additions' --log-junit docs/qa/2026-10-05/business-investigation/evidence/business-qa-junit.xml
& 'C:\Users\User\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe' docs/qa/2026-10-05/business-investigation/finalize-evidence.py
```

Do not rerun `build-inputs.py` as an end-of-audit preservation check: it creates a new baseline. `finalize-evidence.py` compares against the already recorded baseline. Before any future mutation run, revalidate the actual test-process isolation; do not rely on the XML alone. Detailed executed method/fixture evidence is in the JUnit and [in-process isolation log](evidence/test-isolation.jsonl).

## Reporting reconciliation and limits

Read-only regular MariaDB September 2026 values independently calculated from saved lines/extras, ledger payments, completed refunds, customer cancellations and active expenses agree with the report service:

| Metric | Independent amount / count | Report amount / count |
|---|---:|---:|
| Completed sales | ₱3,400 | ₱3,400 |
| Verified collections | ₱3,875 | ₱3,875 |
| Completed refunds | ₱0 | ₱0 |
| Net collections | ₱3,875 | ₱3,875 |
| Retained cancellations | ₱0 | ₱0 |
| Valid expenses | ₱100 | ₱100 |
| Operational result | ₱3,300 | ₱3,300 |
| Completed orders | 2 | 2 |

For 1–5 October 2026 the same metrics are zero in both calculations. Evidence: [regular-report-reconciliation.json](evidence/regular-report-reconciliation.json). The oracle independently computes calendar bounds with PHP DateTime and integer centavos; it does not call the report's event aggregation. No actual customer names, phone contacts or private tokens are exported.

Selected fixtures also verify saved extras/inclusions and prices, server staged ordering, image-detail associations, exact deposit/balance/lifecycle rules, ordinary stock operations by both roles, reasoned correction and baseline arithmetic, expense audit/void totals, valid Owner catalog/settings/user/customer CRUD, report categories/methods/packages/trends/HTTP drill-down/CSV, month/year/leap/timezone endpoints, zero periods and negative refund-only net collections. These are bounded server/SQLite tests, not proof of actual browser behavior or all MariaDB mutations.

Every B/C item has a status and evidence scope in [matrix.md](matrix.md). The matrix preserves all deferred design/UI and reliability work. Source evidence can establish a business definition/configuration; it cannot establish visual rendering, focus behavior, security enforcement or concurrency. No inventory valuation/recipe, delivery feature, real payment transfer, blanket readiness score or accounting-profit claim is invented.

Proposed order for review: resolve the retention/report mismatch; verify and constrain the Assistant permissions to the approved boundary; repair normalized contact validation; unify pickup calendar guards/dashboard dates; then use the next agent's A/D findings to prioritize UI/design and reliability changes. This is a proposed fix list, not permission to implement.

The next agent should use [handoff-prompt.md](handoff-prompt.md) to execute **working rule 4 + A + D**, validate evidence applicability against its actual revision, and compile the combined final report with fresh browser screenshots and explicit limits.
