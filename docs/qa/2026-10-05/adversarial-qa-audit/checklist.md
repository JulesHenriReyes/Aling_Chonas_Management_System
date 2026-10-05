# Consolidated QA Audit Checklist — 5 October 2026

**Target Application:** Aling Chona Cake & Cupcake Management System (`IT12_Project`)  
**Audit Scope:** Full audit combining Sections B & C (Business rules and financial reporting) with fresh executions of Working Rule 4 (historical documentation analysis), Section A (UI/UX, responsive layouts across 6 viewports, and accessibility), and Section D (reliability, concurrency, idempotency, and security).  
**Safety Protocol:** Strictly zero edits to production code (`app/`, `routes/`, `resources/`, etc.) and zero live database mutations or migrations on MariaDB `aling_chona_db`.

---

## 1. Safety, Environment, and Authority Baseline
- [x] Authoritative Company Documents inspected via native `word/document.xml`:
  - [x] `Milestone 1.docx` (SHA-256: `1ba38e06...`) — business model, 50% deposit, owner/assistant structure.
  - [x] `Milestone 2 (Revised).docx` (SHA-256: `c82c82cc...`) — system architecture, ordering workflow.
  - [x] `Milestone 3 upd.docx` (SHA-256: `d1b4b271...`) — use case diagram, narrower Assistant role (M3-P366).
- [x] Authoritative Owner Decisions enforced:
  - [x] **OD-1**: Assistant view/status role, retaining inventory and expense management. No customer/order/payment/refund mutations.
  - [x] **OD-2**: Customer cancellation retains all verified customer payments (down payment + final balance).
  - [x] **OD-3**: System tracks pickup only; delivery logistics outside system.
  - [x] **OD-4**: Phones are primary devices; tablet/desktop responsive support required.
- [x] Environment confirmed:
  - [x] Laravel 12.69.2, PHP 8.2.12 CLI, MariaDB 10.4.32 on port 3306 (`aling_chona_db`).
  - [x] Local application running on `http://127.0.0.1:8000`.
  - [x] HEAD revision `9dbd138bf7f02fae780fc7b6e2896e0a6a94b389` clean on tracked files.
- [x] Isolated mutation harness constructed with PDO connection-name guards preventing writes to MariaDB.

---

## 2. Working Rule 4 — Evaluation of Historical Documents
- [x] Analyzed all 6 historical upgrade documents:
  - [x] `docs/workflow-upgrade.md`: Storefront stages and atomic inventory pass; retention formula (:83) and broad assistant mutations (:71) fail against OD-1 & OD-2.
  - [x] `docs/workflow-verification.md`: "100 tests passed" claim failed because tests asserted obsolete deposit-only retention and broad Assistant permissions.
  - [x] `docs/upgrade-checklist.md`: Architectural refactoring checklist verified pass.
  - [x] `docs/local-expenses-repair.md`: Additive schema repair verified pass; soft deletes active on expenses.
  - [x] `docs/catalog-payments-update.md`: Pricing snapshots pass; deposit-only cancellation claim (:68) fails against OD-2.
  - [x] `docs/catalog-inclusions.md`: Structured inclusions, add-ons, and JSON snapshots verified pass.
- [x] Verified disagreements against original company sources and owner decisions documented.

---

## 3. Section A — UI/UX, Responsive Design, and Accessibility Audit
- [x] Headless Chrome CDP automation executed across all 6 required viewports:
  - [x] **360px** (small phone): Two-column catalog cards (158.8px cols); zero page horizontal overflow (`public-catalog-360px.png`).
  - [x] **390px** (standard phone): Two-column catalog cards (173.8px cols); zero page horizontal overflow (`public-catalog-390px.png`).
  - [x] **430px** (large phone): Two-column catalog cards (193.8px cols); zero page horizontal overflow (`public-catalog-430px.png`).
  - [x] **768px** (tablet portrait): Three-column catalog cards; table container horizontal scrolling (`public-catalog-768px.png`).
  - [x] **1024px** (tablet landscape): Three-column catalog cards; full-width staff workspaces (`public-catalog-1024px.png`).
  - [x] **1440px** (desktop monitor): Four-column catalog cards; full review layout (`public-catalog-1440px.png`).
- [x] 200% Enlarged Text Adaptation:
  - [x] At 360px with 32px root font, catalog container queries dynamically switch to single column (296px) with zero page overflow (`public-catalog-360px-enlarged-text.png`).
- [x] Public Storefront Workflow:
  - [x] Dedicated customization page (`public-customize-*.png`).
  - [x] Multi-line order bag with add/edit/remove (`public-catalog-with-order-bag-1440px.png`).
  - [x] Contact/details validation feedback and preserved form input (`public-details-validation-errors-390px.png`).
  - [x] Tokenized payment screen with QR and reference instructions (`public-payment-pending-*.png`).
- [x] Staff Workspaces (Owner & Assistant):
  - [x] Full-width tables for Inventory (`/supplies`) and Expenses (`/expenses`).
  - [x] On-demand forms for receiving, usage, stocktake, and expense entry.
  - [x] Local container table scrolling (`tableScrolls: true`, `hasHorizontalScroll: false`).
  - [x] Mobile navigation drawer: hamburger toggle, scroll freeze, focus trap, Escape key restoration (`staff-owner-mobile-drawer-open-390px.png`).
- [x] Accessibility & Design Quality:
  - [x] Palette contrast ratios exceed WCAG AA 4.5:1 (ranging from 5.4:1 to 12.8:1).
  - [x] `:focus-visible` active with 3px solid outline.
  - [x] Skip-to-content links present.
- [x] UI Findings Logged:
  - [x] **UA-001**: Reports card reads "Retained cancellation deposits" and explainer cites deposit-only formula (P2).
  - [x] **UA-003**: Mobile row action buttons measure 37.8px height (< 44px recommendation) (P3).
  - [x] **UA-005**: Order cancellation JavaScript modal alert hardcodes deposit-only retention message (P2).

---

## 4. Section D — Reliability, Concurrency, and Authorization Audit
- [x] Isolated PHPUnit harness (`tests/ReliabilityTest.php`) executed (12 tests, 58 assertions, 0 errors, 0 failures):
  - [x] **HD01**: Schema integrity verified; all additive migrations intact; soft-delete and audit columns present.
  - [x] **HD02**: Duplicate checkout submission keys return existing order; duplicate inventory submission keys return existing operation; duplicate GCash references rejected.
  - [x] **HD03**: Atomic rollback verified on multi-row receipt; invalid second row rolls back entire batch without stock mutation.
  - [x] **HD03-C**: Overlapping stock contention verified between 2 independent PHP CLI worker processes using filesystem barrier; exactly 1 posted, 1 rejected, stock reconciled to 3.00 kg (`hd03-concurrent-workers.json`).
  - [x] **HD04**: Stale expense update (outdated version) and stale stocktake update (outdated version) rejected with ValidationException.
  - [x] **HD05**: Staged multi-line orders survive browser navigation, refresh, and validation recovery.
  - [x] **HD06-A**: Assistant mutation permissions verified at runtime: Assistant can create customers (201), create orders (200), record payments, and complete refunds (proves CR-01).
  - [x] **HD06-B**: Guests redirected to `/login` (302); inactive staff blocked with 403 Forbidden; private receipts protected from guest downloads.
  - [x] **HD06-C**: File uploads reject executable/script mimes (`.php`, `.exe`, `.txt`) and files >5MB with session validation errors; valid images accepted.
  - [x] **HD06-D**: Private payment token URLs protected; unauthorized requests return 404.
  - [x] **HD07**: Eager loading verified; query counts remain low (Orders: 5, Catalog: 5, Expenses: 2, Reports: 4).

---

## 5. Adversarial Audit of Prior Investigation
- [x] Dissected and verified all prior findings:
  - [x] Corrected fabricated SQL query and non-existent database columns in BC-001.
  - [x] Corrected line numbers, method signature, and validation rule citations in BC-002.
  - [x] Corrected fabricated line 48 in BC-004.
  - [x] Exposed table count hallucination in HD01 (15 tables checked, 30 present in MariaDB).
  - [x] Uncovered full financial mutation vulnerability of Assistant role (CR-01) beyond customer/order creation.
  - [x] Resolved open gaps B27 (stocktake notes validation) and B28 (secondary CRUD variants).
  - [x] Documented findings in `prior-investigation-critique.md`.
