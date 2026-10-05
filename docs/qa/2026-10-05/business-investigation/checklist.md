# Business investigation checklist — 5 October 2026

Scope: **B functionality/business rules and C reporting accuracy**. Working rule 4 (historical project reports), A (UI/UX/browser/accessibility), and D (reliability) are deferred. Mixed tests that execute deferred checks were not run. Test isolation is a required safety prerequisite, not a migration-readiness or reliability pass. A checked box means the investigation and status assignment are complete; it does **not** mean the behavior passed. See the matrix for exact evidence and remaining limits.

## Preparation
- [x] Read the latest attached prompt and explicit exclusions.
- [x] Inspect current revision/worktree and applicable instructions.
- [x] Read UI/UX skill as context; defer its UI evaluation to the next agent.
- [x] Reverify the original company DOCX hashes and native XML requirements.
- [x] Record decisions: narrower Assistant view/status role with inventory/expense exceptions; customer cancellations retain all verified payments; pickup-only tracking; phones currently used with tablet/desktop responsive support still required.
- [x] Generate route/module register and detailed requirement-to-test matrix before executing tests; finalize results afterward.
- [x] Prove in-process disposable SQLite isolation, including connection names.

## B — functionality
- [x] Catalog/customization/contact/payment server workflow and shared staff ordering; browser flow deferred A.
- [x] Multiple lines, ordinary add/edit/remove, quantities, inclusions, extras, theme and per-line reference association; image security deferred D.
- [x] Availability, server pricing, and saved historical price snapshots.
- [x] Customer matching, normalization, create/edit; required contact validation — **BC-002 fails** in public and staff creation.
- [x] Order lifecycle, selected pickup date/status and fulfillment prerequisites — dashboard today and past-date guards **BC-003/004 fail**.
- [x] Exact deposits/final balances, proof acceptance/rejection/replacement and unverified money exclusion.
- [x] Customer cancellation under owner clarification — report **BC-001 fails**; ordinary bakery-failure refund accounting passes in the fixture.
- [x] Ordinary shared stock receipt/usage/waste/count, fixed units, negative-stock rule, baselines and one linked correction; missing count reason source-inspected only (runtime Not tested).
- [x] Trace no recipe/order deductions/inferred costs; fixture confirms no implicit stock expense. No broad runtime absence claim.
- [x] Expense create/edit/void with reasons, original creator/editor/audit, all-match totals and report impact; missing-reason/stale attempts not exercised here.
- [x] Inspect discovered catalog/settings/user-management operations; normal Owner create/update fixtures pass, other existing variants remain Not tested. No customer/product/user deletion endpoint is assumed.
- [x] Compare Assistant permissions to source — **CR-01 discrepancy**; direct authorization/private-file tests deferred D.

## C — reporting
- [x] Independent known-amount fixtures for sales/extras, verified collections, completed refunds/net, retention, valid expenses and operational result — retention/result **BC-001 fails**.
- [x] Reconcile summary, trend values, categories/methods/packages, drill-downs and CSV; internally consistent retention still conflicts with the current owner decision.
- [x] Month/range/custom/preset URL periods, inclusive endpoints, business timezone, leap/year boundaries and invalid/incomplete inputs; only the recorded cases are claimed.
- [x] Zero-activity and refund-only cases.
- [x] Inspect current-snapshot/operational-result definitions and per-supply units; actual readability/chart interpretation deferred A.
- [x] Read-only regular MariaDB reconciliation for September and 1–5 October; distinguish it from fixture/HTTP evidence.

## Delivery
- [x] Scoped report, sources/environment, exact commands/results and evidence links.
- [x] Every matrix row has Verified pass / Verified fail / Blocked / Not tested / Not applicable; evidence scope is explicit.
- [x] Separate confirmed failures, source discrepancies, unresolved rules and optional suggestions.
- [x] Proposed corrections only; no fixes/configuration/business data changes.
- [x] Complete handoff prompt containing **working rule 4, section A, section D**, all deferred overlaps, matrix/evidence paths, safety rules and consolidated-report instructions.
- [x] Production-code/config file fingerprint remains unchanged. This is audit preservation, not a D reliability pass.

## Explicit deferral
All actual browser rendering, screenshots, viewports, visual/design judgment, accessibility, focus/touch, UI loading/errors and navigation tests belong to A. Migration readiness, replay/idempotence, rollback failure injection, concurrent writes, stale versions, expiry/recovery, direct authorization/upload security/privacy, console/errors/query-performance/large-list testing belong to D. The next agent must review the six historical project reports under working rule 4. No new execution of these checks occurs in this phase.
