# Consolidated QA Investigation Checklist — 5 October 2026

Scope: Full-scope consolidated investigation covering **Working Rule 4** (historical upgrade documents), **Section A** (UI/UX, responsive layout across 6 viewports, accessibility), **Section D** (reliability, schema, concurrency, idempotency, stale edits, and authorization), and integration of prior **Section B** (business rules) and **Section C** (financial reporting) findings.

Strict Boundary: **Zero production mutations** — no edits to production source files (`app/`, `routes/`, `resources/`, etc.) and no live database resets or data mutations on `aling_chona_db`.

---

## 1. Preparation & Test Isolation Prerequisite
- [x] Review authoritative company documents: native DOCX XML for `Milestone 1.docx`, `Milestone 2 (Revised).docx`, and `Milestone 3 upd.docx`.
- [x] Review direct owner instructions: 2 October upgrade prompt (`pasted-text-1.txt`).
- [x] Enforce authoritative Owner Decisions:
  - [x] **OD-1**: Assistant view/status role, retaining inventory and expense management. No customer/order/payment/refund mutations.
  - [x] **OD-2**: Customer cancellation retains all verified payments (down payment + final balance).
  - [x] **OD-3**: Pickup tracking only; external delivery coordination outside the system.
  - [x] **OD-4**: Phones are current bakery devices; tablet/desktop responsive support required.
- [x] Verify current codebase HEAD revision: `9dbd138bf7f02fae780fc7b6e2896e0a6a94b389`.
- [x] Construct isolated test environment (`test-bootstrap.php`, `phpunit-reliability.xml`) with in-process PDO safeguards to prevent accidental connection to MariaDB `aling_chona_db`.
- [x] Verify zero uncommitted production changes in working tree (audit files strictly quarantined in `docs/qa/`).

---

## 2. Working Rule 4 — Historical Implementation & Evidence Evaluation
- [x] Analyze all 6 historical upgrade documents:
  - [x] `docs/workflow-upgrade.md` (Storefront stages, mobile cards, atomic stock, reports formula)
  - [x] `docs/workflow-verification.md` (100 automated tests claim, regression suite)
  - [x] `docs/upgrade-checklist.md` (Upgrade task list baseline)
  - [x] `docs/local-expenses-repair.md` (MariaDB migration repair, 19 tables check)
  - [x] `docs/catalog-payments-update.md` (Fixed catalog pricing, payment tokens, GCash settings)
  - [x] `docs/catalog-inclusions.md` (Package inclusions, paid add-ons, structured JSON snapshots)
- [x] Identify critical disagreements between historical documents and authoritative decisions:
  - [x] Retained cancellation deposits formula (`FinancialReportService.php:32`) filters `down_payment` only, contradicting OD-2.
  - [x] Broad Assistant management permissions (`AppServiceProvider.php:34`, `routes/web.php:49`) contradict Milestone 3 (M3-P366) and OD-1.
  - [x] Historical claim of 100 passing tests explained: tests encoded obsolete deposit-only rules and broad Assistant permissions as assertions.
- [x] Document detailed findings in `docs/qa/2026-10-05/consolidated-investigation/working-rule-4-analysis.md`.

---

## 3. Section A — UI/UX, Responsive Layout, and Accessibility Audit
- [x] Develop automated headless Chrome CDP test harness (`browser-qa-suite.mjs` and `capture-staged-screens.mjs`).
- [x] Capture rendered screenshots across all 6 required CSS viewports:
  - [x] **360px** (small mobile / Android)
  - [x] **390px** (standard mobile / iPhone 12–14)
  - [x] **430px** (large mobile / iPhone Pro Max)
  - [x] **768px** (tablet portrait / iPad)
  - [x] **1024px** (tablet landscape / small laptop)
  - [x] **1440px** (desktop monitor)
- [x] Audit public storefront user experience:
  - [x] Two-column mobile catalog cards verified at 360px (158.8px cols), 390px (173.8px cols), 430px (193.8px cols) with zero page overflow.
  - [x] Enlarged text adaptation verified at 360px with 32px root font (200% scale): container query adapts to 1 column (296px) with zero horizontal overflow.
  - [x] Multi-line staged bag display and per-line customization verified (`public-customize-*.png`, `public-catalog-with-order-bag-1440px.png`).
  - [x] Contact/details form validation tooltips and preserved input verified (`public-details-validation-errors-390px.png`).
  - [x] Tokenized payment screen layout and instructions verified (`public-payment-pending-*.png`).
- [x] Audit staff workspaces (Owner & Assistant):
  - [x] Responsive layout of Dashboard, Schedule, Orders, Customers, Products, Inventory, Expenses, Reports, Users across viewports.
  - [x] Full-width inventory & expense workspaces verified; permanent forms replaced by modal/on-demand workflows.
  - [x] Local horizontal table scrolling verified on small screens (`tableScrolls = true`, `docScrollWidth === docClientWidth`).
  - [x] Mobile navigation drawer verified: smooth toggle, backdrop blur, scroll freeze, focus trap, and Escape key restoration.
  - [x] Double-click protection verified on expense creation button ("Saving..." state).
- [x] Audit accessibility and visual quality:
  - [x] Color contrast checked: brand colors achieve 5.4:1 to 12.8:1 against backgrounds (exceeding WCAG AA 4.5:1).
  - [x] Focus states: `:focus-visible` with 3px outline active on interactive elements; skip-to-content links present.
  - [x] Touch targets: primary buttons meet 44px; inline table action buttons identified at ~38px height (logged as P3 polish finding UA-003).
  - [x] Outdated report UI retention label and explanation identified (logged as P2 finding UA-001).
- [x] Capture DOM metrics in `evidence/viewport-state.json` and 66 screenshot files in `screenshots/`.

---

## 4. Section D — Reliability, Concurrency, and Authorization Audit
- [x] Implement and execute isolated test suite (`tests/ReliabilityTest.php`) in PHPUnit:
  - [x] **HD01: Schema & Migrations**: Verified 19 tables intact, additive migrations complete, no destructive drops. (20 assertions)
  - [x] **HD02: Idempotency & Replay**:
    - [x] Order checkout submission key idempotency verified (2 assertions).
    - [x] Inventory operation submission key idempotency verified (3 assertions).
    - [x] Duplicate GCash reference number rejection verified (1 assertion).
  - [x] **HD03: Atomic Transactions & Concurrency**:
    - [x] Multi-row stock receipt atomic rollback verified on invalid row; stock untouched at 10 kg (4 assertions).
    - [x] Concurrent multi-process stock contention tested with 2 independent worker processes and filesystem barrier; exactly 1 posted (7 kg), 1 rejected, final stock 3.00 kg (5 assertions).
    - [x] Note engine limitation: MariaDB production multi-worker load untested per zero-mutation constraint.
  - [x] **HD04: Stale Edit & Version Guarding**:
    - [x] Stale expense edit rejected when `updated_at` does not match latest database timestamp (1 assertion).
    - [x] Stale stocktake operation rejected when inventory version does not match latest sequence (1 assertion).
  - [x] **HD05: Session Preservation & Recovery**:
    - [x] Multi-line order draft session verified across stage navigation and page reloads.
  - [x] **HD06: Authorization & Security Boundaries**:
    - [x] Assistant role mutation test executed: Assistant CAN create customers and orders under current code, confirming defect CR-01 (3 assertions).
    - [x] Guest unauthenticated requests redirected to `/login` (302) (1 assertion).
    - [x] Inactive staff (`is_active = 0`) rejected with 403 Forbidden (1 assertion).
    - [x] File upload security verified: non-image mimes (`.php`, `.exe`, `.txt`) and files >5MB rejected (6 assertions).
    - [x] Private payment token protected: requests without valid 64-char token return 404 (1 assertion).
  - [x] **HD07: Performance & Query Loading**:
    - [x] Eager loading verified; query counts on primary views benchmarked (<15 queries per page) (9 assertions).
- [x] Record test execution in `evidence/reliability-junit.xml` (12 tests, 58 assertions, 0 errors, 0 failures).

---

## 5. Integration of Section B & C Findings (Carried Forward)
- [x] Carried forward and re-validated all business rules and financial findings:
  - [x] **BC-001 (P1)**: Retained customer cancellation income undercount (₱500 final balance omitted in `FinancialReportService.php:32`).
  - [x] **BC-002 (P2)**: Required customer phone persisted as empty string after non-digit stripping in `Customer::normalizePhoneNumber`.
  - [x] **BC-003 (P2)**: Dashboard today's pickups evaluates UTC `today()`, omitting Manila day at boundary.
  - [x] **BC-004 (P2)**: Public checkout accepts yesterday's bakery date due to UTC date evaluation in `CatalogOrderRules.php`.
- [x] Reconcile regular MariaDB September/October records against independent calculation oracle (sales ₱3,400, expenses ₱100 agree; zero cancellations in historical database).
- [x] Preserve explicit coverage gaps:
  - [x] **B27**: Missing physical-count reason isolated HTTP test (untested at runtime).
  - [x] **B28**: Secondary CRUD form variants (toggles, supply creation without baseline; deletion endpoints do not exist).

---

## 6. Final Delivery & Artifact Verification
- [x] Comprehensive Execution Evidence Index written (`execution-evidence-index.md`).
- [x] Detailed Requirement-to-Test and Execution Matrix compiled (`matrix.md`).
- [x] Consolidated Final Investigation Report compiled (`report.md`).
- [x] Screenshot gallery cross-referenced with exact relative paths.
- [x] Prioritized remediation plan prepared for owner approval.
- [x] Final completion report communicated via single `send_message` to parent agent.
