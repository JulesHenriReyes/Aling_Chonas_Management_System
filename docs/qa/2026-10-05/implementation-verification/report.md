# Implementation verification and handoff

5 October 2026. The in-scope implementation packages are implemented and verified. **IP-07, automatic GCash amount QR, remains deferred by the Owner.** The cream/cocoa palette, Inter typography, table-first staff workspace and two-column normal-phone catalog are preserved.

The regular application’s **24 business tables have identical before/after row counts and hashes**. `.env` is unchanged. The regular GET-only smoke check added one framework session row; this is recorded separately rather than described as zero database writes. No new migration, historical money reclassification, database reset, deployment or Git commit was performed.

## Delivered behavior

- Active Assistants can view customers, products, orders and payments; advance valid normal order statuses; and manage inventory and expenses. Customer/order creation and editing, image attachment, customer cancellation, financial actions, reports, settings and administration require an active Owner. Routes, controllers, service entry points and rendered controls enforce the applicable boundaries.
- Booking accepts exactly 50% once. The Owner completes pickup only after **Ready for pickup**, real collection and payment acknowledgment. The saved remaining balance is calculated on the server; final payment and completion commit together. Stale/replayed or rejected attempts preserve ledger/reference/status state. Historical fully paid orders can complete without another charge.
- The Owner can declare an actual inability to fulfil, with a reason and acknowledgment. A genuine failure may occur after on-time readiness; late customer collection alone is excluded. Verified funds become one pending full refund. Completion requires acknowledgment that the money was actually returned. Completed pickups remain terminal.
- New customer cancellations retain the verified booking deposit. The historical deposit-retention report query and actual payment history remain intact. Read-only preflight found **no historical cancellation exceptions or overpayments** requiring a separate Owner decision.
- A shared PH metadata validator accepts mobile numbers and complete Manila/provincial landlines, including ordinary formatting and `+63`. Invalid attempts preserve input and drafts without customer/order writes. Equivalent contacts match existing records; historical records are not bulk rewritten.
- Dashboard, pickup schedule, validation and date-input minimums share the bakery pickup calendar. Tests cover Manila midnight, month and year boundaries. UTC lifecycle storage remains intact; no same-day cutoff was invented.
- Measured controls received narrow target fixes. Error text is associated with its field without duplicate inline messages. Failed financial actions preserve relevant input and open their disclosure when necessary. Mutating action forms expose saving state and recover controls on browser restoration. Cancelled/completed buyer screens give status-specific instructions.

## Implemented package matrix

The original [plan](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/implementation-plan/plan.md) and [planned execution matrix](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/implementation-plan/execution-matrix.md) remain planning records. This table records execution.

| Package | State | Source and executed acceptance evidence |
|---|---|---|
| IP-00 preservation/isolation | Verified | `source-before.json`, `source-release.json`, `regular-before.json`, `regular-after.json`, `preservation-reconciliation.json`; actual SQLite PDO/PRAGMA and MariaDB schema/port/datadir/connection-name proofs in the isolation JSONL logs. Earlier evidence is retained. |
| IP-01 authorization boundaries | Verified | Gates, routes, request/controller authorization; `ImplementationPolicyTest` direct requests and bookmarks, active/inactive/guest cases, approved reads and allowed operations. |
| IP-02 service bypass protection | Verified | `StaffAccess`, order/draft/payment-review/refund services; direct service denials, indirect cancellation denial, file/ledger/reference snapshots and public creation/proof acceptance tests. |
| IP-03 role-correct interface | Verified | Navigation, orders, customers, proof and refund views; Owner/Assistant six-width grid and prohibited-action assertions. |
| IP-04 booking/pickup settlement | Verified | `OrderService`, `PaymentController`, pickup route and view; exact cents, early/duplicate/stale/acknowledgment/spoofed-amount cases, post-payment-write rollback, legacy no-charge completion; MariaDB booking/pickup contention. |
| IP-05 bakery failure/refund | Verified | Refund controller/service/view and buyer view; unpaid/deposit/legacy-paid failures, genuine post-readiness failure, terminal protection, actual-return acknowledgment and duplicates; MariaDB failure/refund contention. |
| IP-06 retained-deposit policy/history | Verified | Ordinary-workflow cancellation tests and independent report oracle; preserved historical query/ledger assertions; regular preflight exception lists empty. |
| IP-07 automatic amount QR | Deferred by Owner | No implementation. Existing exact-amount and account-verification checks remain; no claim that a static QR caps an outside GCash transfer. |
| IP-08 report reconciliation | Verified | `ApprovedWorkflowReportAcceptanceTest`: independent centavo expectations from ordinary service workflows; summaries, trends, available drill-downs, CSVs and payment-method totals; zero/collection/cancellation/refund-only periods. Existing period/leap-year/timezone tests also pass. |
| IP-09 truthful policy wording | Verified | Owner pickup/refund and buyer payment/status views; existing report/CSV deposit labels and operational-result definition retained; final terminal-state browser captures. |
| IP-10 PH contact validation | Verified | Shared support/rule/model/controller/request usage, phone package manifest/lock; `ContactAndPickupCalendarTest` all entry points, equivalent matching, invalid historical preservation, draft/image preservation; inline valid/error/retry/expiry browser checks. |
| IP-11 pickup-local calendar | Verified | `PickupCalendar`, dashboard/schedule/request/service/date input; public/staff/service boundary assertions with UTC storage and prior records preserved. |
| IP-12 measured touch targets | Verified | Shared scoped CSS and customer action wrapper; styled before/after measurements, six-width grid, final drawer measurements for both roles. |
| IP-13 workflow interactions | Verified | Shared UI and customer picker JS, financial error rendering; Tab/Shift+Tab/Escape/restoration, server errors/no-write snapshots, stock recount/replay, unsaved edit warning, retry/expired-session feedback, upload refresh and retained association after contact error. |
| IP-14 reliability gaps | Verified | Real missing-reason/no-write and product-toggle/inactive tests; actual post-first-stock-write rollback; supply creation/inline customer/private-file/replay cases in regression; additive migration preservation on SQLite; 8 held-lock MariaDB contention cases. |
| IP-15 updated policy fixtures | Verified | Superseded Assistant financial fixtures use Owners; normal Assistant status/stock/expense assertions remain. Early-final cases are rejected, pickup cases become atomic, historical cases are explicitly seeded. No tests are skipped to obtain the pass. |
| IP-16 rendered acceptance | Verified | `browser-grid-*.json`, interaction, terminal, refund-only and navigation evidence. 360/390/430/768/1024/1440×844 CSS px; separate 200% root-text pass at 390px. |
| IP-17 handoff | Verified | This report, exact command/evidence register, source/data reconciliation, dependency decision, operating instructions and explicit limitations below. |

## Executed checks

All paths below are relative to the project root. PowerShell used the explicit bundled executables shown; redirected output and JUnit files are saved under this folder’s `evidence` directory.

| Check | Exact executable/arguments | Exit and result |
|---|---|---|
| Full regression | `C:\xampp\php\php.exe vendor\phpunit\phpunit\phpunit --log-junit docs\qa\2026-10-05\implementation-verification\evidence\final-regression-junit.xml` | 0; **123 tests, 1,620 assertions**; `final-regression.txt`. |
| Final buyer copy/presentation validation | `C:\xampp\php\php.exe vendor\phpunit\phpunit\phpunit tests\Feature\UiPresentationTest.php tests\Feature\ImplementationPolicyTest.php --log-junit docs\qa\2026-10-05\implementation-verification\evidence\final-presentation-junit.xml` | 0; **11 tests, 236 assertions** after the final terminal-copy change. The full regression above preceded this small copy change. |
| MariaDB acceptance | Set `BAKERY_QA_MYSQL_MANIFEST` to the absolute `evidence\mariadb-fixture.json` path, then `C:\xampp\php\php.exe vendor\phpunit\phpunit\phpunit tests\Feature\ImplementationPolicyTest.php tests\Feature\ContactAndPickupCalendarTest.php tests\Feature\OperationalReliabilityAcceptanceTest.php tests\Feature\ApprovedWorkflowReportAcceptanceTest.php --log-junit docs\qa\2026-10-05\implementation-verification\evidence\mariadb-acceptance-junit.xml` | 0; **23 tests, 675 assertions**, disposable MariaDB **10.4.32** on port **33317**. |
| MariaDB contention | Same manifest environment; `C:\xampp\php\php.exe docs\qa\2026-10-05\implementation-verification\mariadb-concurrency.php` | 0; **8 cases, 16 workers**. Both workers waited on a held InnoDB row lock before release in every case. Stock/payment/refund amounts and history reconciled. |
| Six-width UI grid | Bundled Node executable, `docs\qa\2026-10-05\implementation-verification\browser-acceptance.mjs` | 0; **455 records, 441 screenshots, 0 measured failures**. Includes 7 enlarged-text captures. |
| Error/retry/draft interactions | Bundled Node executable, `docs\qa\2026-10-05\implementation-verification\browser-interactions.mjs` | 0; **72 screenshots** plus grouped effect records; `browser-interactions-metadata.txt` and `browser-interactions.json`. |
| Terminal buyer states | Bundled Node executable, `docs\qa\2026-10-05\implementation-verification\browser-terminal-states.mjs` | 0; **24 captures**. Cancelled/completed pages have no receipt-upload action or booking-payment invitation. |
| Visual references/refund-only/regular smoke | Bundled Node executable, `docs\qa\2026-10-05\implementation-verification\browser-additional-evidence.mjs` | 0; **93 captures**: 78 original-presentation references, 12 future-period refund-only report/drill-down captures, 3 regular GET-only smoke captures. |
| Final open navigation targets | Bundled Node executable, `docs\qa\2026-10-05\implementation-verification\browser-navigation-controls.mjs after` | 0; 8 role/width records; no small drawer target, no page overflow, Escape focus restored. |
| Shareable payment screenshots | Bundled Node executable, `docs\qa\2026-10-05\implementation-verification\browser-redacted-payment-evidence.mjs` | 0; readonly synthetic private-link displays redacted in recaptured payment images. Route metadata is also redacted. |
| Source/data preservation | Bundled Python executable, `docs\qa\2026-10-05\implementation-verification\reconcile-preservation.py` | 0; 24 business tables unchanged, `.env` hash unchanged, only the pinned phone dependency added. |
| PHP/Blade lint and diff whitespace | PHP `-l` for each changed/new PHP or Blade file; `git -c core.autocrlf=false diff --check` | 0; 53 files linted; `php-lint.txt`, `diff-check.txt`. Rendered routes additionally compile their Blade views. |
| Composer validation | `C:\ProgramData\ComposerSetup\bin\composer.bat validate --no-check-publish --no-interaction` | 0. `--strict` returns 1 for the deliberate exact-version constraint warning; both outputs/exits are retained. |
| Dependency audit | `C:\ProgramData\ComposerSetup\bin\composer.bat audit --format=json --no-interaction` | 1; two advisories in the pre-existing locked `league/commonmark` **2.10.1**. Phone package has no reported advisory. See limitations. |

Bundled Node path: `C:\Users\User\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe`. Bundled Python path: `C:\Users\User\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe`. Chrome **154.0.8037.97**, PHP **8.2.12**, Laravel **12.69.2**.

Earlier failing runs are retained for diagnosis and are **not** acceptance results. The initial safe baseline had three reporting-date fixture failures around the UTC/Manila date boundary. Subsequent intermediate failures included superseded policy fixtures, missing fixture data and browser harness selectors. The current accepted files named above identify the successful runs. The upload preview initially emitted a PHP startup notice before JSON because its sandboxed upload directory was unusable; a disposable writable upload directory corrected that environment issue. Regular PHP configuration was not altered.

## Rendered evidence and measurements

The screen/state/role/viewport registers contain exact screenshot paths and redacted route URLs. This is actual Chrome rendering of synthetic data, supported by DOM geometry and behavioral assertions. Representative screenshots were visually inspected; it is not a claim of a human inspection of every image or a full WCAG certification.

| Evidence family | Accepted register | Coverage |
|---|---|---|
| Catalog/customization/contact | `browser-grid-360.json` through `browser-grid-1440.json` | Normal-phone two columns; multi-line packages; formatted landline; invalid contact with preserved input; refresh/back/date minimum; no page overflow. |
| Buyer payment/refund | Same grid plus `browser-terminal-states.json`, `redacted-payment-captures.json` | Pending, awaiting, rejected, verified, cancelled, refund pending/completed, completed pickup; truthful proof/payment/refund distinction. |
| Staff daily work | Same six grid files | Both roles: dashboard, pickup lists/selected empty date, orders and eight lifecycle/refund detail states. |
| Customer/inline flow | Grid and `browser-interactions.json` | Owner forms, Assistant read-only records, invalid phone, valid landline, save busy state, network retry and actual HTTP 419 with input retained and no write. |
| Stock/expenses | Grid and interaction register | Both roles’ tables, operation/edit/detail/history forms; missing reason, stale stock, explicit fresh recount, accepted count and identical replay/no duplicate; unsaved edit navigation warning. |
| Reports | Grid and `browser-additional-evidence.json` | Owner period, empty report, cancellation drill-down; refund-only month with net collections **−₱1,000**; Assistant 403. |
| Navigation/targets | Grid and `navigation-controls-after.json` | Drawer entry, forward/reverse focus wrap, Escape/restoration; final close/brand/open controls and all measured drawer links at 44px minimum. |
| Enlarged text | 390px grid records with `textScale: 2` | Public catalog falls to one column; both roles’ supplies, expenses and ready-order details remain within the page width. This is 200% root-font scaling, not an OS/browser-zoom certification. |

Measured fixes: open navigation **36×44 → 44×44**; Unit sort heading **37.6×44 → ≥44×44**; customer View/Edit links **33.1×17 / 25.0×17 → ≥44×44**; bakery-failure disclosure **40px → 44px** height; drawer close **42×44 → 44×44**; drawer brand link **41.5px → 44px** height. Existing order/stock row buttons already met the target. Tables retain local horizontal scrolling rather than page overflow.

Before/after control registers are `controls-before-styled.json`, `controls-after-styled.json` and the navigation before/after registers. Initial unstyled captures are diagnostics and are excluded. The 78 `baseline-*` screenshots use original tracked views/CSS/JS from revision **9dbd138bf7f02fae780fc7b6e2896e0a6a94b389**, current read controllers/gates and the disposable fixture database. They are **presentation references**, not an assertion that the old backend/authorization ran. `visual-baseline.json` records that distinction and exported file hashes.

The first source fingerprint was taken after preparing the test-isolation harness and before production changes. Git HEAD supplies the original tracked production/presentation reference. Subsequent source snapshots are preserved rather than overwriting the initial register.

Regular smoke is explicitly `http://127.0.0.1:8000`: catalog, login, and guest `/orders` redirect to login. It does not substitute for authenticated mutation tests. Synthetic preview is **8124**; the GET-only presentation reference was **8125**. Fixture manifests may contain synthetic bearer tokens to reproduce tests; report route metadata and shareable payment screenshots redact them. No real private order links were used in browser testing.

## Dependency and operation handoff

The only new locked dependency is **`giggsey/libphonenumber-for-php-lite:9.0.40`**. See [dependency decision](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/implementation-verification/dependency-decision.md). No existing locked package version was changed or removed. Install from the updated lock file with `composer install --no-scripts --no-plugins`; the phone package needs no discovery hook. Do not run the project’s broad setup script or reset the database to deploy this change. There is **no new schema migration** in this implementation.

For staff operation:

1. The Owner records/verifies the exact booking deposit. For a public order, review its GCash proof and actual business-account receipt. An uploaded image alone is not a verified payment.
2. Either active staff role can advance confirmed → preparing → Ready for pickup. Readiness alone does not record the final payment.
3. At actual collection, the Owner verifies the saved remaining amount, selects cash/GCash, supplies the GCash reference if applicable and acknowledges collection/payment before **Complete pickup**. A historical fully paid ready order uses normal completion without another charge.
4. Customer cancellation is Owner-only and retains the verified deposit. A real bakery failure uses its separate reason/acknowledgment action. Complete the pending full refund only after returning the money, entering its reference and acknowledging the return.
5. Enter full landline area codes; ordinary spacing and `+63` are supported. The validator checks number metadata, not reachability. Use the displayed bakery date for pickup work.
6. For stale stocktake errors, reload current stock, physically recount and enter the fresh count. The reload deliberately clears the previous quantity. Inventory/expense permissions remain available to both active roles.

To reproduce UI QA, run `create-preview.php`, then start the PHP development server on 8124 using `preview-router.php` and the manifest’s disposable storage `uploads` directory as `-d upload_tmp_dir=<path>`. The accepted preview also used `-d display_errors=Off` to keep JSON responses clean. Use only synthetic `owner@implementation.test` / `assistant@implementation.test`, password `preview-test-only`. These credentials belong to the disposable fixture database. The standard PHP upload directory must be writable in any deployment runtime.

For ordinary regression, invoke PHPUnit directly so the disposable guard runs before migrations; do not use a command that first clears regular application configuration. MariaDB tests require the manifest from `prepare-mariadb.ps1` and the dedicated local server; every parent/worker checks actual schema, port, datadir and connection name before writes. Never point that manifest at the regular database.

## Remaining limits

- IP-07 is intentionally deferred. Manual GCash verification and refund acknowledgment remain required; recorded-amount validation cannot prevent an outside transfer.
- Two existing CommonMark advisories are documented in `composer-audit.json`: [raw-HTML filtering bypass](https://github.com/advisories/GHSA-97jj-33gv-5xf9) and [table-parser denial of service](https://github.com/advisories/GHSA-3q6v-r5mr-hxv8). Their application exposure was not assessed and unrelated dependencies were not upgraded in this plan.
- Full regression/additive-migration preservation used disposable SQLite. MariaDB coverage comprises the 23 selected acceptance tests and eight contention cases, not the entire regression or every migration scenario. Other database engines were not tested.
- Browser evidence uses desktop headless Chrome and specified CSS widths/root-text scaling. Physical devices, assistive technologies, external payment providers and production deployment were not tested.
- Existing payment/stock/expense histories and any undocumented old invalid contact values are preserved. Synthetic historical money cases in tests are not actual regular-database exceptions.

No in-scope implementation blocker remains. The separate QR feature and dependency advisories retain the limits above.
