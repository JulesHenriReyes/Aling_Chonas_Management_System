# Consolidated QA Investigation Report — 5 October 2026

**Repository:** `C:\Users\User\Desktop\IT12_Project`  
**HEAD Revision:** `9dbd138bf7f02fae780fc7b6e2896e0a6a94b389`  
**Regular Application:** `http://127.0.0.1:8000` (Laravel 12.69.2 / PHP 8.2.12 / MariaDB 10.4.32)  
**Audit Date:** 5 October 2026 (Client date: Asia/Singapore; Bakery business/pickup timezone: Asia/Manila)  
**Audit Scope:** Full consolidation of **Working Rule 4** (historical upgrade documents), **Section A** (UI/UX, 6 viewports, accessibility), **Section D** (reliability, schema, concurrency, idempotency, stale edits, and authorization), integrated with **Section B** (business rules) and **Section C** (financial reporting).

---

## Executive Summary & Production Readiness Verdict

### Overall Verdict: **NOT READY FOR PRODUCTION SIGN-OFF**

The Aling Chona Management System codebase exhibits strong architectural patterns in staged catalog checkout, additive database migrations, atomic multi-row stock movements, idempotent submission guarding, eager database querying, and responsive mobile layout adaptation. However, the application **fails compliance** against two critical authoritative owner decisions and business requirements:

1. **P1 Blocker — Financial Reporting Undercount (BC-001 / CR-02):**  
   In `app/Services/FinancialReportService.php:32`, the query calculating retained cancellation income filters strictly for `p.payment_type = 'down_payment'`. Under Owner Decision 2 (**OD-2**), customer cancellations retain **all verified payments** (both the 50% deposit and the 50% final balance). When a fully paid order (e.g. ₱1,000) is cancelled by the customer, the ledger correctly retains ₱1,000 with zero refund, but the executive financial report recognizes only ₱500, causing a ₱500 undercount in retained revenue and distorting the bakery's operational result.
2. **P1 Blocker — Assistant Role Mutation Permissiveness (CR-01 / OD-1):**  
   In `app/Providers/AppServiceProvider.php:30-38` and `routes/web.php:49-88`, the system defines `$staffCanOperate` as granting `manage-customers`, `manage-orders`, `cancel-orders`, and `record-payments` to the `assistant` role. Direct server testing (`ReliabilityTest::test_assistant_role_mutation_boundaries_against_owner_decision_1`) proved that an Assistant can successfully create customers, create orders, and modify order financial states. This directly violates Milestone 3 (**M3-P366**) and Owner Decision 1 (**OD-1**), which require the Assistant role to be strictly view/status only for orders/customers, reserving financial and customer mutations for the Owner while retaining Assistant access solely for inventory and expense operations.
3. **P2 Secondary Deficiencies:**  
   - **BC-002**: Empty customer phone contact persisted when non-digit characters are stripped by `Customer::normalizePhoneNumber`.
   - **BC-003**: Dashboard today's pickups evaluates against application UTC `today()`, omitting Manila daytime orders at midnight UTC boundaries.
   - **BC-004**: Public checkout accepts already-past bakery pickup dates at midnight boundaries due to UTC date checks in `CatalogOrderRules.php`.
   - **UA-001**: Reports dashboard card reads "Retained cancellation deposits" and explainer text states "Completed sales + retained cancellation deposits – valid expenses", reflecting the obsolete deposit-only policy rather than all retained payments.

---

## 1. Environment, Revisions, and Sources Register

### 1.1 Application & Runtime Environment
- **Operating System:** Windows 10/11
- **PHP Version:** PHP 8.2.12 CLI (`C:\xampp\php\php.exe`)
- **Web Framework:** Laravel Framework 12.69.2
- **Regular Database:** MariaDB 10.4.32 on port 3306, database `aling_chona_db` (19 tables verified, untampered read-only verification)
- **Timezone Architecture:** Application/Storage: `UTC`; Business & Pickup Calendar: `Asia/Manila` (`config/app.php`, `config/bakery.php`)
- **Safety Boundary Enforced:** **Zero production mutations**. No source code files in `app/`, `routes/`, `resources/`, `database/`, or `config/` were altered. No live migrations, resets, or updates were run on `aling_chona_db`.

### 1.2 Authoritative Source Documents
All requirements were verified against native original document XML and explicit owner directives (excluding AI-generated upgrade reports from authoritative status):

| Document / Source | SHA-256 Hash | Core Authority & Scope |
|---|---|---|
| `Milestone 1.docx` | `1ba38e06261c320d847c28c52b9c6fc93063f47c99e27d05de570bac1d307ca8` | 50% deposit, cash/GCash verification, pickup scheduling |
| `Milestone 2 (Revised).docx` | `c82c82cc67427392a48d43f671f9723b67e1f8634373225c4762d88392d04c69` | Order management workflows, operational coordination |
| `Milestone 3 upd.docx` | `d1b4b27160be9862b6a3339a22ec8966740ee8af86b84d12393aece64360adbb` | Centralized customer/order records; M3-P366 narrow Assistant role |
| Owner Upgrade Instructions | `pasted-text-1.txt` (2 Oct 2026) | Staged order flow, inventory full-width table, expense soft-delete, reports formula |
| **Owner Decisions** | Dated 5 Oct 2026 | **OD-1** (Assistant view/status only); **OD-2** (Retain all verified payments); **OD-3** (Pickup tracking only); **OD-4** (Phones current; tablet/desktop supported) |

### 1.3 Route Register Summary
Route introspection confirmed **88 registered application routes**:
- Public Storefront & Ordering: 7 routes (`/`, `/packages/{product}/customize/{line}`, `/order/details`, `/order`, `/order/payment/{token}`, `/order/payment/{token}/receipt`)
- Authentication: 5 routes (`/login`, `/logout`, etc.)
- Staff Workspace (Shared & Owner): 76 routes across dashboard, orders, proof review, customers, products, catalog, options, add-ons, supplies/operations/history, expenses/audits, reports/drill-downs/exports, pickup schedule, and users.

---

## 2. Working Rule 4 — Evaluation of Historical Implementation Documents

A comprehensive audit was conducted of the six historical documents in `docs/` against original DOCX sources, Owner Decisions, and live application code:

| Historical Document | Evaluated Scope | Status vs Current Authority | Key Contradiction or Agreement |
|---|---|---|---|
| `docs/workflow-upgrade.md` | Storefront stages, mobile cards, inventory, reports | **Verified Fail** | Accurate on storefront stages & atomic stock; **contradicts OD-1 & OD-2** by specifying deposit-only retention (`:83`) and broad Assistant mutations (`:71`). |
| `docs/workflow-verification.md` | Automated test suite (100 tests passed claim) | **Verified Fail** | The "100 passed tests" claim is misleading: earlier tests passed because they asserted the *incorrect* deposit-only rule (`Feature/OrderAndPaymentBusinessRulesTest.php:204`) and broad Assistant permissions. The tests encoded defects as assertions. |
| `docs/upgrade-checklist.md` | Upgrade task checklist for 2 Oct update | **Verified Pass** | Serves as an accurate architectural changelog, but lacks narrower role boundaries. |
| `docs/local-expenses-repair.md` | MariaDB schema repair, 19 tables check | **Verified Pass** | All 19 tables verified intact in MariaDB; additive migrations preserved without destructive drops. |
| `docs/catalog-payments-update.md` | Fixed catalog pricing, tokenized payments | **Verified Fail** | Pricing and tokens verified; explicit statement (`:68`) that customer cancellation retains only deposit violates OD-2. |
| `docs/catalog-inclusions.md` | Inclusions, paid add-ons, JSON snapshots | **Verified Pass** | Implemented correctly; historical order details preserve pricing/inclusion snapshots across catalog edits. |

*Detailed claim-by-claim analysis is recorded in [`working-rule-4-analysis.md`](working-rule-4-analysis.md).*

---

## 3. Prioritized Audit Findings

### 3.1 Priority 1 (Blockers for Production Sign-Off)

#### Finding BC-001 / CR-02: Retained Customer Cancellation Income Undercount
- **Severity:** **P1 — Blocker (Financial Reporting Integrity)**
- **Authoritative Requirement:** Owner Decision 2 (**OD-2**) & UP-2 §5: Customer cancellations retain all verified payments. Operational result = `Completed sales + Retained cancellation payments - Valid expenses`.
- **Root Cause Source Location:**  
  [`app/Services/FinancialReportService.php:32`](file:///c:/Users/User/Desktop/IT12_Project/app/Services/FinancialReportService.php#L32)
  ```php
  30: $retainedQuery = DB::table('orders as o')
  31:     ->join('payments as p', 'o.id', '=', 'p.order_id')
  32:     ->where('p.payment_type', 'down_payment')
  33:     ->where('p.payment_status', 'verified')
  34:     ->where('o.status', 'cancelled')
  35:     ->where('o.cancellation_reason', 'customer_cancellation');
  ```
- **Observed Behavior:** When a customer pays both the 50% deposit (₱500) and the final balance (₱500) and then cancels the order, the payment ledger retains the full ₱1,000 and zero refund is issued. Gross and net collections show ₱1,000. However, `FinancialReportService` filters exclusively for `down_payment`, recognizing only ₱500 as retained cancellation income. The ₱500 final payment vanishes from operational revenue, undercounting the bakery's operational result by ₱500. This undercount propagates across summary cards, trend charts, drill-down tables, and CSV exports.
- **Reproduction & Evidence:**  
  - Test: `BusinessInvestigationTest::test_fully_paid_customer_cancellation_recognizes_all_verified_retained_money` (Failed).
  - Evidence Artifacts: [`customer-cancellation.json`](../business-investigation/evidence/customer-cancellation.json), [`customer-cancellation.csv`](../business-investigation/evidence/customer-cancellation.csv), [`csv-independent-decimal-check.json`](../business-investigation/evidence/csv-independent-decimal-check.json).
- **Proposed Remediation:** Remove `->where('p.payment_type', 'down_payment')` from `FinancialReportService.php:32` (or allow `in_array(p.payment_type, ['down_payment', 'final_payment'])`) so that all verified payments associated with a customer cancellation are recognized.

---

#### Finding CR-01 / OD-1: Permissive Assistant Role Mutation Permissions
- **Severity:** **P1 — Blocker (Access Control & Role Separation)**
- **Authoritative Requirement:** Milestone 3 (**M3-P366**) & Owner Decision 1 (**OD-1**): The Assistant role must be restricted to viewing records and updating order fulfillment statuses, while retaining inventory and expense management. The Assistant must NOT create or edit customer records, create orders, cancel orders, or record/verify payments.
- **Root Cause Source Location:**  
  [`app/Providers/AppServiceProvider.php:30-38`](file:///c:/Users/User/Desktop/IT12_Project/app/Providers/AppServiceProvider.php#L30-L38)
  ```php
  30: $staffCanOperate = fn (User $user) => in_array($user->role, ['owner', 'assistant'], true);
  31: Gate::define('manage-customers', $staffCanOperate);
  32: Gate::define('manage-orders', $staffCanOperate);
  33: Gate::define('cancel-orders', $staffCanOperate);
  34: Gate::define('record-payments', $staffCanOperate);
  ```
  And [`routes/web.php:49-88`](file:///c:/Users/User/Desktop/IT12_Project/routes/web.php#L49-L88) routes customer creation (`POST /customers`) and order creation (`POST /orders`) through generic `staff` middleware.
- **Observed Behavior:** Fresh execution of [`ReliabilityTest.php:376`](file:///c:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/tests/ReliabilityTest.php#L376) confirmed that an authenticated Assistant successfully created a new customer (`POST /customers`, HTTP 201), created a new order (`POST /orders`, HTTP 200), and initiated mutations beyond view/status capabilities.
- **Reproduction & Evidence:**  
  - Test: `ReliabilityTest::test_assistant_role_mutation_boundaries_against_owner_decision_1` (Assertion passed confirming that Assistant CAN perform mutations under current code).
  - Evidence Artifact: [`hd06-assistant-role-boundary.json`](evidence/hd06-assistant-role-boundary.json).
- **Proposed Remediation:** In `AppServiceProvider.php`, redefine gates `manage-customers`, `manage-orders`, `cancel-orders`, and `record-payments` to require `$user->role === 'owner'`. In `routes/web.php` and controllers (`CustomerController`, `OrderController`), enforce `can:manage-customers` and `can:manage-orders` so Assistant is restricted to `GET` index/show and status transition endpoints.

---

### 3.2 Priority 2 (Data Integrity, Scheduling, and UI Clarity)

#### Finding BC-002: Customer Contact Saved Empty After Normalization
- **Severity:** **P2 — Data Integrity**
- **Authoritative Requirement:** Milestone 3 (M3-P139, M3-P158) & Controller validation contracts: Required customer contact information must not be persisted as an empty string.
- **Root Cause Source Location:**  
  [`app/Models/Customer.php:68`](file:///c:/Users/User/Desktop/IT12_Project/app/Models/Customer.php#L68)
  ```php
  68: public static function normalizePhoneNumber(?string $number): ?string {
  69:     return preg_replace('/\D+/', '', (string) $number);
  70: }
  ```
  And [`app/Http/Controllers/CustomerController.php:37`](file:///c:/Users/User/Desktop/IT12_Project/app/Http/Controllers/CustomerController.php#L37), [`app/Http/Controllers/PublicOrderController.php:143`](file:///c:/Users/User/Desktop/IT12_Project/app/Http/Controllers/PublicOrderController.php#L143).
- **Observed Behavior:** A contact string consisting of non-digits (`abcdefgh`) satisfies Laravel's `string|max:30` rule. The controller then calls `normalizePhoneNumber`, which strips all characters, producing an empty string `""`. The record is persisted with an empty phone number, circumventing the required contact rule without validation feedback.
- **Reproduction & Evidence:** Tests `test_required_customer_contact_cannot_normalize_to_empty` and `test_public_contact_cannot_normalize_to_empty` (Failed). Evidence: [`customer-phone.json`](../business-investigation/evidence/customer-phone.json), [`public-phone.json`](../business-investigation/evidence/public-phone.json).
- **Proposed Remediation:** Apply normalization *prior* to validation, or add a custom validation rule verifying that `preg_match('/[0-9]{7,}/', $value)` contains a minimum required digit count.

---

#### Finding BC-003: Dashboard Today's Pickups Uses UTC Instead of Bakery Timezone
- **Severity:** **P2 — Operational Scheduling**
- **Authoritative Requirement:** Milestone 1 (M1-P020, M1-P042) & Milestone 3 (M3-P131): Order fulfillment is scheduled in the bakery's local calendar (`Asia/Manila`).
- **Root Cause Source Location:**  
  [`app/Http/Controllers/DashboardController.php:28`](file:///c:/Users/User/Desktop/IT12_Project/app/Http/Controllers/DashboardController.php#L28)
  ```php
  28: $todayPickups = Order::whereDate('pickup_date', today())->get();
  ```
- **Observed Behavior:** The helper `today()` returns the current date in UTC. At UTC 17:00 (which corresponds to 01:00 AM Manila on the following calendar day), the dashboard selects yesterday's Manila orders and omits today's Manila pickups from the active dashboard counter.
- **Reproduction & Evidence:** Test `test_dashboard_today_pickups_uses_the_bakery_calendar` (Failed). Evidence: [`dashboard-pickup-date.json`](../business-investigation/evidence/dashboard-pickup-date.json).
- **Proposed Remediation:** Use `now(config('bakery.pickup_timezone', 'Asia/Manila'))->toDateString()` when querying `whereDate('pickup_date', ...)`.

---

#### Finding BC-004: Public Checkout Accepts Already-Past Bakery Pickup Date
- **Severity:** **P2 — Ordering & Validation**
- **Authoritative Requirement:** Milestone 1 (M1-P020) & Catalog checkout validation: Customers cannot book an order for a past calendar date.
- **Root Cause Source Location:**  
  [`app/Http/Requests/CatalogOrderRules.php:48`](file:///c:/Users/User/Desktop/IT12_Project/app/Http/Requests/CatalogOrderRules.php#L48) & [`app/Services/OrderService.php:65`](file:///c:/Users/User/Desktop/IT12_Project/app/Services/OrderService.php#L65)
- **Observed Behavior:** The request validation applies `after_or_equal:today`, which evaluates against UTC `today()`. At UTC 17:00 (Manila 01:00 AM on 5 October), a customer can select 4 October 15:00 Manila, which is already in the past locally, and the order is successfully placed.
- **Reproduction & Evidence:** Test `test_new_public_order_rejects_a_past_bakery_pickup_date` (Failed). Evidence: [`past-pickup-date.json`](../business-investigation/evidence/past-pickup-date.json).
- **Proposed Remediation:** Validate pickup dates against `now(config('bakery.pickup_timezone'))->startOfDay()`.

---

#### Finding UA-001: Reports UI Displays Obsolete Deposit-Only Retention Label
- **Severity:** **P2 — User Interface & Financial Clarity**
- **Authoritative Requirement:** Owner Decision 2 (**OD-2**) & UP-2 §5: UI labels must clearly reflect that all verified customer payments are retained upon cancellation.
- **Root Cause Source Location:**  
  [`resources/views/admin/reports/index.blade.php:23-28`](file:///c:/Users/User/Desktop/IT12_Project/resources/views/admin/reports/index.blade.php#L23-L28)
- **Observed Behavior:** The reports KPI headline card is titled "Retained cancellation deposits" and the sub-explainer text reads:  
  `Completed sales + retained cancellation deposits – valid expenses`  
  This text reinforces the legacy deposit-only assumption rather than communicating the retention of all verified customer payments.
- **Reproduction & Evidence:** Headless Chrome screenshot [`staff-owner-reports-1440px.png`](screenshots/staff-owner-reports-1440px.png).
- **Proposed Remediation:** Update card label to "Retained customer cancellations" and the formula explainer to "Completed sales + retained customer cancellations – valid expenses".

---

### 3.3 Priority 3 (UI/UX Polish & Minor Ergonomics)

#### Finding UA-003: Table Row Action Touch Target Below 44px on Mobile
- **Severity:** **P3 — Ergonomics / Accessibility Polish**
- **Authoritative Requirement:** Mobile viewport usability guidelines (OD-4) recommend minimum touch targets of 44×44px for touch screens.
- **Observed Behavior:** On viewports 360px and 390px, table action buttons (e.g. "View", "Record") in `/orders` and `/supplies` have computed heights of 37.8px.
- **Reproduction & Evidence:** Bounding box measurements in [`viewport-state.json`](evidence/viewport-state.json); screenshot [`staff-owner-orders-360px.png`](screenshots/staff-owner-orders-360px.png).
- **Proposed Remediation:** Add `py-2` or `min-h-[44px]` on mobile breakpoints to inline action buttons.

---

## 4. Section A — UI/UX, Responsive Layout, and Accessibility Audit

Section A was audited using an automated Headless Chrome 134 CDP test harness across 6 standard viewports: **360px, 390px, 430px, 768px, 1024px, 1440px**. A total of **66 real rendered PNG screenshots** were captured and stored under `screenshots/`.

```
================================================================================
VIEWPORT COMPLIANCE SUMMARY (Headless Chrome 134 / CDP Automation)
================================================================================
- 360px (Small Mobile):   PASS — 2-col catalog (158.8px cols); 0 page overflow; local table scroll.
- 360px (200% Font Scale): PASS — Container query adapts catalog to 1-col (296px); 0 text clipping.
- 390px (Standard Mobile):PASS — 2-col catalog (173.8px cols); drawer focus trap verified; 0 overflow.
- 430px (Large Mobile):   PASS — 2-col catalog (193.8px cols); full responsive form layout.
- 768px (Tablet Portrait): PASS — 3-col catalog (229.3px cols); expanded table columns.
- 1024px (Tablet Landscape): PASS — 3-col catalog (304.3px cols); desktop sidebar visible.
- 1440px (Desktop Monitor): PASS — 4-col catalog (292.0px cols); full-width workspace views.
================================================================================
```

### 4.1 Public Storefront User Experience
1. **Catalog Responsive Grid (HA04):**  
   Mobile viewports (360, 390, 430px) render a 2-column card layout with 1:1 image aspect ratios. Exact column widths measured:
   - 360px: 158.8px columns (`public-catalog-360px.png`)
   - 390px: 173.8px columns (`public-catalog-390px.png`)
   - 430px: 193.8px columns (`public-catalog-430px.png`)  
   In all cases, `docScrollWidth === docClientWidth`, proving zero horizontal page overflow.
2. **Enlarged Text Adaptation (HA04):**  
   When rendered at 360px with a 32px root font size (200% scale), container queries automatically adapt the catalog grid to a single column (`cols = 296px`). Product titles, descriptions, and price badges wrap naturally without truncation or horizontal page blowout (`public-catalog-360px-enlarged-text.png`).
3. **Staged Checkout Flow (HA01, HA06):**  
   - Dedicated customization stage (`public-customize-1440px.png`, `public-customize-390px.png`) renders package inclusions, paid add-ons, and flavor selection in a 2-column desktop / 1-column mobile layout.
   - Contact & pickup stage (`public-details-1440px.png`, `public-details-390px.png`) preserves entered data upon invalid submission and provides clear feedback (`public-details-validation-errors-390px.png`).
   - Payment page (`public-payment-pending-1440px.png`, `public-payment-pending-390px.png`) clearly presents GCash reference fields and receipt upload controls.

### 4.2 Staff Workspace Experience (Owner & Assistant)
1. **Full-Width Workspaces & Local Table Scrolling (HA02, HA03):**  
   The Inventory (`/supplies`) and Expenses (`/expenses`) workspaces occupy full layout width. Permanent inline creation forms have been removed in favor of modal/on-demand workflows. Across all mobile viewports, tables scroll horizontally within their containers (`tableScrolls = true`), while the outer page maintains `hasHorizontalScroll = false`.
2. **Mobile Navigation Drawer (HA02, HA05):**  
   On viewports < 768px, clicking the hamburger icon smoothly slides in the mobile navigation drawer (`staff-owner-mobile-drawer-open-390px.png`). The body scroll is locked (`overflow: hidden`), keyboard focus is trapped within the drawer, and pressing the Escape key closes the drawer and restores focus to the toggle button.
3. **Role-Based Navigation Display:**  
   The Assistant workspace (`staff-assistant-dashboard-1440px.png`) properly hides administrative links (such as "Users") from the sidebar navigation. Attempting direct access to `/users` renders a clean 403 Forbidden page (`staff-assistant-users-403-forbidden.png`).

### 4.3 Accessibility & Visual Quality (HA05)
- **Contrast Ratios:** Contrast ratios for text across primary brand palettes were measured between 5.4:1 and 12.8:1, comfortably exceeding WCAG 2.1 AA requirements (4.5:1).
- **Keyboard Navigation:** High-visibility `:focus-visible` outlines (3px solid brand maroon) are present on all interactive controls. Skip-to-content links are present on all public and staff pages.

---

## 5. Section D — Reliability, Concurrency, and Security Audit

Section D was verified through an isolated test suite ([`tests/ReliabilityTest.php`](tests/ReliabilityTest.php)) using dedicated SQLite memory/temporary file databases with in-process PDO safeguards:

```
================================================================================
RELIABILITY TEST SUITE RESULTS (PHPUnit 11.5.3 / PHP 8.2.12)
================================================================================
Tests: 12, Assertions: 58, Errors: 0, Failures: 0, Skipped: 0. Time: 4.33s (EXIT 0)
JUnit Log: docs/qa/2026-10-05/consolidated-investigation/evidence/reliability-junit.xml
================================================================================
```

### 5.1 Schema Integrity & Additive Migrations (HD01)
- Verified that all 19 database tables are intact in MariaDB `aling_chona_db` (`evidence/hd01-schema-verification.json`).
- Migration files are strictly additive. Soft-deletes (`deleted_at`), audit tables (`expense_audits`), snapshot columns (`order_details.package_name`, `order_details.inclusions_snapshot`), and idempotency keys (`submission_key`) are present and functional.

### 5.2 Idempotency & Replay Protection (HD02)
- **Order Checkout:** Submitting two consecutive checkout requests with the same `submission_key` returns the existing order instance without creating duplicate records or double-charging.
- **Inventory Submission:** Submitting duplicate stock movements with the same `submission_key` returns the original operation ID and updates stock balances only once.
- **GCash References:** Duplicate GCash reference numbers are rejected with a unique constraint validation error.

### 5.3 Atomic Rollback & Multi-Worker Concurrency (HD03)
- **Atomic Rollback:** Submitting a multi-row stock receipt with a valid first row (10 kg flour) and an invalid second row (negative sugar quantity) caused the entire database transaction to roll back atomically. Stock balance remained at 10 kg with 0 transaction records created ([`hd03-atomic-rollback.json`](evidence/hd03-atomic-rollback.json)).
- **Concurrent Stock Contention:** Two independent PHP CLI worker processes contended simultaneously against a shared inventory balance (10 kg) using a filesystem barrier and lock contention. Exactly one worker successfully claimed 7 kg (reducing stock to 3 kg), while the second worker was rejected due to insufficient stock. Final reconciled stock was exactly 3.00 kg ([`hd03-concurrent-workers.json`](evidence/hd03-concurrent-workers.json)).
- **Engine Limitation:** High-concurrency worker load on the production MariaDB database was omitted to comply with the zero-mutation safety mandate.

### 5.4 Stale Edits & Version Guarding (HD04)
- An expense edit submitted with an outdated `updated_at` timestamp was rejected with a 409 conflict/validation error, preserving existing ledger records.
- An inventory stocktake submitted with an outdated version counter was rejected.

### 5.5 Security & Authorization Boundaries (HD06)
- **Role Enforcement:** Confirmed source defect CR-01 where the Assistant role can create customers and orders under current code ([`hd06-assistant-role-boundary.json`](evidence/hd06-assistant-role-boundary.json)).
- **Guest & Inactive Staff:** Unauthenticated guests attempting to access protected staff routes are redirected to `/login` (HTTP 302). Inactive staff accounts (`is_active = 0`) are rejected with HTTP 403 Forbidden ([`hd06-guest-inactive-security.json`](evidence/hd06-guest-inactive-security.json)).
- **Upload Restrictions:** Uploading malicious extensions (`.php`, `.exe`, `.txt`) or images exceeding 5MB are rejected with validation errors ([`hd06-upload-security.json`](evidence/hd06-upload-security.json)).
- **Private Receipt Tokens:** Order payment links are guarded by unique 64-character tokens. Accessing payment or receipt endpoints without a valid token returns HTTP 404.

### 5.6 Performance & Database Query Loading (HD07)
- Eager loading on `Order`, `OrderDetail`, `PackageOption`, `OrderAddOn`, and `Payment` relationships was confirmed.
- Database query counts on primary views benchmarked under 15 queries:
  - Orders Index: 7 queries
  - Catalog Index: 5 queries
  - Expenses Index: 4 queries
  - Reports Index: 8 queries ([`hd07-query-counts.json`](evidence/hd07-query-counts.json)).

---

## 6. Financial Reconciliation & Ledger Integrity

An independent calculation oracle was executed against read-only MariaDB production data for September and October 2026, comparing direct database queries against `FinancialReportService`:

| Financial Metric | Independent MariaDB Oracle (Sep 2026) | FinancialReportService (Sep 2026) | Variance |
|---|---:|---:|---:|
| **Completed Sales** | ₱3,400.00 | ₱3,400.00 | ₱0.00 |
| **Verified Collections** | ₱3,875.00 | ₱3,875.00 | ₱0.00 |
| **Completed Refunds** | ₱0.00 | ₱0.00 | ₱0.00 |
| **Net Collections** | ₱3,875.00 | ₱3,875.00 | ₱0.00 |
| **Retained Cancellations** | ₱0.00 | ₱0.00 | ₱0.00 |
| **Valid Expenses** | ₱100.00 | ₱100.00 | ₱0.00 |
| **Operational Result** | ₱3,300.00 | ₱3,300.00 | ₱0.00 |
| **Completed Orders Count** | 2 | 2 | 0 |

*For 1–5 October 2026, all metrics reconcile to ₱0.00.*

### Why Existing Records Agree Despite BC-001
The live database currently contains zero customer cancellations. Consequently, historical figures in MariaDB reconcile perfectly. However, the moment a fully paid customer cancellation occurs in production, `FinancialReportService.php:32` will drop the final payment, creating an immediate ₱500 divergence per fully paid cancellation.

---

## 7. Complete Screenshot Gallery

The 66 captured screenshots are organized in [`screenshots/`](screenshots/) and indexed below:

| Screenshot File Name | Role / Area | Viewport | Key Feature / Verification Focus |
|---|---|---|---|
| `public-catalog-360px.png` | Public | 360px | Two-column cards (158.8px cols), 0 page overflow |
| `public-catalog-360px-enlarged-text.png` | Public | 360px | 200% font scale / 32px root, 1-col adaptation, 0 clipping |
| `public-catalog-390px.png` | Public | 390px | Standard mobile 2-col card layout |
| `public-catalog-430px.png` | Public | 430px | Large phone 2-col card layout |
| `public-catalog-768px.png` | Public | 768px | Tablet portrait 3-col card grid |
| `public-catalog-1024px.png` | Public | 1024px | Small desktop 3-col card grid |
| `public-catalog-1440px.png` | Public | 1440px | Full desktop 4-col card grid |
| `public-catalog-with-order-bag-1440px.png` | Public | 1440px | Multi-line order bag at bottom of screen |
| `public-customize-1440px.png` | Public | 1440px | 2-column customization form with sticky cake card |
| `public-customize-390px.png` | Public | 390px | Responsive single-column mobile customizer |
| `public-details-1440px.png` | Public | 1440px | Staged checkout contact details & pickup date picker |
| `public-details-390px.png` | Public | 390px | Mobile contact details layout |
| `public-details-validation-errors-390px.png`| Public | 390px | Inline validation errors & preserved input |
| `public-payment-pending-1440px.png` | Public | 1440px | Private tokenized payment instructions & QR container |
| `public-payment-pending-390px.png` | Public | 390px | Mobile payment instructions view |
| `public-login-1440px.png` | Public | 1440px | Staff authentication login card |
| `staff-owner-dashboard-360px.png` | Owner | 360px | Mobile dashboard KPIs, quick actions, schedule |
| `staff-owner-dashboard-390px.png` | Owner | 390px | Standard mobile dashboard layout |
| `staff-owner-dashboard-430px.png` | Owner | 430px | Large mobile dashboard layout |
| `staff-owner-dashboard-768px.png` | Owner | 768px | Tablet dashboard layout |
| `staff-owner-dashboard-1024px.png` | Owner | 1024px | Small desktop dashboard layout |
| `staff-owner-dashboard-1440px.png` | Owner | 1440px | Full desktop dashboard layout |
| `staff-owner-mobile-drawer-open-390px.png`| Owner | 390px | Open mobile navigation drawer, focus trap, backdrop |
| `staff-owner-schedule-1440px.png` | Owner | 1440px | Pickup schedule table with status badges |
| `staff-owner-schedule-390px.png` | Owner | 390px | Mobile pickup schedule view |
| `staff-owner-orders-360px.png` | Owner | 360px | Orders table with local horizontal scrolling |
| `staff-owner-orders-1440px.png` | Owner | 1440px | Full orders management workspace |
| `staff-owner-order-show-1440px.png` | Owner | 1440px | Detailed order breakdown, timeline, payments |
| `staff-owner-customers-1440px.png` | Owner | 1440px | Centralized customer directory & search |
| `staff-owner-products-1440px.png` | Owner | 1440px | Cake catalog & package option management |
| `staff-owner-inventory-360px.png` | Owner | 360px | Inventory table with local horizontal scrolling |
| `staff-owner-inventory-1440px.png` | Owner | 1440px | Full-width inventory workspace with balance indicators|
| `staff-owner-inventory-receipt-1440px.png`| Owner | 1440px | Dedicated stock receiving form |
| `staff-owner-inventory-usage-1440px.png` | Owner | 1440px | Dedicated stock usage & waste logging form |
| `staff-owner-inventory-stocktake-1440px.png`| Owner| 1440px | Dedicated physical count / stocktake form |
| `staff-owner-expenses-360px.png` | Owner | 360px | Expenses table with local horizontal scrolling |
| `staff-owner-expenses-1440px.png` | Owner | 1440px | Full-width expense workspace with filter toolbar |
| `staff-owner-expenses-create-1440px.png` | Owner | 1440px | Add Expense modal form with category selection |
| `staff-owner-expenses-history-1440px.png`| Owner | 1440px | Complete expense audit trail & void history |
| `staff-owner-reports-360px.png` | Owner | 360px | Reports view on small mobile with scrollable chart |
| `staff-owner-reports-1440px.png` | Owner | 1440px | Financial report dashboard, KPIs, trend chart |
| `staff-owner-reports-drilldown-1440px.png`| Owner | 1440px | Itemized order drill-down supporting completed sales |
| `staff-owner-users-1440px.png` | Owner | 1440px | Staff account administration (Owner only) |
| `staff-assistant-dashboard-1440px.png` | Assistant| 1440px | Assistant dashboard with restricted navigation items |
| `staff-assistant-users-403-forbidden.png`| Assistant| 1440px | HTTP 403 Forbidden page on unauthorized route access |

---

## 8. Prioritized Remediation Plan for Owner Approval

### Phase 1: Critical Blockers (Required Before Production Launch)
1. **Fix Financial Retention Formula (BC-001):**  
   - Update `FinancialReportService.php:32` to include all verified payments (`whereIn('p.payment_type', ['down_payment', 'final_payment'])`) associated with a customer cancellation.
   - Update `resources/views/admin/reports/index.blade.php:23` to label the KPI "Retained customer cancellations" and clarify that both deposits and balance payments are retained.
2. **Enforce Assistant Role Mutation Boundaries (CR-01):**  
   - In `app/Providers/AppServiceProvider.php:31-34`, restrict gates `manage-customers`, `manage-orders`, `cancel-orders`, and `record-payments` strictly to `'owner'`.
   - Update `app/Http/Controllers/CustomerController.php` and `OrderController.php` to authorize write actions against `manage-customers` and `manage-orders`.
   - Ensure Assistant role retains write permissions only for `supplies` (inventory operations) and `expenses`.

### Phase 2: Data Integrity & Calendar Reliability
3. **Fix Phone Number Normalization Validation (BC-002):**  
   - In `CustomerController.php` and `PublicOrderController.php`, validate that the string contains digits before persisting, ensuring normalized phone cannot be empty.
4. **Align Operations to Bakery Timezone (BC-003 & BC-004):**  
   - Update `DashboardController.php:28` to evaluate `today()` using `now(config('bakery.pickup_timezone'))`.
   - Update `CatalogOrderRules.php:48` and `OrderService.php:65` to compare pickup dates against `now(config('bakery.pickup_timezone'))->startOfDay()`.

### Phase 3: Ergonomics & Touch Polish
5. **Expand Mobile Action Buttons (UA-003):**  
   - Add `min-h-[44px]` and `py-2` to inline table buttons in `/orders` and `/supplies` on mobile breakpoints.

---

## 9. Remaining Questions & Gaps

1. **Physical-Count Missing Reason Runtime Test (B27):**  
   While the source rule `required_if:operation_type,stocktake` is confirmed in `InventoryService.php`, an isolated automated HTTP submission sending an empty count reason was not executed in this round.
2. **Secondary CRUD Form Coverage (B28):**  
   Secondary administrative variants (e.g. supply creation without baseline, soft-delete user toggles) remain unexercised. Note that customer/product deletion endpoints do not exist in the codebase.
3. **MariaDB Multi-Process Concurrency Load:**  
   Concurrency contention was proved between independent worker processes using temporary SQLite file barriers. Running an external multi-worker concurrent lock test directly against production MariaDB `aling_chona_db` remains unexecuted due to strict zero-mutation safety constraints.
