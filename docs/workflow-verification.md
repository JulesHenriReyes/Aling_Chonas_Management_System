# Workflow upgrade verification

Verified 2 October 2026 against the existing Laravel / Blade / Alpine application. Browser data is a separate realistic fixture, not live business data. See the [workflow and deployment guide](workflow-upgrade.md), [screenshots](workflow-screenshots.md) and [completed checklist](upgrade-checklist.md).

Follow-up: the [local Expenses repair](local-expenses-repair.md) now verifies the regular port-8000 MySQL app as well, including private backup restoration and additive schema/data preservation on a MySQL copy. The original screenshots below remain isolated-fixture evidence.

**8 October 2026 inventory follow-up:** [Ingredient expiration verification](inventory-expiration.md) supersedes the stock behavior and concurrency limits below. Final suites pass **173 SQLite tests / 2,148 assertions** and **168 MariaDB-compatible tests / 2,078 assertions**, with separate real MariaDB lock-contention evidence. Owner/Assistant screens pass at 390, 768 and 1440px. The active database's 12 original supply balances and business history were preserved through a verified-backup, clone-rehearsed migration; ten ingredient opening entries await Owner date verification. The remainder of this document records the historical 2 October verification.

**Earlier 8 October follow-up, superseded for cancellation:** the prior implementation blocked cancellation after any verified payment. The user subsequently clarified that cancellation after the verified 50% deposit must remain available with that deposit retained. The older [test output](qa/2026-10-08/ingredient-expiry/payment-cancellation-and-batches-tests.txt) and [paid-order screenshot](qa/2026-10-08/ingredient-expiry/paid-order-cancellation-blocked.jpg) record the previous behavior, not the current rule. Its unrelated batch changes remain: details expand beneath the main movement row, with measured column widths and 57px main-row heights unchanged; Enter/Space controls the disclosure. See [batch layout](qa/2026-10-08/ingredient-expiry/batch-disclosure-fixed.jpg).

**Current staff-order modernization:** Owner creation counts as approval and awaits a separately recorded Cash/GCash deposit; public requests still require review. Cancellation after exactly the verified 50% deposit is restored, retaining the original payment and counting cancellation income once. Shared package cards, customization and saved summary support separate public/staff drafts, private staff photos, scoped browser recovery and server-issued idempotent submission. The full guarded suite passes **207 SQLite tests / 2,670 assertions**; **202 MariaDB-compatible tests / 2,600 assertions** pass. Five tests explicitly create SQLite databases and are included in the complete SQLite run. Evidence: [SQLite](qa/2026-10-08/staff-order-modernization/sqlite-suite.txt), [MariaDB](qa/2026-10-08/staff-order-modernization/mariadb-compatible.txt), and [verification details](qa/2026-10-08/staff-order-modernization/README.md). All new fixtures use guarded temporary databases and storage; business data is untouched.

## Automated results

| Check | Result | Evidence |
|---|---|---|
| Full PHP regression suite | **100 tests passed, 940 assertions**, 35.61 seconds | [Complete output](verification-php-tests.txt) |
| JavaScript behavior suite | **5 tests passed**, no failures | [Complete output](verification-js-tests.txt) |
| Separate migration/concurrency run | **4 tests passed, 52 assertions**; also included in the full suite | [Focused output](verification-migration-concurrency-tests.txt) |
| Syntax / templates / whitespace | Changed PHP files and shared JavaScript parse; all Blade templates compile; `git diff --check` passes | `php -l`, `node --check`, `php artisan view:cache` followed by `view:clear`, `git diff --check` |

Commands from the project directory (use the installed PHP/Node executable paths if they are not on PATH):

```powershell
php artisan test
node --test tests/js/package-inclusions.test.mjs tests/js/ordering-controls.test.mjs
php artisan test --filter=StockConcurrencyAndMigrationTest
```

The baseline before the upgrade was 78 tests / 636 assertions. Existing ordering, fixed pricing, package inclusions, receipt privacy/verification, refunds, payment amounts and lifecycle tests remain in the passing suite.

## Requirement evidence

| Requirement | Implementation and verification |
|---|---|
| Shared bakery identity and compact workspaces | Shared cocoa/cream CSS, Inter, common controls, headers, action menus and table containers. Actual inventory, expenses and report screens inspected at all six requested widths. |
| Full-width inventory/expense tables; forms requested separately | Listing pages contain toolbar and table. Add/edit/receive/usage/count are dedicated pages. Numeric columns align right and quantities retain the supply unit. |
| Search/filter/sort/pagination/count/total | Server queries paginate operational records and eager-load relationships. Supply search/category/status/activity and expense search/category/date/status/sorting are tested. Expense matching total is tested across multiple pages. Browser no-result state verified. |
| Keyboard, focus, touch, error/loading and unsaved input | Visible focus styles; primary controls measured at least 44px tall. Mobile navigation traps focus, Escape closes and restores the opener. Local scroll regions retain full tables. Waste validation focuses the invalid field, exposes `aria-invalid`, and retains type/reason/quantity. Forms prevent repeat submits and restore their original disabled state on browser return. Unsaved forms use a navigation warning. Reduced-motion CSS is present. |
| Dedicated public catalog → customize → checkout → payment | Same-tab catalog link opens a dedicated editor. Browser completed the actual public flow into the existing unpaid payment page; no real payment sent. `PublicPackageWorkspaceTest`, `TwoStepOrderingAndReceiptScreenTest` and payment regressions pass. |
| Multiple independent lines and uploads | Browser added two different lines, edited the first, refreshed/back-navigated, preserved its image and extra, then removed the second and submitted the first. Tests verify final per-line extra/image association, upload deduplication/cleanup, validation preservation, server availability and pricing. Stable line keys, removed-line tombstones and checkout submission keys prevent duplicate/resurrected lines/orders. |
| Staff shared components | Browser selected 2-layer vanilla ×2, Puto ×1 and a theme, continued to contact/pickup and created a staff order. Saved total was PHP 3,150, deposit PHP 1,575, unpaid with no invented payment. Staff two-step, per-item images and included-item/extra regression tests pass. |
| Responsive catalog | At 360/390/430px normal text: two columns; at 768/1024px: three; at 1440px: four. Real cake images loaded with reserved square proportions. At 360px with a 32px root font (200% text), cards switch to one column and essentials remain readable without page overflow. Temporary QA font override was removed. |
| Grouped multi-supply receiving | Browser posted flour, sugar and cake boxes as one three-row receipt with supplier/reference and before/after values. Tests verify atomic posting, distinct rows, failed-row rollback, stable-key replay and both staff roles. |
| Usage/waste, stocktake and negative/stale rejection | Browser rejected waste beyond stock with preserved fields, then posted valid waste and a reasoned count. Tests cover negative-stock rejection, waste reasons, zero count and changes after opening a count form. No order-driven automatic deductions. |
| Concurrent stock integrity | Two independent PHP worker processes overlap writes on a disposable SQLite file. Concurrent receipts retain both increments; concurrent usage cannot overspend; concurrent requests with one key post once. SQLite acquires its write lock before stock reads; MySQL uses stable ordered row locks. |
| Traceable baseline, fixed units, linked corrections | Baseline = adoption quantity minus signed legacy movements, explicitly labelled reconciliation rather than a fabricated receipt. Tests reconcile opening/new/legacy quantities, reject unit reinterpretation and retain immutable movement/group history. Browser reversed a posted three-row receipt; legacy single-movement correction is tested separately with duplicate-reversal rejection. |
| Expense create/edit/void and audit | Browser created a PHP 210 invoice, edited to 225 with a reason, then voided with a reason. Three actor/time/action/before/after audit entries remain visible. Tests preserve original creator separately from editor, reject stale changes and unauthorized mutations, exclude voids from totals/reports, and prevent ordinary audit mutation. |
| Stock and expense separation | Stock operations do not write expense records or infer costs. Browser receipt detail states that actual purchases are recorded separately; test asserts receipt/usage/count leave expense totals unchanged. |
| Inclusive period selector and URL consistency | Browser verified September 2026 and September–November 2026, plus inline reversed-range validation. `ReportPeriod` is shared by page, records and CSV; URLs retain resolved filters and presets' `as_of`. Tests cover incomplete/reversed inputs, year boundaries, leap February, timezone conversion and fractional timestamps late on the ending date. |
| Financial definitions | Shared `FinancialReportService` uses saved completed sales + paid extras once; verified payment ledger by payment date; completed refunds by completion date; customer-cancellation retained deposits excluding bakery failure; valid expense dates excluding voids. Operational result is sales + retention − expenses and explicitly not accounting profit. Payment collections are never added to sales. |
| Chart/table/breakdown/drill-down/export reconciliation | Tests reconcile totals, zero-filled daily/monthly trends, categories, collection/refund methods, saved package performance, order count, supporting records and CSV. Browser September summary, exact 30-day chart table, completed-sales drill-down and downloaded CSV agreed. Longer range uses monthly trends. Empty reports are tested. |
| Scoped current snapshots and units | Current low stock/outstanding refunds are labelled separately from the historical period. Stock history displays each supply/unit and individual balances, without unlike-unit totals or invented valuations. Legacy history limits are explained. |
| Additive migrations preserve existing records | A real migration test builds the pre-upgrade schema with customers, products, users, supplies, movements, expenses, orders, order lines, payments and refunds, then applies the additive upgrade and verifies original values. Live database was neither reset nor migrated. |
| Existing stack and unrelated work | Laravel services/controllers, Blade, Alpine and shared CSS retained. No replacement frontend framework or new dependencies. Existing catalog/inclusion and payment behavior preserved. |

## Browser reconciliation example

The isolated September fixture resolves to **1–30 September 2026** in Asia/Manila:

| Figure | PHP amount / count |
|---|---:|
| Completed sales | 6,550.00 |
| Verified collections | 6,550.00 |
| Completed refunds | 0.00 |
| Net collections | 6,550.00 |
| Retained cancellation deposits | 0.00 |
| Valid expenses | 3,980.00 |
| Operational result | 2,570.00 |
| Completed order count | 6 |

Cash 3,050 + GCash 3,500 reconciled to collections 6,550. The six completed-sales records, saved package amounts, exact chart table and downloaded CSV agree. Nonzero refunds, retained cancellations, late end-date timestamps and catalog changes are covered by automated fixtures; the screenshot fixture does not exercise every financial case.

The [downloaded preview CSV](verification-report-preview.csv) is preserved with the evidence. A Decimal-based check summed its 30 daily rows and matched all eight summary fields, including completed order count.

The browser expense created/edited/voided in October does not change September's totals, and its void excludes it from October's PHP 1,500 valid expenses. Stock receipt/reversal/waste/count did not create an expense.

## Browser checks and fixes

- Six actual viewports: **360, 390, 430, 768, 1024 and 1440px**. At normal text the document width remains within the viewport; tables overflow only inside their scroll container. Screenshots are actual rendered JPEGs, not mockups.
- Supplementary checks: at 375px the catalog retains two columns with a 360px document width; at 844×390 landscape it uses three columns with an 829px document width. The 15px difference is the scrollbar. Temporary viewport overrides were reset after verification.
- Browser checks found and corrected initial select/model disagreement, inventory script registration order, dirty-form dispatch scope, browser-return disabled state, and clipping/page overflow with enlarged catalog text.
- Staff checkout and order detail reported no browser console errors in the final check. Earlier inventory errors were resolved and the final three-line receipt posted successfully.
- Contrast/focus and keyboard behavior were manually checked, with shared darker text/border tokens and labelled statuses. This is targeted accessibility verification, not a formal certification or exhaustive assistive-technology audit.

## Practical limits

Concurrency was executed on SQLite, not on a deployed MySQL server. A subsequent local repair verified migrations and preservation on a backup copy of the actual MySQL database; MySQL concurrency load remains untested. Legacy history cannot reconstruct missing historical balances or earlier expense edits; reconciliation begins explicitly at adoption. Customer drafts are session-bound. Existing CDN/font dependencies remain. The isolated payment fixture has no configured GCash QR. See the [operating guide](workflow-upgrade.md) for deployment and these limits.
# Order status presentation cleanup — October 8, 2026

- Staff confirmation appears only while a review decision is needed. Approved/rejected actor and timestamp remain in collapsed Review history; decline reasons remain visible.
- Receipt panels appear for existing proof history or an available receipt submission. Settled/terminal receipt history is collapsed, without acceptance instructions. Empty receipt panels no longer appear on pending, paid, or terminal public orders.
- Preparation guidance names the outstanding step. Paid preparing orders show pickup-balance guidance; completed/cancelled orders have no repeated Order actions card. Public progress guidance no longer asks paid orders for another deposit, and fully paid pickup guidance states no further payment is due.
- Guarded disposable SQLite regression run: **45 tests, 597 assertions passed**, covering pending, approved unpaid, deposit verified, preparing, ready, completed, cancelled, public guidance, financial gates and review permissions. Evidence: `docs/qa/2026-10-08/ingredient-expiry/order-status-cleanup-tests.txt`.
- Read-only Chrome checks confirmed pending review controls and completed-order payment history. Preparing order Review history opened with Enter and closed with Space; approval metadata remained accessible. Screenshot: `docs/qa/2026-10-08/ingredient-expiry/order-status-cleanup.jpg`. No live order records changed during verification.
# Pickup time picker verification — October 8, 2026

- Full guarded disposable SQLite suite: **194 tests, 2,345 assertions passed**. Final focused run after focus/input-safety refinements: **18 tests, 161 assertions passed**. Evidence: `docs/qa/2026-10-08/pickup-time/sqlite-regression.txt` and `pickup-time-tests.txt`.
- Public/staff HTTP and direct service paths accept 08:00, 11:59, 12:00, 17:59 and 18:00. Earlier/later, malformed, array and missing times fail without creating orders or payments. Configured non-whole-hour boundaries, saved details, validation errors and Back-to-packages persistence are covered.
- Synthetic checkout browser checks passed at actual CSS widths **390, 768 and 1440**, for customer and staff layouts. Popups remained inside the viewport with no horizontal page overflow. Screenshots and structured evidence are in `docs/qa/2026-10-08/pickup-time/`.
- Verified closing-time minute restriction, no silent rounding, compatible minute preservation, noon conversion, Enter/Done, Escape and outside dismissal, focus return, browser Back, reload draft restoration, and inline errors. An invalid saved time blocks submission and opens its picker even when the field was above the screen. The native field remains available when the picker script is missing.
- Initial page loading no longer dismisses an already-open picker; the pageshow reset is limited to cached Back/Forward restoration. The final phone screenshot shows the popup still open after loading.
- Browser previews use disposable synthetic fixtures. No live orders were submitted and no schema migration was performed. Temporary preview/asset servers were stopped after verification.
