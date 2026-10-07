# Staff-order modernization verification — 8 October 2026

The company documents establish the 50% deposit, Cash/GCash and balance at pickup. The user explicitly clarified that Owner creation counts as approval and that customer cancellation after a verified deposit must retain the 50% payment. These clarifications supersede the older cancellation-blocking implementation; they are distinguished from document requirements in [company-rules.md](../../../company-rules.md). No production migration or business-data changes were made.

| Verification | Result | Evidence |
|---|---|---|
| Complete guarded SQLite suite | 207 tests, 2,670 assertions pass | [Output](sqlite-suite.txt), [JUnit](sqlite-suite.xml), [PDO isolation](isolation.jsonl) |
| Guarded MariaDB-compatible suite | 202 tests, 2,600 assertions pass | [Output](mariadb-compatible.txt), [JUnit](mariadb-compatible.xml), [actual server proof](mariadb-initial-server-proof.json), [PDO isolation](mariadb-isolation.jsonl) |
| Independent-process MariaDB contention | Both creation workers return the same order; one reference file, no payment at creation. Both cancellation workers keep one original ₱500 deposit; collections and retained income are each ₱500, completed sales are zero. Workers demonstrably wait for held InnoDB row locks. | [Results](mariadb-concurrency.json), [Executable check](mariadb-concurrency.php) |
| JavaScript behavior | 9 tests pass | [Output](javascript-tests.txt), `tests/js/staff-workspace.test.mjs` |
| Browser flow and keyboard checks | 69 checks pass over nine staff page/width combinations; no horizontal overflow, broken images, server errors or application JavaScript errors. Staff actions have at least 44px targets. | [Results](browser-results.json), [Script](browser-verification.mjs), [Output](browser-tests.txt) |
| PHP and Blade | Changed PHP files pass syntax checks; all 132 Blade templates compile to guarded temporary storage | [Compiler result](view-compilation.json), [Compiler script](compile-views.php) |
| Working-tree preservation | 54 unrelated tracked diffs match the initial snapshot; seven concurrently changed inventory/expense/UI paths were left intact. All 19 original untracked paths remain. No new workflow migration; whitespace check passes. | [Initial status](before-status.txt), [Initial diff](before-existing-changes.patch), [Preservation proof](working-tree-preservation.json), [Concurrent differences](concurrent-unrelated-differences.txt) |

The five excluded MariaDB cases explicitly construct SQLite databases (`CatalogMigrationSafetyTest`, `StockConcurrencyAndMigrationTest`). They pass in the complete SQLite suite; they are not represented as MariaDB tests. The initial unfiltered MariaDB run and initial focused failing fixtures are retained as diagnostic history. Final results above are from corrected runs.

The new staff feature tests exercise separate designs of one package; repeated saves, edits, removal and tombstones; forged line/product/token rejection, including legacy-route attempts; five-image and 5 MB limits; private previews bound to Owner/session/draft/line; upload preservation during invalid edits; customer creation/search, pickup validation and detail recovery; catalog availability and compensating price changes; approved creation without payment; draft cleanup that preserves public/new drafts; Cash/GCash deposits and duplicate rejection; retained cancellation reports and ledger preservation. Assistants, inactive users and guests cannot use the new draft endpoints. The complete suites also cover public ordering, staff review, payment verification, pickup calendars, fixed-price snapshots and existing inventory/report behavior.

The browser exercises real CSRF and file sessions against a guarded temporary SQLite database. It stages a reference, reloads an unsaved design, saves two designs of the same package, edits/repeats a save, removes a line, checks remove-dialog focus trapping/restoration, creates an inline customer after field validation, selects a customer with Arrow Down/Enter, selects pickup time, reloads details, goes Back, retains a separate public draft, creates an approved staff order, replays its submission after cleanup, records a Cash deposit and cancels it while preserving its payment. Catalog photos and buyers are synthetic fixtures. Chrome reports its existing cross-document ViewTransition abort messages during rapid automated navigation; these are saved separately as browser warnings and do not affect order state or controls.

| Screen | 390px | 768px | 1440px |
|---|---|---|---|
| Selection and saved summary | [Image](selection-390.png) | [Image](selection-768.png) | [Image](selection-1440.png) |
| Customization and reference | [Image](customization-390.png) | [Image](customization-768.png) | [Image](customization-1440.png) |
| Customer, pickup and checkout | [Image](checkout-390.png) | [Image](checkout-768.png) | [Image](checkout-1440.png) |

Screenshots were visually inspected for long names, stacking, accessible actions and retained styling. The 768px long customization title initially overflowed; the shared title now shrinks/wraps correctly. Narrow staff action groups remain in normal flow, so they do not cover fields. [Verified-deposit cancellation](verified-deposit-cancellation.png) shows the retained-deposit explanation and restored action.

The disposable database server uses port 33317, schema `bakery_implementation_qa`, and a checked temporary `bakery-implementation-mysql-*` directory; the browser fixture uses port 8138 and a checked `workflow-concurrency-*.sqlite` file. Regular application connections are removed by the guards. Fixture servers are stopped after verification.
