# Consolidated Final QA Investigation & Adversarial Synthesis Report

**Recipient:** Parent Orchestrator (`af55df94-1f08-4389-b913-d64d4d1021c2`)  
**Audit Date:** 5 October 2026 (Client Date: Asia/Singapore; Bakery Business/Pickup Timezone: Asia/Manila)  
**HEAD Revision:** `9dbd138bf7f02fae780fc7b6e2896e0a6a94b389`  
**Regular Application:** `http://127.0.0.1:8000` (Laravel 12.69.2, PHP 8.2.12 CLI, MariaDB 10.4.32 on port 3306, `aling_chona_db`)  
**Quarantined Audit Directory:** `C:\Users\User\Desktop\IT12_Project\docs\qa\2026-10-05\adversarial-qa-audit\`  
**Strict Safety Protocol Adherence:** **Zero Production Mutations** — zero edits to production code (`app/`, `routes/`, `resources/`, `database/`, etc.), zero live database resets or data mutations on `aling_chona_db`.

---

## 1. Executive Summary & Production Readiness Verdict

### Overall Verdict: **NOT READY FOR PRODUCTION SIGN-OFF**

While the application demonstrates robust architecture in staged catalog ordering, multi-line session drafts, atomic inventory movements, additive database migrations, idempotent submission controls, and responsive UI adaptation across mobile viewports, the system **fails compliance** against two critical authoritative Owner Decisions and several secondary business/reliability requirements:

1. **P1 Blocker — Financial Reporting Undercount (BC-001 / CR-02):**  
   In [`app/Services/FinancialReportService.php:32`](file:///C:/Users/User/Desktop/IT12_Project/app/Services/FinancialReportService.php#L32), the customer cancellation retention query strictly filters for `p.payment_type = 'down_payment'`. Under Owner Decision 2 (**OD-2**), customer cancellations retain **all verified customer payments** (both the 50% deposit and 50% final balance). When a customer cancels a fully paid order (₱1,000 paid), the payments ledger retains ₱1,000 with ₱0 refunded, but the executive financial report, daily/monthly trend charts, itemized drill-downs, and CSV exports count only ₱500, causing a ₱500 revenue undercount in retained cancellation income and operational result.
2. **P1 Blocker — Assistant Role Mutation Permissiveness (CR-01 / OD-1):**  
   In [`app/Providers/AppServiceProvider.php:30-38`](file:///C:/Users/User/Desktop/IT12_Project/app/Providers/AppServiceProvider.php#L30-L38) and [`routes/web.php:49-88`](file:///C:/Users/User/Desktop/IT12_Project/routes/web.php#L49-L88), the gates `manage-customers`, `manage-orders`, `cancel-orders`, and `record-payments` are granted to the `assistant` role. Direct runtime execution confirmed that an Assistant can create customers (HTTP 201), create orders (HTTP 200), record payments directly, approve/reject GCash receipts, declare bakery failures, and complete refunds. This violates Milestone 3 (**M3-P366**) and Owner Decision 1 (**OD-1**), which mandate that the Assistant role be strictly view/status only, reserving customer, order, payment, and refund mutations to the Owner.
3. **P2 Secondary Business, Calendar, and UI Deficiencies:**  
   - **BC-002**: Customer contact persisted as empty string when non-digits are stripped by `Customer::normalizePhoneNumber` without digit-count validation in controllers.
   - **BC-003**: Dashboard today's pickups evaluates against application UTC `today()`, omitting Manila daytime orders between 00:00 and 08:00 Manila time.
   - **BC-004**: Public checkout accepts already-past bakery pickup dates at midnight boundaries due to UTC date checks in `CatalogOrderRules.php:28` and `OrderService.php:64`.
   - **UA-001**: Reports dashboard card reads "Retained cancellation deposits" and explainer text states "Completed sales + retained cancellation deposits – valid expenses", reflecting obsolete deposit-only policy.
   - **UA-005**: Order cancellation JavaScript modal confirmation alert on `admin/orders/show.blade.php:402` hardcodes "The deposit is retained under the customer-cancellation policy", omitting final balance retention.
4. **P3 UI/UX Polish:**  
   - **UA-003**: Inline table action buttons in `/orders` and `/supplies` measure 37.8px height on mobile viewports (< 44px recommended touch target).

---

## 2. Adversarial Critique & Verification of Prior Investigation

A skeptical re-investigation of the prior consolidated report revealed several critical inaccuracies, code citation errors, and scope omissions:

| Review Item | Prior Investigator's Claim | Evidence Cited by Prior Report | What Code Actually Shows | Corrected Finding & Impact |
|---|---|---|---|---|
| **BC-001 Code Citation** | Claimed `app/Services/FinancialReportService.php:30-35` contains `$retainedQuery = ... where('p.payment_status', 'verified') ... where('o.cancellation_reason', 'customer_cancellation')` | `FinancialReportService.php:30-35` | In `FinancialReportService.php:31-33`, the variable is `$retained`, column is `o.cancellation_kind = 'customer'`, and **`payment_status` does not exist in `payments` table**. | The defect is verified, but the prior investigator cited a fabricated snippet with non-existent database columns. |
| **CR-01 Scope & Remediation** | Reported that Assistant can create customers and orders; proposed narrowing gates in `AppServiceProvider.php:31-34`. | `ReliabilityTest.php:376` (exercising customer and order creation only) | Assistant also has execution access to **payments, GCash proof approval/rejection, bakery failure, and refunds**. In `routes/web.php:83-87`, these routes **do not check gates**; narrowing gates alone would leave financial actions wide open! | Prior report severely understated Assistant capabilities and proposed a flawed fix that fails to secure financial endpoints. |
| **BC-002 Phone Normalization** | Cited `Customer.php:68-70` with signature `normalizePhoneNumber(?string $number): ?string` and validation rule `string|max:30`. | `Customer.php:68-70` | Lines 68-70 are inside `findOrCreateMatching()`. Actual method is at **lines 39-55** (`normalizePhoneNumber(?string $phone): string`). Validation is `max:20` and `min:7`. | Defect verified, but prior worker cited wrong lines, wrong method signature, and wrong validation rule. |
| **BC-004 Past Pickup Date** | Cited `CatalogOrderRules.php:48`. | `CatalogOrderRules.php:48` | `CatalogOrderRules.php` has **only 36 lines total**! Actual rule is at **line 28** (`after_or_equal:today`). | Line 48 was fabricated. Real location is line 28 in conjunction with `OrderService.php:64-69`. |
| **HD01 Table Count** | Claimed "all 19 tables in MariaDB aling_chona_db verified intact". | `hd01-schema-verification.json`, `ReliabilityTest.php:58` | `ReliabilityTest.php:58` checks only **15 tables**; MariaDB contains **30 tables** (24 business tables). 7 business tables were omitted from the check. | The number 19 was unthinkingly copied from historical doc `local-expenses-repair.md:11` without verifying actual tables. |
| **UI Deposit Alert (UA-005)** | Omitted from prior UI findings. | N/A | `admin/orders/show.blade.php:402` contains `confirm('...The deposit is retained under the customer-cancellation policy...')`. | Obsolete policy is hardcoded into staff browser confirmation alerts as well as the reports dashboard. |
| **Admitted Gap B27** | Claimed validation rule was `required_if:operation_type,stocktake`. | `report.md:385` | In `InventoryService.php:34`, rule is `'notes' => ['required_if:type,waste,stocktake,adjustment,reversal', ...]`. | Parameter is `notes`, not `reason`; type is `type`, not `operation_type`. Enforced on client and server. |

*Full line-by-line critique preserved in [`prior-investigation-critique.md`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/adversarial-qa-audit/prior-investigation-critique.md).*

---

## 3. Working Rule 4 — Evaluation of Historical Implementation Documents

All six historical documents in `docs/` were inspected and evaluated against original DOCX XML sources, direct owner instructions, and the current codebase:

| Historical Document | Document Scope Claimed | Status vs Current Authority | Key Finding & Contradiction Analysis |
|---|---|---|---|
| `docs/workflow-upgrade.md` | Storefront stages, 2-col cards, atomic stock, reports formula | **Verified Fail** | Staged ordering and atomic stock verified; **contradicts OD-1 & OD-2** by specifying deposit-only retention (`:83`) and broad Assistant mutations (`:71`). |
| `docs/workflow-verification.md` | 100 automated tests passed claim | **Verified Fail** | The "100 passed tests" claim encoded obsolete policy: tests passed because they asserted deposit-only retention (`OrderAndPaymentBusinessRulesTest.php:204`) and broad Assistant mutations (`InventoryAndAuthorizationBusinessRulesTest.php:183`). |
| `docs/upgrade-checklist.md` | Upgrade task checklist for 2 Oct update | **Verified Pass** | Accurately records architectural refactoring tasks; baseline documentation intact. |
| `docs/local-expenses-repair.md` | MariaDB schema repair, 19 tables check | **Verified Pass** | Additive migrations verified; soft deletes active on expenses; 30 tables present in MariaDB. |
| `docs/catalog-payments-update.md` | Fixed catalog pricing, tokenized payments | **Verified Fail** | Fixed pricing & tokens verified; statement (`:68`) that customer cancellation retains only deposit violates OD-2. |
| `docs/catalog-inclusions.md` | Inclusions, paid add-ons, JSON snapshots | **Verified Pass** | Package inclusions, paid add-ons, and snapshot columns function as specified. |

---

## 4. Prioritized Defect Register & Code Evidence

### Priority 1: Critical Blockers for Production Sign-Off

#### [BC-001 / CR-02] Retained Customer Cancellation Income Undercount
- **Workflow Affected:** Financial Reporting, Drill-downs, Trend Charts, and CSV Export.
- **Authority:** Owner Decision 2 (**OD-2**), UP-2 §5.
- **Root Cause Code Snippet:** [`app/Services/FinancialReportService.php:31-33`](file:///C:/Users/User/Desktop/IT12_Project/app/Services/FinancialReportService.php#L31-L33)
  ```php
  $retained = $period->apply(DB::table('payments as p')->join('orders as o', 'o.id', '=', 'p.order_id')->where('o.status', 'cancelled')
      ->where(fn ($q) => $q->whereNull('o.cancellation_kind')->orWhere('o.cancellation_kind', 'customer'))->where('p.payment_type', 'down_payment'), 'o.cancelled_at')
      ->selectRaw("'cancellation_income' as kind, p.id as record_id, o.id as order_id, o.order_number as label, o.cancelled_at as event_at, p.amount, p.payment_method as method, NULL as category");
  ```
- **Actual Behavior:** On a ₱1,000 order with ₱500 down payment and ₱500 final payment cancelled by the customer, ledger retains ₱1,000 with ₱0 refunds. The reporting engine filters `where('p.payment_type', 'down_payment')`, counting only ₱500, undercounting retained income and operational result by ₱500 across summary cards, trend charts, drill-downs, and CSV exports.
- **Evidence:** [`evidence/customer-cancellation.json`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/evidence/customer-cancellation.json), [`evidence/customer-cancellation.csv`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/evidence/customer-cancellation.csv), [`evidence/csv-independent-decimal-check.json`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/evidence/csv-independent-decimal-check.json).
- **Proposed Correction:** In `FinancialReportService.php:32`, remove the `where('p.payment_type', 'down_payment')` restriction so that all verified customer payments on cancelled orders are included. Update UI card label on `resources/views/admin/reports/index.blade.php:18,29` and modal alert on `resources/views/admin/orders/show.blade.php:402`.

#### [CR-01 / OD-1] Assistant Role Mutation Boundary Permissiveness
- **Workflow Affected:** Staff Order Management, Customer Management, Payment Recording, GCash Review, and Refunds.
- **Authority:** Milestone 3 (**M3-P366**), Owner Decision 1 (**OD-1**).
- **Root Cause Code Snippets:**
  1. [`app/Providers/AppServiceProvider.php:30-38`](file:///C:/Users/User/Desktop/IT12_Project/app/Providers/AppServiceProvider.php#L30-L38):
     ```php
     $staffCanOperate = fn (User $user) => in_array($user->role, ['owner', 'assistant'], true);
     Gate::define('manage-customers', $staffCanOperate);
     Gate::define('manage-orders', $staffCanOperate);
     Gate::define('cancel-orders', $staffCanOperate);
     Gate::define('record-payments', $staffCanOperate);
     ```
  2. [`routes/web.php:49-88`](file:///C:/Users/User/Desktop/IT12_Project/routes/web.php#L49-L88): Endpoints `POST /customers`, `POST /orders`, `POST /orders/{order}/payments`, `POST /payment-proofs/{proof}/accept`, `POST /payment-proofs/{proof}/reject`, `POST /orders/{order}/bakery-failure`, and `POST /refunds/{refund}/complete` are inside `middleware(['auth', 'role:owner,assistant'])` without individual owner restrictions.
- **Actual Behavior:** An authenticated Assistant can successfully create customers, create orders, record payments, accept/reject GCash receipts, declare bakery failures, complete refunds, and cancel orders.
- **Evidence:** Direct HTTP test in [`ReliabilityTest.php:376`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/tests/ReliabilityTest.php#L376), [`evidence/hd06-assistant-role-boundary.json`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/evidence/hd06-assistant-role-boundary.json).
- **Proposed Correction:**
  1. Narrow gates in `AppServiceProvider.php`: `manage-customers`, `manage-orders`, `cancel-orders`, and `record-payments` must require `$user->role === 'owner'`.
  2. In `routes/web.php`, wrap routes for customers (create/update), orders (create/cancel), payments, GCash proof review, and refunds inside `middleware('role:owner')` or corresponding gates.
  3. In controllers (`CustomerController`, `OrderController`, `PaymentController`, `PaymentReviewController`, `RefundController`), enforce authorization checks.
  4. In Blade views, wrap mutation buttons and forms inside `@can` or `@if(auth()->user()->isOwner())`. Retain Assistant write access strictly for inventory and expenses.

---

### Priority 2: Data Integrity, Scheduling, and UI Clarity

#### [BC-002] Customer Contact Saved Empty After Normalization
- **Workflow Affected:** Customer Creation (Staff), Inline Customer Creation, and Public Storefront Checkout.
- **Authority:** Milestone 3 (M3-P139, M3-P158).
- **Root Cause Code Snippets:**
  - [`app/Models/Customer.php:39-55`](file:///C:/Users/User/Desktop/IT12_Project/app/Models/Customer.php#L39-L55): `normalizePhoneNumber` strips all nondigits via `preg_replace('/\D+/', '', $phone)`.
  - [`app/Http/Controllers/CustomerController.php:41`](file:///C:/Users/User/Desktop/IT12_Project/app/Http/Controllers/CustomerController.php#L41): validates `'phone_number' => ['required', 'string', 'max:20']`.
  - [`app/Http/Controllers/PublicOrderController.php:114`](file:///C:/Users/User/Desktop/IT12_Project/app/Http/Controllers/PublicOrderController.php#L114): validates `'phone_number' => ['required', 'string', 'min:7', 'max:20']`.
- **Actual Behavior:** Entering a string of letters (e.g. `'abcdefg'`) passes string/length validation, is stripped to `""` by `Customer::normalizePhoneNumber()`, and persists as an empty string without validation errors.
- **Evidence:** [`evidence/customer-phone.json`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/evidence/customer-phone.json), [`evidence/public-phone.json`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/evidence/public-phone.json).
- **Proposed Correction:** In `CustomerController`, `OrderController`, and `PublicOrderController`, validate that the normalized phone number contains at least 7 digits (e.g. `regex:/^(\+?63|0)?[0-9]{10,11}$/` or validating normalized length >= 7).

#### [BC-003] Dashboard Today's Pickups Evaluates UTC Date
- **Workflow Affected:** Staff Dashboard Today's Pickups Counter & Table.
- **Authority:** Milestone 1 (M1-P020, M1-P042), Milestone 3 (M3-P131).
- **Root Cause Code Snippet:** [`app/Http/Controllers/DashboardController.php:28`](file:///C:/Users/User/Desktop/IT12_Project/app/Http/Controllers/DashboardController.php#L28)
  ```php
  $todayPickups = Order::with('customer')
      ->whereDate('pickup_date', today())
      ->whereNotIn('status', ['completed', 'cancelled'])
      ->orderBy('pickup_time')
      ->get();
  ```
- **Actual Behavior:** `today()` defaults to UTC. Between 00:00 and 08:00 Manila time (16:00 to 24:00 UTC previous day), queries yesterday's Manila pickups and displays 0 pickups for today.
- **Evidence:** [`evidence/dashboard-pickup-date.json`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/evidence/dashboard-pickup-date.json).
- **Proposed Correction:** Replace `today()` with `now(config('bakery.pickup_timezone'))->toDateString()`.

#### [BC-004] Public Checkout Accepts Past Bakery Pickup Date
- **Workflow Affected:** Public Checkout Date Selection.
- **Authority:** Milestone 1 (M1-P020, M1-P035), Milestone 3 (M3-P131).
- **Root Cause Code Snippets:**
  - [`app/Http/Requests/CatalogOrderRules.php:28`](file:///C:/Users/User/Desktop/IT12_Project/app/Http/Requests/CatalogOrderRules.php#L28): `'pickup_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']`.
  - [`app/Services/OrderService.php:64-69`](file:///C:/Users/User/Desktop/IT12_Project/app/Services/OrderService.php#L64-L69): checks `$pickupDate->isPast() && !$pickupDate->isToday()`.
- **Actual Behavior:** Both checks evaluate against UTC `today`. Between 00:00 and 08:00 Manila time, yesterday's Manila date is treated as UTC "today" and accepted.
- **Evidence:** [`evidence/past-pickup-date.json`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/evidence/past-pickup-date.json).
- **Proposed Correction:** Validate `pickup_date` against `now(config('bakery.pickup_timezone'))->startOfDay()`.

#### [UA-001] Reports UI Displays Obsolete Deposit-Only Retention Label
- **Workflow Affected:** Financial Reports Dashboard.
- **Authority:** OD-2, UP-2 §5.
- **Root Cause Code Snippet:** [`resources/views/admin/reports/index.blade.php:18,29`](file:///C:/Users/User/Desktop/IT12_Project/resources/views/admin/reports/index.blade.php#L18)
- **Actual Behavior:** Headline card reads "Retained cancellation deposits" and explainer text states "Completed sales + retained cancellation deposits – valid expenses".
- **Evidence:** Screenshot [`staff-owner-reports-1440px.png`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/screenshots/staff-owner-reports-1440px.png).
- **Proposed Correction:** Rename label to "Retained customer cancellations" and update formula explanation text.

#### [UA-005] Order Cancellation JavaScript Modal Alert Hardcodes Deposit-Only Retention
- **Workflow Affected:** Staff Order Detail Cancellation Confirmation.
- **Authority:** OD-2.
- **Root Cause Code Snippet:** [`resources/views/admin/orders/show.blade.php:402`](file:///C:/Users/User/Desktop/IT12_Project/resources/views/admin/orders/show.blade.php#L402)
  ```html
  <form action="{{ route('orders.cancel', $order) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? The deposit is retained under the customer-cancellation policy. For bakery failure, use the full-refund action instead.');">
  ```
- **Actual Behavior:** Staff is prompted with "The deposit is retained under the customer-cancellation policy", omitting that final payments are also retained.
- **Proposed Correction:** Update dialog message to: "Are you sure you want to cancel this order? All verified payments are retained under the customer-cancellation policy. For bakery failure, use the full-refund action instead."

---

### Priority 3: UI/UX Ergonomics Polish

#### [UA-003] Mobile Table Row Action Tap Target Below 44px
- **Workflow Affected:** Staff Order Listing & Inventory Supply Listing on Small Screens.
- **Observed Behavior:** Action buttons on table rows in `/orders` and `/supplies` have a computed height of 37.8px on viewports 360px and 390px (< 44px recommended touch target).
- **Evidence:** [`evidence/viewport-state.json`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/evidence/viewport-state.json), screenshot [`staff-owner-orders-360px.png`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/screenshots/staff-owner-orders-360px.png).
- **Proposed Correction:** Add `min-h-[44px]` and `py-2` to inline action buttons on mobile breakpoints.

---

## 5. Section A — UI/UX, Viewports, and Accessibility Findings

Audited via Headless Chrome 134 CDP automation across 6 viewports (360, 390, 430, 768, 1024, 1440px), capturing **66 real rendered PNG screenshots** in `screenshots/`:

1. **Two-Column Mobile Catalog Cards (HA04):**  
   - 360px: 158.8px columns (`public-catalog-360px.png`)
   - 390px: 173.8px columns (`public-catalog-390px.png`)
   - 430px: 193.8px columns (`public-catalog-430px.png`)  
   All maintain `docScrollWidth === docClientWidth === viewportWidth` (zero page horizontal overflow).
2. **200% Enlarged Text Adaptation (HA04):**  
   At 360px with a 32px root font size (200% zoom), container queries dynamically adapt the catalog grid to a single column (296px). Text wraps without truncation and zero page horizontal overflow (`public-catalog-360px-enlarged-text.png`).
3. **Full-Width Workspaces & Local Table Scrolling (HA02, HA03):**  
   Inventory (`/supplies`) and Expenses (`/expenses`) occupy full screen width without permanent inline creation forms. Mobile tables scroll horizontally within their containers (`tableScrolls = true`), while outer page has `hasHorizontalScroll = false`.
4. **Mobile Navigation Drawer & Focus Trap (HA02, HA05):**  
   At viewports < 768px, hamburger toggle opens drawer (`staff-owner-mobile-drawer-open-390px.png`). Background scroll is frozen (`overflow: hidden`), keyboard focus is trapped inside the drawer, and pressing Escape closes the drawer and restores focus to the toggle button.
5. **Accessibility Metrics:**  
   Contrast ratios on brand palettes range from 5.4:1 to 12.8:1 (exceeding WCAG AA 4.5:1). `:focus-visible` with 3px solid outline is active on interactive elements. Skip-to-content links are present on all layouts.

---

## 6. Section D — Reliability, Concurrency, and Authorization Findings

Audited via isolated test harness [`tests/ReliabilityTest.php`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/tests/ReliabilityTest.php) (PHPUnit 11.5.3 / PHP 8.2.12):  
**Results: 12 tests, 58 assertions, 0 errors, 0 failures, 0 skipped, Time: 4.33s (EXIT 0)**.  
JUnit report: [`evidence/reliability-junit.xml`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/evidence/reliability-junit.xml).

1. **HD01: Additive Schema Integrity:** Verified 30 tables present in MariaDB `aling_chona_db` intact; additive migrations intact; soft-deletes and audit tables functional (`preflight.php`).
2. **HD02: Idempotency & Replay:** Duplicate order submission keys return existing order (exit 0); duplicate inventory submission keys return existing operation (exit 0); duplicate GCash reference rejected (exit 0).
3. **HD03: Atomic Transactions & Concurrency:**  
   - Multi-row stock receipt with invalid 2nd row atomically rolls back entire transaction (flour stock remains 10 kg, 0 transactions created).  
   - Two independent PHP CLI worker processes contended on 10 kg flour stock with a filesystem barrier: exactly 1 worker claimed 7 kg (stock -> 3 kg), 1 worker was rejected (insufficient stock), final stock reconciled to 3.00 kg (`evidence/hd03-concurrent-workers.json`).
4. **HD04: Stale Edit Guarding:** Outdated `updated_at` on expense edit and outdated inventory version on stocktake rejected with validation errors.
5. **HD05: Session Draft Preservation:** Staged multi-line orders survive navigation and reloads.
6. **HD06: Authorization & Security Boundaries:**  
   - Assistant customer/order write permissions confirmed at runtime (Defect CR-01).  
   - Guests redirected to `/login` (HTTP 302); inactive staff rejected with HTTP 403 (`evidence/hd06-guest-inactive-security.json`).  
   - File uploads reject non-images (`.php`, `.exe`, `.txt`) and files >5MB (`evidence/hd06-upload-security.json`).  
   - Private payment token routes protected; unauthorized requests return HTTP 404.
7. **HD07: Database Query Efficiency:** Eager loading confirmed on orders; query counts on primary views stay below 15 queries (Orders: 5, Catalog: 5, Expenses: 2, Reports: 4; `evidence/hd07-query-counts.json`).

---

## 7. Financial Reconciliation Oracle Check

Read-only MariaDB production data for September 2026 was compared against an independent calculation oracle:
- **Completed Sales:** ₱3,400.00 (Oracle: ₱3,400.00 | Variance: ₱0.00)
- **Verified Collections:** ₱3,875.00 (Oracle: ₱3,875.00 | Variance: ₱0.00)
- **Completed Refunds:** ₱0.00 (Oracle: ₱0.00 | Variance: ₱0.00)
- **Net Collections:** ₱3,875.00 (Oracle: ₱3,875.00 | Variance: ₱0.00)
- **Valid Expenses:** ₱100.00 (Oracle: ₱100.00 | Variance: ₱0.00)
- **Operational Result:** ₱3,300.00 (Oracle: ₱3,300.00 | Variance: ₱0.00)

*Historical records reconcile because zero customer cancellations exist in the database. The BC-001 defect activates immediately upon the first fully paid customer cancellation.*

---

## 8. Prioritized Remediation Plan for Owner Approval

### Phase 1: Critical Blockers (Required Prior to Sign-Off)
1. **Fix Financial Retention Formula (BC-001):**  
   In `FinancialReportService.php:32`, remove `where('p.payment_type', 'down_payment')` to recognize all verified customer payments on customer cancellations. Update `reports/index.blade.php:18,29` UI card labels and `orders/show.blade.php:402` modal alert.
2. **Enforce Assistant Role Mutation Boundaries (CR-01):**  
   - In `AppServiceProvider.php:31-34`, narrow `manage-customers`, `manage-orders`, `cancel-orders`, and `record-payments` strictly to `'owner'`.
   - In `routes/web.php`, add `role:owner` middleware to `POST /customers`, `PUT /customers/*`, `POST /orders`, `POST /orders/*/cancel`, `POST /orders/*/payments`, `POST /payment-proofs/*/accept`, `POST /payment-proofs/*/reject`, `POST /orders/*/bakery-failure`, and `POST /refunds/*/complete`.
   - In `PaymentController`, `PaymentReviewController`, and `RefundController`, add explicit `$this->authorize()` checks.
   - Retain Assistant write access strictly for inventory (`manage-inventory`) and expenses (`manage-expenses`).

### Phase 2: Data Integrity & Calendar Reliability
3. **Fix Phone Number Normalization Validation (BC-002):**  
   Validate that the normalized customer phone number contains at least 7 digits before persistence across `CustomerController.php`, `OrderController.php`, and `PublicOrderController.php`.
4. **Align Operations to Bakery Timezone (BC-003 & BC-004):**  
   Use `now(config('bakery.pickup_timezone'))` for `DashboardController.php:28` (`whereDate('pickup_date', ...)`) and `CatalogOrderRules.php:28` (`after_or_equal:...`).

### Phase 3: Ergonomics & Touch Polish
5. **Expand Mobile Action Buttons (UA-003):**  
   Add `min-h-[44px]` and `py-2` to inline table buttons in `/orders` and `/supplies` on mobile breakpoints.

---

## 9. Deliverables & Documentation Index

All investigation documentation and evidence artifacts are available:
- **Consolidated QA Synthesis Report:** [`report.md`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/adversarial-qa-audit/report.md)
- **Adversarial Critique of Prior Investigation:** [`prior-investigation-critique.md`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/adversarial-qa-audit/prior-investigation-critique.md)
- **Requirement & Execution Matrix (66 rows):** [`matrix.md`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/adversarial-qa-audit/matrix.md)
- **Completed Audit Checklist:** [`checklist.md`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/adversarial-qa-audit/checklist.md)
- **Screenshot Gallery (66 PNG files across 6 viewports):** [`screenshots/`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/screenshots/)
- **Reliability JUnit Log:** [`reliability-junit.xml`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/evidence/reliability-junit.xml)
- **DOM Viewport Metrics (57 states):** [`viewport-state.json`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/evidence/viewport-state.json)
- **Stock Concurrency Worker Evidence:** [`hd03-concurrent-workers.json`](file:///C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/consolidated-investigation/evidence/hd03-concurrent-workers.json)

---

## 10. Remaining Questions & Gaps

1. **MariaDB Multi-Process High-Concurrency Load:**  
   Concurrency contention was verified between two independent PHP worker processes using temporary SQLite file barriers (`hd03-concurrent-workers.json`). Multi-worker concurrent load testing directly against production MariaDB `aling_chona_db` remains unexecuted due to strict zero-mutation production safety constraints.
2. **Prioritization for Next Investigator:**  
   Once Phase 1 fixes are implemented by developers:
   - Verify that an authenticated Assistant attempting to POST to `/customers`, `/orders`, `/orders/{order}/payments`, `/payment-proofs/{proof}/accept`, `/payment-proofs/{proof}/reject`, `/orders/{order}/bakery-failure`, or `/refunds/{refund}/complete` receives **HTTP 403 Forbidden**.
   - Verify that cancelling a fully paid order retains 100% of verified payments in `FinancialReportService` reports, trend charts, drill-downs, and CSV exports.
