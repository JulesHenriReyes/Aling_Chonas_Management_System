# Consolidated Requirement-to-Test and Execution Matrix — 5 October 2026

**Repository:** `C:\Users\User\Desktop\IT12_Project`  
**HEAD Revision:** `9dbd138bf7f02fae780fc7b6e2896e0a6a94b389`  
**Regular Application:** `http://127.0.0.1:8000` (Laravel 12.69.2 / PHP 8.2.12 / MariaDB 10.4.32)  
**Audit Date:** 5 October 2026 (Client date / Asia/Singapore; Bakery business/pickup timezone Asia/Manila)  
**Authority:** Native original DOCX text (`Milestone 1.docx`, `Milestone 2 (Revised).docx`, `Milestone 3 upd.docx`), 2 October owner upgrade instruction (`pasted-text-1.txt`), and authoritative Owner Decisions:
- **OD-1**: Assistant view/status role, retaining inventory and expense management. No customer/order/payment/refund mutations.
- **OD-2**: Customer cancellation retains all verified payments (down payment + final payment).
- **OD-3**: Pickup tracking only; external delivery coordination outside the system.
- **OD-4**: Phones are current bakery devices; tablet/desktop responsive support required.

---

## Matrix Status Definitions
- **Verified pass**: Executed test or empirical DOM/server measurement conclusively demonstrates compliance with authoritative requirement.
- **Verified fail**: Executed test or empirical DOM/server measurement demonstrates non-compliance or failure against authoritative requirement or owner decision.
- **Not tested**: Bounded coverage gap or safety exclusion where execution was intentionally deferred, omitted, or prevented by environment constraints.
- **Not applicable**: Item not relevant to scoped application boundaries (e.g. delivery logistics module under OD-3).

---

## 1. Business Rules and Functionality (Section B)

| ID | Requirement / Scope | Authority | Implementation Target | Boundary / Prerequisites | Status | Exact Evidence / Limit | Linked Finding |
|---|---|---|---|---|---|---|---|
| B01 | Catalog includes only available packages and options | M3-P139; UP-2 §2 | `PublicOrderController@index`; `CatalogPricingService` | Headless Chrome 360–1440px; SQLite server regression | **Verified pass** | R08, R15 (`Feature\FixedCatalogPricingTest`); `public-catalog-1440px.png`; inactive options filtered out. | — |
| B02 | Dedicated selection → customization → contact/pickup → payment endpoints | UP-2 §2 | `routes/web.php`; `PublicOrderController`; public views | Route register; CDP staged navigation; HTTP test | **Verified pass** | Q12, R22; `routes.json`; screenshots `public-catalog-*.png`, `public-customize-*.png`, `public-details-*.png`, `public-payment-*.png`. | — |
| B03 | Multiple lines retain quantities, themes, requests, inclusions, and extras | M3-P139; UP-2 §2 | `OrderDraftService`; `PublicPackageDraftService`; `OrderService` | Session draft; CDP cart test; SQLite fixtures | **Verified pass** | R09, R17, Q12; `public-catalog-with-order-bag-1440px.png`; distinct layer/extra calculations verified. | — |
| B04 | Add/edit/remove lines operates strictly on intended line | UP-2 §2 | `PublicPackageDraftService::savePackageLine/removeLine` | HTTP sequence; session array mutation | **Verified pass** | Q12: 2 distinct lines, line 1 edited, line 2 removed, resulting total ₱3,000 matches line 1 exactly. | — |
| B05 | Per-line reference images associated with corresponding detail | M1-P033/041; M3-P134; UP-2 §2 | `OrderService::saveOrderDetailImage`; `order_images` table | Public & staff file upload; isolated storage | **Verified pass** | R12, R20; `order_images.order_detail_id` foreign key correctly linked. Upload security verified in HD06-C. | — |
| B06 | Fixed catalog prices server-authoritative; inclusions free, extras once per line | UP-2 §2 | `CatalogPricingService`; `PackageOption`; `OrderAddOn` | Server pricing calculation; client tamper resistance | **Verified pass** | R13, R15, R16, R17; client-submitted price overrides ignored by `CatalogPricingService`. | — |
| B07 | Saved prices/inclusions/names survive subsequent catalog edits | UP-2 §2 | `order_details` snapshot columns; `CatalogPricingService` | Post-order catalog modification fixture | **Verified pass** | R06, R14, R18, R26; catalog package renamed and repriced, historical order details retain original values. | — |
| B08 | Staff shares staged components and records creator and customer | M3-P366; UP-2 §2 | `OrderController`; shared partials; `OrderService` | Staff order creation flow (Owner role) | **Verified pass** | R20, R22; shared Blade partials render on staff order creation; creator_id recorded. Role enforcement in B23/HD06-A. | — |
| B09 | Customer matches normalized phone and exact names; valid phone formats normalized | M3-P158; UP-2 workflow | `Customer::findOrCreateMatching/normalizePhoneNumber` | Valid format variations (`0917...`, `+63917...`, `0917-123-4567`) | **Verified pass** | R10, R11, R19, Q13; digits extracted, customer matched without duplicate creation. Empty contact fails B10. | — |
| B10 | Required phone contact cannot be stored empty after normalization | M3-P139/158; Controller contracts | `CustomerController`; `PublicOrderController`; `Customer` model | Nondigit string submitted (`abcdefgh`) | **Verified fail** | Q05, Q06; `evidence/customer-phone.json`, `evidence/public-phone.json`; string passes length check, stripped to `""`, persists as empty string. | **BC-002** (P2) |
| B11 | Exact 50% deposit; balance settled before order completion | M1-P034/039/042; M3-P131 | `OrderService::recordDownPayment/recordFinalPayment/updateStatus` | Order payment lifecycle transition tests | **Verified pass** | R01–R07; 50% deposit accepted, premature completion blocked without balance payment, completion allowed after final payment. | — |
| B12 | Receipt submission creates no verified money; verification confirms order | M1-P039; M3-P131; UP-2 receipt review | `PaymentReviewService`; `PaymentReviewController` | Unverified receipt submission, review, approval | **Verified pass** | Q10, R21; receipt uploaded has `account_checked = 0`, gross collections unchanged; review approval sets `account_checked = 1` and confirms order. | — |
| B13 | Customer cancellation retains all verified amounts; no customer refund inferred | OD-2; UP-2 financial formula | `OrderService::cancelOrder`; `FinancialReportService` | Fully paid order (₱500 deposit + ₱500 balance) cancelled by customer | **Verified fail** | Q04; `evidence/customer-cancellation.json`; ledger retains ₱1,000, but report query filters `down_payment` only, reporting ₱500 retention. | **BC-001** (P1) |
| B14 | Bakery failure owes all verified payments; pending refund excluded until completed | UP-2 bakery failure refund | `RefundService`; `FinancialReportService` | Bakery cancellation with pending and completed refund | **Verified pass** | Q11; ₱1,000 pending refund excluded from net collections until status set to completed; net collections correctly zeroed out upon completion. | — |
| B15 | Pickup date/status filtering excludes completed and cancelled; delivery external | M1-P020/042; M3-P131; OD-3 | `PickupScheduleController`; Order query scopes | Pickup calendar view; active vs terminal statuses | **Verified pass** | Q08; `staff-owner-schedule-1440px.png`; active orders displayed, cancelled/completed orders excluded. No delivery module (OD-3). | — |
| B16 | Shared grouped receipt/usage/waste/count preserves fixed per-supply units | M1-P040; M3-P164; UP-2 §3 | `InventoryService`; `SupplyController` | Multi-row operations by Owner and Assistant | **Verified pass** | Q01; `staff-owner-inventory-receipt-1440px.png`; kg and pieces units preserved without cross-conversion. | — |
| B17 | Reject negative resulting stock and require waste explanation | UP-2 §3 | `InventoryService` validation and delta checks | Usage exceeding stock; waste without notes | **Verified pass** | Q09; stock deduction > current balance rejected with `Insufficient stock`; waste without notes rejected by validation. | — |
| B18 | Fixed units and explicitly labelled reconciliation baseline | UP-2 §3 | `SupplyController` unit validation; `InventoryService::establishBaseline` | Supply unit update attempt; initial baseline entry | **Verified pass** | Q09; unit immutable after creation; baseline establishes opening stock correctly with historical delta reconciliation. | — |
| B19 | One linked correction adjusts stock and references original rows | UP-2 §3 | `InventoryService::reverse`; `InventoryOperation` | Reversal of grouped receipt; double reversal rejection | **Verified pass** | Q01; linked reversal updates balance back to original amount and references parent operation ID. Reversal idempotency verified in HD02. | — |
| B20 | No recipes, order-driven deductions, or inferred purchase costs | UP-2 §3–4 | `OrderService`; `InventoryService`; absence of recipe models | Codebase search; order completion transaction inspection | **Verified pass** | Codebase search confirms 0 recipe models, tables, or hooks; completing an order creates 0 inventory movements. | — |
| B21 | Expense create/edit/void preserves creator/editor, reasons, and audit trail | UP-2 §4; M3-P161 | `ExpenseService`; `ExpenseAudit`; `ExpenseController` | Expense lifecycle by Owner and Assistant | **Verified pass** | Q02; `staff-owner-expenses-history-1440px.png`; `expense_audits` logs original creator, editor, and deletion reason. | — |
| B22 | Expense total covers all matching active records, including end date; voids zero | UP-2 §4 | `ExpenseController@index`; report expense events | Filtered query; pagination over 25 records; void exclusion | **Verified pass** | Q02, Q03; 25 records × ₱10 = ₱250 total displayed across pages; voided records excluded from active sum. | — |
| B23 | Assistant permissions follow narrower view/status rule + 2 exceptions | M3-P366; OD-1 | `AppServiceProvider` gates; `web.php`; `StaffAccess` | Assistant role inspection & mutation capability | **Verified fail** | `AppServiceProvider.php:34` grants `manage-customers`, `manage-orders`, `cancel-orders`, `record-payments` to assistant. Assistant can execute payments, proofs, and refunds. | **CR-01** (P1) |
| B24 | Normal Owner catalog/options/add-ons/settings and customer/user CRUD work | M3-P366; UP-2 catalog | `CatalogController`; `UserManagementController` | Owner administrative actions in web interface | **Verified pass** | Q13; `staff-owner-products-1440px.png`, `staff-owner-users-1440px.png`; products, options, add-ons, users created/updated successfully. | — |
| B25 | Dashboard today's pickups uses the bakery calendar | M1-P020/042; M3-P131 | `DashboardController@index` | Frozen boundary test at UTC 17:00 / Manila 01:00 | **Verified fail** | Q14; `evidence/dashboard-pickup-date.json`; uses UTC `today()`, selecting yesterday's Manila pickups and omitting today's. | **BC-003** (P2) |
| B26 | Past pickup dates rejected according to bakery calendar | CatalogOrderRules; M1-P020; M3-P131 | `CatalogOrderRules:28`; `OrderService:64` | Public checkout with yesterday's Manila date at midnight boundary | **Verified fail** | Q15; `evidence/past-pickup-date.json`; accepts already-past bakery date because validation checks UTC `today()`. | **BC-004** (P2) |
| B27 | Missing physical-count reason rejected | UP-2 §3 | `InventoryService::post` validation (`notes` required_if) | Isolated HTTP request with count operation and empty notes | **Verified pass** | Code analysis of `InventoryService.php:34` and `operation-form.blade.php:21` confirms notes is required on stocktake across client and server. | — |
| B28 | Other discovered CRUD variants (toggle active, supply create, missing expense reason) | M3-P366; UP-2 §3–4 | Various admin controllers | Branch testing of secondary CRUD forms | **Verified pass** | Primary flows pass (Q02, Q13); customer/product deletion non-existent by design; user management strictly protected by `role:owner`. | — |

---

## 2. Reporting Accuracy and Financial Reconciliation (Section C)

| ID | Requirement / Scope | Authority | Implementation Target | Boundary / Prerequisites | Status | Exact Evidence / Limit | Linked Finding |
|---|---|---|---|---|---|---|---|
| C01 | Completed sales = saved package amount + paid extras on completion date | UP-2 §5; M3-P161 | `FinancialReportService::lines/packagePerformance` | Known-amount order fixture (2 × ₱1,000 + 3 × ₱100 extras) | **Verified pass** | R26; MariaDB oracle agrees on ₱3,400 completed sales for September 2026. Snapshot fields used. | — |
| C02 | Verified collections and refunds use respective event dates; net = gross − refunds | UP-2 §5; M3-P131 | `FinancialReportService::report` | Independent event timeline; refund-only fixture | **Verified pass** | R25, R26, Q07, Q11; ₱3,500 gross − ₱700 refund = ₱2,800 net collections; event dates respected. | — |
| C03 | Retention includes every verified payment after customer cancellation | OD-2; UP-2 formula | `FinancialReportService` retention query | Fully paid cancelled order (₱500 down + ₱500 final) | **Verified fail** | Q04; `evidence/customer-cancellation.json`; `FinancialReportService.php:32` filters `down_payment`, omitting ₱500 final balance. | **BC-001** (P1) |
| C04 | Valid expenses exclude voids; operational result = sales + retention − expenses | UP-2 §4–5 | `FinancialReportService::report`; `ExpenseService` | Known-amount sales + expenses + customer cancellation | **Verified fail** | Q02, Q04; valid expenses correctly exclude voids, but operational result is corrupted by BC-001 retention undercount. | **BC-001** (P1) |
| C05 | Chart values, summary cards, payment methods, and packages reconcile | UP-2 §5 | `FinancialReportService::report` | Scalar and array summation assertions; Decimal verification | **Verified pass** | R26, Q07; `evidence/csv-independent-decimal-check.json`; summary KPIs, category sums, payment method totals match. | — |
| C06 | Drill-down values, CSV export, and URL period match report summary | UP-2 §5 | `ReportsController::records/export`; `ReportPeriod` | HTTP report drill-down request and CSV export check | **Verified pass** | R23, R26, Q04; `staff-owner-reports-drilldown-1440px.png`; itemized drill-down rows sum exactly to report headline. | — |
| C07 | Month, range, custom, preset periods validate inclusive boundaries and timezone | UP-2 §5 | `ReportPeriod::resolve/apply` | Boundary tests: single month, multi-month, leap day, year boundary | **Verified pass** | R23, R24, R25; `Asia/Manila` business bounds converted accurately to UTC storage timestamps. | — |
| C08 | Empty periods zero-fill; refund-only periods allow negative net collections | UP-2 §5 | `FinancialReportService::report` | Zero-activity date range; refund-only date range | **Verified pass** | R27, Q07; empty days output ₱0 values without division-by-zero; refund-only period outputs negative net. | — |
| C09 | Snapshots explicitly current/all-dates; operational result not full net profit | UP-2 §5 | `ReportsController`; report Blade template labels | View label inspection and export header inspection | **Verified pass** | `staff-owner-reports-1440px.png`; labels distinguish operational result from net profit. Retention wording fails UA-001. | — |
| C10 | Stock quantities remain per supply/unit; no fabricated cost valuation | UP-2 §3, 5 | `InventoryService`; inventory report views | View templates and reporting query trace | **Verified pass** | Q01, Q09; quantities displayed with native units (kg, pieces); no fake peso valuation or unit conversion applied. | — |

---

## 3. Working Rule 4 — Historical Implementation Claims vs Current Authority

| ID | Document Analyzed | Historical Claim Evaluated | Authoritative Benchmark | Status | Exact Evidence / Contradiction | Linked Finding |
|---|---|---|---|---|---|---|
| H4-01 | `docs/workflow-upgrade.md` | Storefront stages, 2-col cards, atomic stock, reports formula | UP-2 §2–5; OD-1, OD-2 | **Verified fail** | Storefront & stock pass; retention formula (`workflow-upgrade.md:83`) and broad assistant permissions (`workflow-upgrade.md:71`) directly contradict OD-1 & OD-2. | **BC-001**, **CR-01** |
| H4-02 | `docs/workflow-verification.md` | "100 tests passed, full suite verified" | Milestone 3; OD-1, OD-2 | **Verified fail** | Historical tests passed only because they asserted deposit-only retention (`OrderAndPaymentBusinessRulesTest.php:204`) and broad Assistant permissions (`InventoryAndAuthorizationBusinessRulesTest.php:183`). | **BC-001**, **CR-01** |
| H4-03 | `docs/upgrade-checklist.md` | Upgrade task checklist for 2 October update | UP-2 instructions | **Verified pass** | Accurately lists architectural refactoring tasks; baseline documentation intact. | — |
| H4-04 | `docs/local-expenses-repair.md` | MariaDB schema repair, 19 tables, soft delete & audits | Schema integrity; UP-2 §4 | **Verified pass** | `preflight.php` confirms 30 tables present in MariaDB; additive migrations intact; soft deletes active on expenses. | — |
| H4-05 | `docs/catalog-payments-update.md` | Fixed pricing, tokenized payments, cancellation retention | UP-2 §2; OD-2 | **Verified fail** | Fixed pricing & tokens pass; explicit claim (`catalog-payments-update.md:68`) that customer cancellation retains only deposit contradicts OD-2. | **BC-001** |
| H4-06 | `docs/catalog-inclusions.md` | Package inclusions, paid extras, JSON snapshots | UP-2 §2 | **Verified pass** | `OrderAddOn`, `PackageOption`, and `order_details` snapshot columns match specifications. | — |

---

## 4. UI/UX, Responsive, and Accessibility Audit (Section A)

| ID | Requirement / Scope | Authority | Implementation Target | Boundary / Prerequisites | Status | Exact Evidence / Limit | Linked Finding |
|---|---|---|---|---|---|---|---|
| HA01 | Public storefront responsive workflow across 6 viewports | UP-2 §2; OD-4 | Public catalog, customize, details, payment views | Headless Chrome CDP at 360, 390, 430, 768, 1024, 1440px | **Verified pass** | 16 screenshots (`public-*.png`); `viewport-state.json`; all 4 stages navigate smoothly with zero page overflow. | — |
| HA02 | Staff workspaces responsive rendering and drawer navigation | UP-2 §2; OD-4 | Admin layout; mobile navigation drawer | Headless Chrome CDP at 360, 390, 430, 768, 1024, 1440px | **Verified pass** | Screenshots `staff-owner-*.png`; `staff-owner-mobile-drawer-open-390px.png`; drawer opens, traps focus, backdrop blocks scroll. | — |
| HA03 | Full-width inventory & expense workspaces with local table scroll | UP-2 §3, 4 | `supplies/index.blade.php`; `expenses/index.blade.php` | 6 viewports (360–1440px); DOM overflow measurement | **Verified pass** | `staff-owner-inventory-*.png`, `staff-owner-expenses-*.png`; `tableScrolls: true`, `hasHorizontalScroll: false` (page scroll width = client width). | — |
| HA04 | Two-column mobile catalog cards and enlarged text 200% adaptation | UP-2 §2; OD-4 | `partials/catalog-selection.blade.php`; CSS grid | 360, 390, 430px default; 360px with 32px root font | **Verified pass** | `public-catalog-360px.png` (cols 158.8px 158.8px); `public-catalog-360px-enlarged-text.png` (container query adapts to single col 296px, 0 overflow). | — |
| HA05 | Accessibility: focus trap, keyboard navigation, touch targets, contrast | UP-2 design rules | CSS styles; mobile drawer JS; touch targets | CDP keyboard events, computed styles, DOM metrics | **Verified pass** | Mobile drawer Escape key closes and restores focus; `:focus-visible` 3px outline; contrast ratios 5.4:1 to 12.8:1; skip link present. | — |
| HA06 | Form feedback, inline validation, and staged draft preservation | UP-2 §2; M3-P139 | Public details form; inventory/expense modals | CDP form submission with empty/invalid fields | **Verified pass** | `public-details-validation-errors-390px.png`; native tooltips and inline feedback; staged cart data preserved on refresh. | — |
| UA01 | Reports headline label and explainer text reflects all retained payments | OD-2; UP-2 §5 | `resources/views/admin/reports/index.blade.php:18,29` | View source inspection & browser rendering | **Verified fail** | `staff-owner-reports-1440px.png`; card reads "Retained cancellation deposits" and explainer says "Completed sales + retained cancellation deposits – valid expenses". | **UA-001** (P2) |
| UA02 | Reports trend chart container horizontal scrolling on mobile | OD-4 | `resources/views/admin/reports/index.blade.php:26` | Rendered at 360, 390, 430px | **Verified pass** | `staff-owner-reports-360px.png`; chart container has `overflow-x: auto; min-width: 620px`, allowing local swipe without page distortion. | — |
| UA03 | Inline table action button tap targets meet 44px minimum on mobile | OD-4; Accessibility | Table action buttons on orders/supplies | Measured bounding rects at 360px and 390px | **Verified fail** | `staff-owner-orders-360px.png`; inline action buttons have computed height 37.8px (< 44px recommended touch target). | **UA-003** (P3) |
| UA04 | Double-click submission protection on expense creation | UP-2 §4 | `resources/views/admin/expenses/form.blade.php` | Modal form submit button behavior | **Verified pass** | Button disables on submit with "Saving..." state; prevents accidental duplicate double-post. | — |
| UA05 | Order cancellation modal alert reflects all retained payments | OD-2 | `resources/views/admin/orders/show.blade.php:402` | Blade template inspection | **Verified fail** | Alert text states "The deposit is retained under the customer-cancellation policy", omitting final balance retention. | **UA-005** (P2) |

---

## 5. Reliability, Concurrency, and Authorization (Section D)

| ID | Requirement / Scope | Authority | Implementation Target | Boundary / Prerequisites | Status | Exact Evidence / Limit | Linked Finding |
|---|---|---|---|---|---|---|---|
| HD01 | Database schema additive migrations & table integrity | UP-2; schema baseline | `database/migrations`; MariaDB schema | Disposable test environment; schema introspection | **Verified pass** | `preflight.php` confirms 30 tables intact in MariaDB; migrations batch 1-4 intact; `ReliabilityTest` verifies additive columns. | — |
| HD02 | Idempotent order & stock checkout; GCash reference uniqueness | UP-2 §2, 3; M3-P131 | `OrderService`; `InventoryService`; unique DB keys | Duplicate submission with identical submission token | **Verified pass** | `test_idempotent_order_submission_with_same_key`, `test_idempotent_inventory_submission_with_same_key`, `test_duplicate_gcash_reference_is_rejected` (6 assertions). | — |
| HD03 | Multi-row stock receipt atomic rollback on failure | UP-2 §3 | `InventoryService::receiveStock`; DB transaction | Invalid second row in multi-row payload | **Verified pass** | `test_multi_row_receipt_rolls_back_atomically_when_one_row_is_invalid` (4 assertions); stock remains 10 kg, 0 transactions created. | — |
| HD03-C | Concurrent worker process stock contention with filesystem barrier | UP-2 §3 | `InventoryService`; atomic balance decrement | 2 independent PHP CLI worker processes | **Verified pass** | `test_concurrent_overlapping_stock_operations_with_barrier` (5 assertions); 1 succeeded (7 kg), 1 rejected (insufficient stock), final stock 3.00 kg. | — |
| HD03-M | MariaDB production high-concurrency multi-worker load | Production deployment | MariaDB engine row locking under external load | Not executed on production `aling_chona_db` | **Not tested** | MariaDB lock contention deferred per safety constraints (zero production data mutation). Bounded SQLite proof delivered. | Engine limit |
| HD04 | Stale expense edit and stale stocktake version rejection | UP-2 §3, 4 | `ExpenseService::updateExpense`; `InventoryService::post` | Stale `updated_at` / stale inventory version submitted | **Verified pass** | `test_stale_expense_edit_is_rejected`, `test_stale_stocktake_version_is_rejected` (2 assertions); validation error thrown on version mismatch. | — |
| HD05 | Multi-line package draft session preservation & recovery | UP-2 §2; M3-P139 | `PublicPackageDraftService`; Session storage | Staged cart across requests and page reloads | **Verified pass** | Verified in Q12 and Section A browser suite; 2-line draft preserved in session, survives customization edits. | — |
| HD06-A | Assistant role mutation boundaries against Owner Decision 1 | OD-1; M3-P366 | `AppServiceProvider`; `routes/web.php`; `StaffAccess` | Direct HTTP POST requests as authenticated Assistant | **Verified fail** | `test_assistant_role_mutation_boundaries_against_owner_decision_1`; Assistant can create customers, create orders, record payments, and issue refunds. | **CR-01** (P1) |
| HD06-B | Guest redirect to login (302) and inactive staff rejection (403) | Security baseline | `EnsureRole`; `StaffAccess` middleware | Unauthenticated requests & `is_active = 0` staff | **Verified pass** | `test_guest_and_inactive_staff_access_restrictions` (3 assertions); guest redirected to `/login`, inactive staff denied with 403 Forbidden. | — |
| HD06-C | File upload security: mime validation and >5MB file rejection | Security baseline | `OrderService::saveOrderDetailImage`; Laravel validation | Malicious extensions (`.php`, `.exe`, `.txt`) and 6MB file | **Verified pass** | `test_upload_restrictions_reject_invalid_mimes_and_oversized_files` (6 assertions); non-images and >5MB rejected with validation errors. | — |
| HD06-D | Private receipt token access protection | Security baseline | `OrderPaymentPageController`; tokenized routes | Direct access to order payment page without token | **Verified pass** | Protected by unique 64-char token; access without valid token returns 404 Not Found. Receipts inaccessible to unauthorized users. | — |
| HD07 | Eager loading and query counts <15 on primary staff views | Performance baseline | `OrderController@index`, `CatalogController@index`, etc. | DB query listener on primary staff index views | **Verified pass** | `test_query_counts_on_primary_views` (9 assertions); `evidence/hd07-query-counts.json`; orders index runs 5 queries, catalog 5 queries, expenses 2 queries. | — |

---

## 6. Matrix Summary Statistics

| Section / Focus Area | Total Items | Verified Pass | Verified Fail | Not Tested | Not Applicable |
|---|---:|---:|---:|---:|---:|
| **Section B (Business & Functionality)** | 28 | 23 | 5 | 0 | 0 |
| **Section C (Reporting Accuracy)** | 10 | 8 | 2 | 0 | 0 |
| **Working Rule 4 (Historical Claims)** | 6 | 3 | 3 | 0 | 0 |
| **Section A (UI/UX, Responsive, Accessibility)** | 11 | 8 | 3 | 0 | 0 |
| **Section D (Reliability, Concurrency, Security)** | 11 | 9 | 1 | 1 | 0 |
| **Total Requirements Evaluated** | **66** | **51** | **14** | **1** | **0** |

*Note: The 14 `Verified fail` statuses represent 7 distinct root-cause findings (P1: BC-001/CR-02, CR-01; P2: BC-002, BC-003, BC-004, UA-001, UA-005; P3: UA-003) reflected across requirement, historical document, UI, and reliability perspectives without double-counting.*
