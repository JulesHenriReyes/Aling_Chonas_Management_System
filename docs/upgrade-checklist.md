# Coordinated workflow upgrade

Source: user requirements in pasted-text-1.txt, 2 October 2026.

## Constraints and design
- Preserve the existing uncommitted catalog/inclusion work and payment rules.
- Laravel, Blade, Alpine, existing cocoa/cream colors and Inter typography.
- Compact full-width operational tables; creation/editing on dedicated pages.
- Local table scrolling, 44px controls, visible focus, field errors and preserved input.
- No live data reset. Additive migrations only. Tests use isolated SQLite.
- No recipes, automatic ingredient deductions, invented purchase costs or implicit expenses.

## Implementation checklist
- [x] Read skill and repository, inspect current implementation, run baseline (78 tests / 636 assertions).
- [x] Storefront: catalog → dedicated customization → contact/pickup → existing payment.
- [x] Storefront: durable multi-line drafts, per-line extras/uploads, safe editing/removal and idempotency.
- [x] Inventory: full-width searchable/filterable/sortable paginated table and supply details/forms.
- [x] Inventory: atomic multi-row receipts, usage/waste, stocktake with stale-count detection.
- [x] Inventory: baseline reconciliation, immutable movement history and linked corrections.
- [x] Expenses: table workspace, filters, matching total, view/create/edit/void and read-only audit.
- [x] Reports: shared period resolver, month/range/custom/presets and timezone boundaries.
- [x] Reports: reconciled summaries/trends/tables/breakdowns/drill-downs/CSV and scoped snapshots.
- [x] Verification: focused integrity, authorization, ordering, date and reconciliation tests.
- [x] Verification: browser interaction and screenshots at 360/390/430/768/1024/1440px.
- [x] Delivery: migration/operating instructions, screenshots, test results and limitations.

## Evidence / notes
- Initial PHP suite: 78 tests passed, 636 assertions (before this upgrade).
- UI skill search: Data-Dense Dashboard is relevant to operational pages; retain bakery colors/fonts over suggested generic blue dashboard palette. Use progressive disclosure for storefront stages.
- Existing data uses separate supplies already. Legacy opening stock has no movement; establish an explicitly labelled reconciliation baseline at migration time.
- Final full suite: **100 PHP tests / 940 assertions**; **5 JavaScript tests** passed. Actual two-process stock concurrency and preservation of pre-upgrade customer/catalog/order/payment/refund/inventory/expense data are covered.
- Staff order creation was also completed in the browser using the shared package fields. Public checkout reached the existing unpaid payment page. Enlarged text and local table scrolling were verified.
- Complete [requirement evidence](workflow-verification.md), [screenshots](workflow-screenshots.md) and [migration/operating guide](workflow-upgrade.md).
- Initial verification kept the live database unchanged. Follow-up [local Expenses repair](local-expenses-repair.md) backed up and upgraded the regular port-8000 MySQL app, verified original values across 19 business tables and restored/migrated a temporary MySQL copy. No reset/reseed. Concurrent MySQL load and truthful legacy history limits remain documented.
