# QA checklist — 5 October 2026

Scope: audit and reporting only. Production code, configuration and business records must remain unchanged. Regular application: `http://127.0.0.1:8000`; any isolated fixture will have a separate labelled URL/database. Prior test reports are historical evidence.

## Investigation and safeguards

- [x] Read the current goal and UI/UX Pro Max skill.
- [x] Inspect the current revision and worktree: `9dbd138bf7f02fae780fc7b6e2896e0a6a94b389`, clean before audit artifacts.
- [x] Check ancestor/project instructions; only the empty user `.codex/AGENTS.md` was present in the checked locations.
- [ ] Read original company DOCX documents and record exact source references/hashes.
- [ ] Review all six requested implementation-history documents.
- [ ] Create a company requirement → implementation → test matrix.
- [ ] Verify the actual regular database/schema and establish a read-only preservation fingerprint.
- [ ] Prove actual in-process test database isolation before mutation tests.
- [ ] Identify and ask multiple-choice questions about genuine company-rule conflicts/missing rules.

## UI/UX and accessibility

- [ ] Render public and staff modules, including both roles where accessible.
- [ ] Verify navigation, task flows, primary actions and branding consistency.
- [ ] Verify full-width inventory/expense workspaces and requested-only forms.
- [ ] Check filters/sorts/pagination/counts/totals and empty states.
- [ ] Check 360/390/430/768/1024/1440px with actual screenshots.
- [ ] Check two-column phone catalog cards, real image proportions and enlarged text.
- [ ] Measure document overflow and local table scrolling.
- [ ] Check keyboard, visible focus, labels, contrast and touch targets.
- [ ] Check drawer/dialog focus containment, Escape and focus restoration.
- [ ] Check loading/success/errors, retained input, unsaved changes and repeat submits.
- [ ] Check relevant skill guidance, reduced motion and small-phone landscape; record native-only/dark-mode criteria as not applicable when appropriate.

## Functional and company-rule checks

- [ ] Public catalog/customization/contact/pickup/payment end-to-end.
- [ ] Multiple lines, edits/removal, refresh/back, quantities/inclusions/extras/theme/images.
- [ ] Server pricing/availability and historical snapshots.
- [ ] Shared staff ordering and customer records.
- [ ] Order lifecycle, pickup schedule and fulfillment.
- [ ] Deposit/balance, receipt verification and private access.
- [ ] Cancellation/refund rules with source-backed expectations.
- [ ] Grouped receiving, usage/waste, stocktake, negative/stale rejection.
- [ ] Units, baseline reconciliation, linked corrections and immutable history.
- [ ] No inferred recipes, order deductions or invented purchase costs.
- [ ] Expense create/edit/void, creator/editor, audit, totals and report effects.
- [ ] Stock changes do not implicitly create expenses.
- [ ] Both staff roles and direct server authorization, including private files.

## Reporting and reliability

- [ ] Independently reconcile completed sales/extras, collections, refunds, retention, expenses and operational result.
- [ ] Reconcile charts, exact tables, categories/methods, package performance, drill-downs and CSV.
- [ ] Check period URL consistency, inclusive dates, timezone/year/leap/invalid cases.
- [ ] Check zero and refund-only periods; current snapshots and per-supply unit limits.
- [ ] Verify migration readiness without live migration/reset.
- [ ] Verify idempotency, atomic rollback and genuinely overlapping concurrency.
- [ ] Verify stale edits/counts, session expiry and failed-request recovery.
- [ ] Check uploads/privacy, console/server errors, query/list performance and pagination.
- [ ] Compare original regular business records and production files after the audit.

## Delivery

- [ ] Report with scoped readiness assessment and environment/source register.
- [ ] Coverage matrix: verified pass/fail, blocked, not tested, not applicable.
- [ ] Prioritized findings with expected source, actual result, reproduction and evidence.
- [ ] Fresh exact commands/results and actual browser screenshots.
- [ ] Unresolved questions and explicit limits, including database-engine scope.
- [ ] Proposed fixes for approval; do not implement them.
- [ ] Complete the requirement-by-requirement audit and retain evidence links.
