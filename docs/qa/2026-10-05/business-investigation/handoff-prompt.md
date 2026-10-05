/boost
/goal

Investigate and QA IT12_Project by executing **WORKING RULE 4, SECTION A (UI/UX/accessibility), and SECTION D (reliability)** of the original audit. Then compile the consolidated final QA report using the existing B/C investigation and your fresh evidence. This is investigation/reporting only: **do not implement fixes**.

Project: C:\Users\User\Desktop\IT12_Project
Regular application: http://127.0.0.1:8000
Authoritative company folder: C:\Users\User\Desktop\Company informations
Original company documents: Milestone 1.docx; Milestone 2 (Revised).docx; Milestone 3 upd.docx
UI/UX skill: C:\Users\User\.codex\skills\ui-ux-pro-max\SKILL.md

CURRENT HANDOFF — read these first

- C:\Users\User\Desktop\IT12_Project\docs\qa\2026-10-05\business-investigation\report.md
- C:\Users\User\Desktop\IT12_Project\docs\qa\2026-10-05\business-investigation\checklist.md
- C:\Users\User\Desktop\IT12_Project\docs\qa\2026-10-05\business-investigation\matrix.md
- C:\Users\User\Desktop\IT12_Project\docs\qa\2026-10-05\business-investigation\execution-evidence-index.md
- C:\Users\User\Desktop\IT12_Project\docs\qa\2026-10-05\business-investigation\evidence\routes.json
- C:\Users\User\Desktop\IT12_Project\docs\qa\2026-10-05\business-investigation\evidence\company-original-sources.json
- C:\Users\User\Desktop\IT12_Project\docs\qa\2026-10-05\business-investigation\evidence\environment.json
- C:\Users\User\Desktop\IT12_Project\docs\qa\2026-10-05\business-investigation\evidence\final-verification.json

The B/C investigation was at revision 9dbd138bf7f02fae780fc7b6e2896e0a6a94b389. It used actual read-only MariaDB totals plus isolated in-memory SQLite server tests. It did not execute working rule 4, A, or D in this phase. Other artifacts in the parent QA folder belong to earlier, broader work before the exclusions: do not mix their counts/screenshots into this phase or call them fresh proof.

The actual regular database was aling_chona_db through the mysql driver on MariaDB 10.4.32; storage UTC, pickup/business Asia/Manila. Verify this again rather than assuming it remains available. The B/C outcomes were 28 existing tests / 264 assertions passed; 15 targeted tests / 109 assertions, 5 failures across four findings. Preserve these outcomes honestly; they are not a full-system pass.

OWNER DECISIONS — authoritative and already answered

1. Follow Milestone 3's narrower Assistant view/status role, retaining inventory and expense management access. Do not broaden customer/order/payment/refund mutations merely because current code permits them. Distinguish status updates from financial actions. Ask a concise multiple-choice question only if a specific residual role boundary remains ambiguous.
2. Customer cancellation retains **all verified payments**, including any final balance collected before cancellation. Bakery-failure refunds remain separately classified. Do not confuse pending/rejected receipt images with verified money. Clarify historical policy application before proposing retrospective recalculation; do not invent an effective date.
3. System tracks pickup only. Delivery arrangements remain outside the system.
4. Phones are the bakery's current devices. Desktop/tablet responsive support remains part of the requested design.

BUSINESS/DESIGN AUTHORITY AND SAFETY

- Read applicable AGENTS.md and the UI/UX skill. Verify important rules against the original DOCX, not only an old extract. Current explicit owner decisions supersede older conflicts. AI-written implementation/history documents and current code do not prove owner approval.
- Preserve the warm bakery identity and the direct 2 October design/workflow requirements. That original owner instruction is C:\Users\User\.codex\attachments\257651c6-5d88-45d0-8edd-676a18ba91eb\pasted-text-1.txt. Skill recommendations are guidance; label subjective optional design improvements separately from required behavior.
- Do not invent company facts/prices/policies/recipes/costs, screenshots, results, defects, certification or quality scores. Separate confirmed defects, potential risks, missing/conflicting requirements and optional improvements.
- Ask necessary company-rule questions with concise multiple-choice options and continue independent work.
- Preserve current production code/configuration, uncommitted work and business records. No live reset/reseed/migrations, account/password changes, business-record mutations, real payments/refunds, messages, publish or deploy.
- Mutation, recovery and concurrency tests require an explicitly disposable environment. **Before any migration/reset/write, verify the actual resolved connection/name/database through PDO inside the executing process.** Do not rely on phpunit.xml or assume environment variables override cached config. Prevent a temporary alias/name resolving to the regular database; verify the connection's configured name agrees with its actual name. Confirm safe file/storage roots too.
- Port 8000 is the regular app. Any fixture preview must use a clearly labelled different URL, database, engine and synthetic data. Do not silently revive port 8123 or present an isolated preview as proof the regular app works. Existing QA bootstrap/config can be inspected as a starting point; revalidate every guard and storage/cache path before use. Do not modify the regular .env to create a preview.
- Use existing browser tools/capabilities properly. Use authorized read-only regular staff sessions if available; otherwise report an access limit and use clearly labelled synthetic roles for mutation tests. Never bypass authentication or change a real account to gain access.

FIRST: update the checklist and execution matrix

Discover the current routes/screens/roles/modules from actual code/runtime. Cross-check the saved route register; do not assume it is unchanged. Follow matrix.md's E01–E08 execution sequence, concrete file-inspection map, viewport/state grid and final compilation contract. Preserve matrix IDs H4, HA01–HA06, HD01–HD07 and HF01, expanding each into independently testable rows where needed. For each row specify authority, expected behavior, actual file/route, role, environment/fixture, exact procedure, expected measurable outcome, evidence path, dependencies, result and remaining limits. B27/B28 are explicit B/C coverage gaps; retain them as such without silently expanding a strictly 4/A/D run.

WORKING RULE 4 — historical implementation/evidence investigation

Inspect these six files:
- C:\Users\User\Desktop\IT12_Project\docs\workflow-upgrade.md
- C:\Users\User\Desktop\IT12_Project\docs\workflow-verification.md
- C:\Users\User\Desktop\IT12_Project\docs\upgrade-checklist.md
- C:\Users\User\Desktop\IT12_Project\docs\local-expenses-repair.md
- C:\Users\User\Desktop\IT12_Project\docs\catalog-payments-update.md
- C:\Users\User\Desktop\IT12_Project\docs\catalog-inclusions.md

Treat them as implementation history, not authority or current passes. Map relevant claims to fresh verification. Record disagreements with original company documents/current owner decisions. Do not rerun old live deployment/reset helpers just because a document shows a command.

SECTION A — UI/UX AND ACCESSIBILITY

Inspect actual rendered public and staff screens, including Owner and Assistant workflows where accessible. Design/UI/UX is a central part of this phase, including proposed improvements grounded in evidence; do not limit it to screenshot collection or backend testing.

Check all of the following:
- Clear navigation, task flow and primary actions.
- Consistent warm bakery branding, typography, spacing and controls.
- Readable tables, filters, sorting, pagination and result counts.
- Inventory and Expenses as full-width table workspaces with forms opened on request.
- Phone/tablet/desktop behavior at **360, 390, 430, 768, 1024 and 1440px**.
- Two-column public catalog cards at normal phone text sizes.
- Enlarged text, image proportions and loading-related layout shifts.
- Local table scrolling without page-wide horizontal overflow.
- Keyboard navigation, visible focus, labels, contrast and practical touch targets.
- Dialog/drawer focus containment, Escape and focus restoration.
- Empty, loading, success and error states.
- Field-level validation, preserved input, unsaved changes and repeated clicks.

Cover actual public catalog/customization/contact/payment, staff ordering/detail/payment review/refund, dashboard/pickups/customers, catalog/settings/users, inventory operations/history and expense forms/history/reports where discovered. Keep forms/data mutations in the isolated environment. Include both fixture roles and direct role consequences. Save real screenshots labelled by URL, environment, role, viewport and state; source inspection/HTML assertions do not establish that a screen renders or behaves correctly.

Distinguish required fixes from optional design improvements. Recommend concrete changes with evidence and business purpose, preserving the approved stack/identity. Do not create a redesign or implement changes in this task. Reset temporary viewport/text overrides before finishing and preserve any necessary user handoff tabs.

SECTION D — RELIABILITY

Check with appropriate isolated tests:
- Database schema and migration readiness.
- Duplicate submissions and idempotency.
- Atomic rollback when one row fails.
- **Actual overlapping concurrent writes**, not only sequential requests.
- Stale edits/counts and record preservation.
- Refresh/back navigation, expired sessions and failed-request recovery.
- Authorization, upload restrictions and private receipt/reference access.
- Console/server errors, unnecessary queries, pagination and large-list behavior.

Run relevant existing tests only after proving isolation; inspect their assertions before treating green results as current business compliance. Some existing tests deliberately assume broad Assistant access or deposit-only cancellation income, conflicting with the current decisions. Add narrowly targeted QA tests separately from production code when needed; do not fix production code or silently rewrite tests to manufacture a passing report.

For concurrency use independent workers and evidence of actual overlap/barriers/contending writes, then reconcile final stock/payment/expense/order state. Label engine coverage explicitly: SQLite concurrency is not MariaDB/MySQL concurrency proof. Verify additive preservation against a disposable baseline/copy; do not run live migrations. Test all participating named connections in process, including workers, so a copied connection configuration cannot use the regular name/database accidentally.

Check both roles and guests/inactive staff via direct server requests. Prioritize Assistant customer mutation, staff order creation, payment/proof/refund mutations versus allowed view/status/inventory/expense actions. Verify private receipt/reference files and bearer links, invalid/oversized files, repeat references, staged image cleanup, cache/referrer behavior and public limits without exposing tokens/credentials in evidence.

Coordinate A/D overlaps once: repeated clicks, back/refresh, upload errors, expired session and recovery require both visible feedback and backend record integrity. Record separate conclusions for each; do not let a server assertion substitute for a browser interaction.

KNOWN B/C LEADS — verify applicability, do not implement

- BC-001: A fully paid customer cancellation retains both ledger entries, but report retention/operational result count only the deposit; CSV/trends/drill-down repeat the undercount. This conflicts with owner decision 2.
- BC-002: Nondigit phone input becomes an empty saved contact in public/staff create.
- BC-003: At UTC 2026-10-04 17:00 / Manila 2026-10-05 01:00, dashboard “today” selects the prior bakery date.
- BC-004: Public checkout accepts an already-past bakery pickup date at that calendar boundary.
- CR-01: Source gates/routes permit broader Assistant mutations; direct authorization verification was deferred.

Reproduce applicable leads in disposable fixtures and inspect UI consequences. Preserve B/C evidence without claiming the earlier agent performed A/D. If revision/config/company rules changed, state which existing conclusions need rerun and perform only the necessary revalidation.

CONSOLIDATED FINAL REPORT

Save new work under C:\Users\User\Desktop\IT12_Project\docs\qa\<actual-client-audit-date>\ with a distinct subfolder for this agent. Preserve the existing business-investigation folder. Include:
1. Concise readiness assessment with the verified scope, including unfinished/blocked areas.
2. Environment/source register: actual URLs, engines, role scopes, revision/dirty state and original documents reviewed; exclude credentials/customer details/bearer links.
3. Complete linked checklist and requirement/execution matrix using **Verified pass / Verified fail / Blocked / Not tested / Not applicable**. Deferred does not mean pass.
4. Prioritized findings: ID, P0/P1/P2/P3, affected workflow, authoritative expected behavior, actual behavior, reproduction, evidence links and proposed correction.
5. Exact fresh commands, engine/process/fixture details, exit statuses and test results; actual browser screenshot gallery for all requested sizes and meaningful states.
6. Explicit unresolved company rules, tooling/access limits, untested cases and engine-specific limits.
7. Prioritized proposed fix list for owner approval, separating mandatory business/functional corrections from UI design requirements and optional polish.

Use P0 for critical security/data-loss or business-stopping failures; P1 for major workflow/financial errors; P2 for material usability/reliability defects; P3 for minor polish. No speculative padding, invented scores, “100% tested” or “fully reliable” claims. Explain why evidence is indirect/insufficient when appropriate. Do not implement fixes.

Continue until each scoped area has an honest status and the consolidated report/evidence are complete. Finish with a concise summary and absolute links to the report, matrix, screenshots and proposed fixes.
