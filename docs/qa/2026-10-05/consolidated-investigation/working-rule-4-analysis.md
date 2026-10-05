# Working Rule 4 — Historical Implementation & Evidence Investigation

This document evaluates the six historical upgrade/implementation documents in `docs/` against:
1. Native original company documents (`Milestone 1.docx`, `Milestone 2 (Revised).docx`, `Milestone 3 upd.docx`)
2. Direct 2 October 2026 owner upgrade instructions (`pasted-text-1.txt`)
3. Authoritative Owner Decisions (OD-1, OD-2, OD-3, OD-4)
4. Fresh verification evidence gathered on 5 October 2026

---

## 1. Document Inventory and Scope

| File Path | Recorded Date | Primary Scope Claimed | Status in Current Audit |
|---|---|---|---|
| `docs/workflow-upgrade.md` | 2026-10-02 | Storefront, inventory, expenses, reports workflow & deployment | Implementation history; conflicting rules identified |
| `docs/workflow-verification.md` | 2026-10-02 | Automated test suite (100 tests), browser verification, limits | Implementation history; outdated policy assertions |
| `docs/upgrade-checklist.md` | 2026-10-02 | Task checklist for 2 October update | Implementation history; baseline reference |
| `docs/local-expenses-repair.md` | 2026-10-02 | MariaDB migration repair, schema verification, 19 tables check | Implementation history; MariaDB state verified |
| `docs/catalog-payments-update.md` | 2026-09-24 | Fixed pricing, GCash settings, tokenized payment pages | Implementation history; deposit policy conflict |
| `docs/catalog-inclusions.md` | 2026-09-27 | Package inclusions, paid add-ons, structured JSON snapshots | Implementation history; verified in current schema |

---

## 2. Claim-by-Claim Mapping and Fresh Verification

### A. Storefront & Catalog Staged Flow
- **Historical Claim (`workflow-upgrade.md:9`, `catalog-payments-update.md:5-9`):** Package browsing is decoupled from customization (`/` -> `/packages/{id}/customize/{line}` -> `/order/details` -> `/order/payment/{token}`). Multi-line drafts survive refresh/back. Catalog pricing is authoritative on server.
- **Authority:** UP-2 §2; M3-P139.
- **Fresh Verification:**
  - Route register confirms 4 distinct stages.
  - Browser tests (`public-catalog-*.png`, `public-customize-*.png`, `public-details-*.png`, `public-payment-*.png`) confirm functional rendering and responsive layout.
  - Multi-line draft and quote validation verified in server tests (`test_ordinary_package_line_add_edit_remove_and_checkout`).
- **Audit Assessment:** **Verified Pass** (architecture matches UP-2 §2).

### B. Mobile Catalog Two-Column Cards & Enlarged Text
- **Historical Claim (`workflow-upgrade.md:55`, `workflow-verification.md:37`):** Mobile catalog cards maintain two columns at 360, 390, and 430px with responsive 1:1 image proportions. Under enlarged text (32px root font / 200%), cards adapt to a single column to prevent clipping.
- **Authority:** UP-2 §2; OD-4.
- **Fresh Verification:**
  - Automated DOM measurements at 360px (`cols="158.8px 158.8px"`), 390px (`cols="173.8px 173.8px"`), 430px (`cols="193.8px 193.8px"`) confirm two-column grid with `hasHorizontalScroll = false`.
  - At 360px with `fontSize = 32px`, container query adapts catalog to single column (`cols="296px"`), zero horizontal overflow (`docScrollWidth === docClientWidth === 360px`).
- **Audit Assessment:** **Verified Pass**.

### C. Multi-Row Inventory Operations & Atomic Rollback
- **Historical Claim (`workflow-upgrade.md:10, 59`, `workflow-verification.md:38`):** Multi-row stock receipts, usage/waste, and stocktakes commit all rows together or none. Duplicate submission keys return existing operation. Linked corrections reverse operations with full audit history.
- **Authority:** UP-2 §3; M1-P040; M3-P164.
- **Fresh Verification:**
  - `ReliabilityTest::test_idempotent_inventory_submission_with_same_key` confirms idempotent submission (exit 0).
  - `ReliabilityTest::test_multi_row_receipt_rolls_back_atomically_when_one_row_is_invalid` confirms atomic rollback (exit 0; flour remains 10, 0 transactions).
  - Grouped reversal linked to original operation verified in `BusinessInvestigationTest::test_ordinary_receiving_usage_count_and_linked_correction_have_no_implicit_expenses`.
- **Audit Assessment:** **Verified Pass**.

### D. Inventory Concurrency
- **Historical Claim (`workflow-upgrade.md:95`, `workflow-verification.md:40, 82`):** Concurrency verified on disposable SQLite with two independent processes. MySQL concurrency load not executed.
- **Authority:** UP-2 §3.
- **Fresh Verification:**
  - `ReliabilityTest::test_concurrent_overlapping_stock_operations_with_barrier` executed two independent PHP processes against a temporary SQLite file with an explicit filesystem barrier and lock contention. Result: exactly one usage posted (7 kg) and one rejected (7 kg), leaving final stock at 3.00 kg.
  - MariaDB production concurrency load remains unexecuted per safety constraints (no live data mutation).
- **Audit Assessment:** **Verified Pass (SQLite bounded)**; MariaDB concurrency remains an explicit limitation.

### E. Expenses Table Workspace & Soft Deletion / Auditing
- **Historical Claim (`workflow-upgrade.md:11, 69`, `local-expenses-repair.md:7-13`):** Permanent form removed; full-width table with filters and matching total across pages. Soft delete (`deleted_at`) with mandatory reason (`deletion_reason`). Complete audit trail (`expense_audits`).
- **Authority:** UP-2 §4; M3-P161.
- **Fresh Verification:**
  - Browser inspection (`staff-owner-expenses-*.png`) confirms table workspace and absence of permanent creation form.
  - Soft delete and reason verified in `BusinessInvestigationTest::test_expense_creator_editor_void_and_active_totals`.
  - Stale edit rejection verified in `ReliabilityTest::test_stale_expense_edit_is_rejected`.
- **Audit Assessment:** **Verified Pass**.

### F. Reports Period Selector & Financial Formula
- **Historical Claim (`workflow-upgrade.md:12, 75-86`):** Reports support single month, month range, custom dates, and presets in `Asia/Manila` timezone. Headline formula: `Completed sales + Retained cancellation deposits - Valid expenses`.
- **Authority:** UP-2 §5; OD-2.
- **Fresh Verification:**
  - Browser inspection (`staff-owner-reports-*.png`, `staff-owner-reports-drilldown-1440px.png`) confirms period picker, KPI cards, trend chart, and drill-down table.
  - Formula discrepancy confirmed: see Section 3 below.
- **Audit Assessment:** **Visual/Period: Pass; Financial Logic: Verified Fail (BC-001)**.

---

## 3. Critical Disagreements with Original Company Documents & Current Owner Decisions

### Disagreement 1: Retained Customer Cancellation Income (BC-001 / CR-02)
- **Historical Text (`workflow-upgrade.md:83`):**
  > "Retained cancellation deposits: Existing customer-cancellation rules and saved downpayment, on cancellation date. Bakery-failure cancellations do not retain deposits."
- **Historical Text (`catalog-payments-update.md:68`):**
  > "Customer cancellation continues to retain the deposit under the existing policy. The existing system does not define the refund treatment of a final balance collected before a customer cancellation; that behavior was not changed."
- **Authoritative Decision:** **Owner Decision 2**:
  > "Customer cancellation retains all verified payments, including any final balance collected before cancellation. Bakery-failure refunds remain separately classified."
- **Contradiction Analysis:**
  The historical implementation deliberately restricted retention to `payment_type = 'down_payment'` in `FinancialReportService.php:32`. When a customer pays both the 50% deposit (₱500) and the final balance (₱500) and subsequently cancels, the bakery retains the entire ₱1,000. However, the report ledger records only ₱500, causing a ₱500 undercount in retained cancellation income and operational result.
- **Audit Finding:** **Confirmed Defect BC-001 (P1)**.

### Disagreement 2: Assistant Role Boundary (CR-01 / OD-1)
- **Historical Text (`workflow-upgrade.md:10, 71`):**
  > "Both staff roles retain access... Owner and Assistant retain the existing management permissions; inactive staff are rejected on the server."
- **Historical Text (`upgrade-checklist.md:31`):**
  > Assumes both roles manage all operational areas.
- **Authoritative Decision:** **Milestone 3 (M3-P366) and Owner Decision 1**:
  > "Follow Milestone 3's narrower Assistant view/status role, retaining inventory and expense management access. Do not broaden customer/order/payment/refund mutations merely because current code permits them. Distinguish status updates from financial actions."
- **Contradiction Analysis:**
  In `app/Providers/AppServiceProvider.php:30-38` and `routes/web.php:49-88`, the system defines:
  `$staffCanOperate = fn (User $user) => in_array($user->role, ['owner', 'assistant'], true);`
  and grants `manage-customers`, `manage-orders`, `cancel-orders`, and `record-payments` to `assistant`.
  Fresh test `ReliabilityTest::test_assistant_role_mutation_boundaries_against_owner_decision_1` confirmed that an Assistant can successfully create customers and orders via POST requests.
- **Audit Finding:** **Confirmed Defect CR-01 (P1)**.

### Disagreement 3: Timezone Application in Pickup Calendar & Validation (BC-003, BC-004)
- **Historical Text (`catalog-payments-update.md:14`):**
  > "Readiness timestamps are separate from completion. Pickup deadlines use Asia/Manila, configurable through BAKERY_PICKUP_TIMEZONE..."
- **Historical Text (`workflow-upgrade.md:75`):**
  > "Datetime bounds are converted consistently from BAKERY_BUSINESS_TIMEZONE (defaults to the existing pickup timezone, Asia/Manila) to the application's storage timezone."
- **Authoritative Decision:** M1-P020/042, M3-P131; configured bakery timezone `Asia/Manila`.
- **Contradiction Analysis:**
  While `ReportPeriod` converts timezone bounds for financial reports, operational routes do not:
  1. `DashboardController.php:28` uses `whereDate('pickup_date', today())`, evaluating against UTC `today()`. At UTC 17:00 (Manila 01:00), today's Manila pickups are omitted (BC-003).
  2. `CatalogOrderRules.php` and `OrderService.php:65` validate `pickupDate->isPast() && !$pickupDate->isToday()` using UTC `isToday()`, allowing checkout for yesterday's bakery date (BC-004).
- **Audit Finding:** **Confirmed Defects BC-003 (P2) & BC-004 (P2)**.

### Disagreement 4: Validity of Historical Test Passing Claims
- **Historical Text (`workflow-verification.md:11`):**
  > "Full PHP regression suite: 100 tests passed, 940 assertions... Final full suite passed."
- **Contradiction Analysis:**
  The passing status of the 100 tests in earlier reports is misleading. The tests passed because their assertions explicitly checked the *incorrect* deposit-only retention formula (`Feature/OrderAndPaymentBusinessRulesTest.php:204`) and asserted that the Assistant *should* have access to customer and order management (`Feature/InventoryAndAuthorizationBusinessRulesTest.php:183-186`). When tested against the authoritative owner decisions, these tests encode requirement defects rather than verified compliance.

---

## 4. Conclusion on Historical Documents

The six historical documents are valuable as an engineering changelog and architectural record, but **cannot be accepted as authoritative verification or current proof of compliance**. They document an implementation that deviated from Milestone 3 permissions and codified a deposit-only cancellation accounting rule that violates Owner Decision 2.
