# Implementation plan — 5 October 2026

Status: **Plan complete for the confirmed scope.** The owner clarified the payment timing and bakery-failure policy. The GCash amount QR feature is deferred. Historical financial records are preserved; any actual exception requiring a new money policy must be reviewed before reclassification. This request creates the plan. No application code, configuration, business records or test assertions are changed during planning.

Project: `C:\Users\User\Desktop\IT12_Project`  
Planning revision: `9dbd138bf7f02fae780fc7b6e2896e0a6a94b389`  
Regular application: `http://127.0.0.1:8000`  
Stack: Laravel / PHP / Blade / Alpine / Tailwind and shared bakery CSS.  
Companion: [execution and acceptance matrix](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/implementation-plan/execution-matrix.md).

## Authority and decisions

Original company documents remain the authority for company facts. Their hashes still match the native-DOCX source register. Explicit owner decisions resolve conflicts; implementation reports are evidence to assess, rather than business approval.

| ID | Rule / decision | State |
|---|---|---|
| OD-1 | Assistant views product/customer/payment/order records and updates normal order statuses; inventory and expense management remain allowed. Customer management, staff order creation and financial mutations belong to the Owner. | Confirmed from the earlier narrower-role decision and M3-P366; normal status changes must continue to enforce deposit/balance prerequisites. |
| OD-3 | Pickup tracking only; delivery coordination stays outside the system. | Confirmed. |
| OD-4 | Phones are current bakery devices; tablet/desktop responsive support is still required. | Confirmed. |
| IP-D01 | Customer phone validation accepts Philippine mobile and landline numbers. | Confirmed in this planning conversation. |
| IP-D02 | Financial reports, drill-downs and CSV exports are Owner-only. | Confirmed in this planning conversation. |
| IP-D03 | Exactly 50% at booking. The remaining balance is accepted only at actual pickup, after the order is Ready for pickup, and before Completed. | Confirmed. The selected actual-pickup answer and the written Ready-for-pickup prerequisite both apply. Maintain the existing centavo rounding and enforce the cap on the server. |
| IP-D04 | If the bakery cannot fulfil the order, the Owner records failure and all verified payments become a pending full refund. Completion of that refund requires confirmation that the money was actually returned. | Confirmed as a new owner decision. The interview did not itself establish this rule. |
| IP-D05 | The revised workflow prevents advance payments above 50%; it does not prescribe a retrospective rewrite of existing financial records. | Preserve history. The answer rejects the old early-full-payment workflow; it does not establish a new disposition for actual historical excess funds. Inspect only, and obtain a case-specific decision if such records exist. |
| IP-D06 | Automatic GCash QR containing the exact deposit amount. | Explicitly deferred by the owner for a future feature. Keep existing exact-amount verification; do not build an excess-payment/refund workflow in this implementation. |

The 50% advance cap and actual-pickup settlement timing are explicit owner decisions. Preserve actual cash/GCash verification and the documented balance-before-completion rule. A receipt image alone creates no verified payment. The existing non-refundable customer-cancellation deposit policy continues for the ordinary new workflow.

For a **₱2,000 order**, the only booking payment is **₱1,000**. A ₱2,000 booking payment, a deposit above ₱1,000 or an additional advance payment is rejected. The remaining **₱1,000** is collected only once the order is Ready for pickup and the customer is actually collecting it; the order is then Completed. If the customer cancels beforehand, the verified ₱1,000 deposit is retained. If the bakery cannot fulfil the order, verified money becomes a pending full refund, recorded as completed only after its actual return.

## Evidence reconciliation before changing code

Read [the final synthesis](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/adversarial-qa-audit/report.md), [the critique](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/adversarial-qa-audit/prior-investigation-critique.md), [the original B/C investigation](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/report.md), and their underlying evidence together. The critique primarily addresses the other agent's consolidated report; its quoted errors should not be attributed to every prior document.

| Observation checked during planning | Consequence for implementation |
|---|---|
| The actual synthesis matrix has 67 requirement rows: 52 marked pass, 14 fail and 1 Not tested. Its summary says 66 rows/51 pass. | Rebuild counts from rows in the new verification report. Counts do not prove coverage. |
| Screenshot folder has 68 PNG files, including two `test_*` setup captures; 66 remain after those are excluded. DOM register has 57 entries. | Label setup captures separately and specify which module/role/state/width each screenshot proves. |
| Saved reliability JUnit records 12 tests, 58 assertions, zero failures. Its Assistant test posts only customer/order creation and checks `/users` denial. Both creation responses are 302, rather than the report's 201/200. | Add direct financial-endpoint tests against the approved boundary. A green test that demonstrates prohibited access documents a defect; it does not prove permission compliance. |
| Payment/proof/refund services require active staff, but do not distinguish Owner from Assistant. Some controllers lack action-specific gates. | Protect routes/controllers and service entry points. Narrowing a few gates or hiding buttons alone leaves gaps. |
| `OrderService::updateStatus` delegates a `cancelled` status to `cancelOrder`; public creation and public receipt submission intentionally have separate actor rules. | Guard cancellation at its service boundary, including indirect calls. Preserve the legitimate public submission paths. |
| Shared CSS already sets many controls and row actions to a 44px minimum height. Saved mobile table metrics show a 36×44px small target; they do not contain the claimed 37.8px measurement. | UA-003 is a measurement lead. Identify the actual element and measure both dimensions before applying a targeted UI change. |
| B27/B28 are labelled runtime passes in the synthesis although the supplied explanations rely on source/primary flows. HD01 checks 15 tables and five columns in the isolated suite. | Close the specific missing runtime cases. Table presence alone does not prove additive migration preservation or upgrade readiness. |
| Current code permits staff final payments for an active confirmed order. The public verification path already checks the exact 50% deposit. | The new advance-payment rule chiefly changes the staff final-payment path and its UI; do not present an unchanged deposit guard as a new fix. |

The original B/C tests independently reproduced empty normalized phone contacts, wrong dashboard/past-date behavior at the UTC/Manila boundary, and deposit-only retained-income reporting on a fully paid synthetic customer cancellation under the earlier rule. Keep their recorded outcomes intact. The new payment timing changes the relevance of BC-001, UA-001 and UA-005: do not blindly apply the audit's proposed all-payments retention query or declare deposit-only labels obsolete. First prevent early final payment, reconcile ordinary deposit-only cancellations, and inspect actual legacy exceptions. Record the requirement change explicitly in the final verification report.

## Implementation sequence

| Phase | Work packages | Result / release condition |
|---|---|---|
| 0 | IP-00 | Register the confirmed decisions and deferred feature, map actual source/routes, preserve baseline, inspect legacy exceptions read-only, and prove every mutating test process disposable before running it. |
| 1 | IP-01–03 | Enforce Owner/Assistant boundaries across ordinary staff actions, direct requests, service calls, navigation and forms. Owner-only report access is effective. |
| 2 | IP-04–06 | Enforce the 50% advance cap, actual-pickup settlement and Owner-recorded bakery-failure refund. Preserve historical funds and identify any exception requiring a separate owner decision. IP-07 is deferred. |
| 3 | IP-08–09 | Reconcile reporting and all cancellation/payment explanations against the settled policy. Summary/trend/drill-down/CSV agree with independently calculated records. |
| 4 | IP-10–11 | Apply shared Philippine contact validation and a consistent bakery pickup calendar. Source/customer matching and historical timestamps remain preserved. |
| 5 | IP-12–13 | Correct measured phone-control ergonomics, role-specific task flow, inline errors, pickup/payment feedback and drawer behavior while retaining the bakery design. |
| 6 | IP-14–17 | Close evidence gaps, run meaningful regression/security/reliability and browser checks, produce before/after proof and a concrete operating handoff. |

Implement in this order, with independent phone/calendar work available while permission/payment changes are reviewed. Finish and verify each coherent change before proceeding. Additional fixes require a reproduced defect or an explicit requested requirement; a missing test alone is not a reason to redesign a module. Stop only the affected financial reclassification if an actual historical exception needs an owner decision; continue the other packages.

## Role implementation

Define capabilities separately for viewing, normal status changes and management. Use the same capability rules for server enforcement and rendered actions, requiring active staff. Keep inventory/expense capabilities available to both approved roles.

| Action | Owner | Assistant | Public / guest |
|---|---|---|---|
| View product/customer/order/payment information | Allowed | Allowed | Only intended catalog/private own-order paths; internal records require staff authentication. |
| Advance an order through valid normal statuses | Allowed | Allowed when the deposit/balance and lifecycle conditions are met | Denied. |
| Create/edit customer records through staff screens or inline APIs | Allowed | Denied | Intended public checkout may create/match its customer through the public path. |
| Create staff order/draft, attach staff reference images, cancel customer order | Allowed | Denied | Intended public draft/order/reference submission remains supported. |
| Record deposits/final balances; accept/reject proofs; declare bakery failure; complete refunds | Allowed under the approved policy | Denied | Upload a proof through a valid private own-order link; no staff verification or refund power. |
| Inventory and expenses, including normal audit-preserving corrections/voids | Allowed | Allowed | Denied. |
| Catalog/settings/user administration | Allowed | Product view only; administration denied | Denied. |
| Report screen, financial records drill-down and CSV export | Allowed | Denied | Denied. |

Protect all existing variants: form GETs, draft save/back, inline customer POST, store/update, image attachment, cancellation, proof accept/reject, payment and refund actions. A direct forbidden request should return the correct denial without changing orders, payments, refunds, customers or files. Explicitly test allowed Assistant normal status/inventory/expense operations so the fix does not remove approved daily work.

Do not change `StaffAccess::require` globally into Owner-only: it is also used by inventory and expenses. Introduce action-aware authorization or dedicated Owner checks for sensitive service methods and keep shared staff eligibility distinct. No new public login or frontend framework is needed.

## Payment, failure and reporting design

Final settlement requires a Ready-for-pickup order and an explicit Owner acknowledgment that collection is taking place. Use an Owner **Complete pickup** workflow that records the exact remaining balance and completion in one transaction, after real cash/GCash verification, preserving the existing ledger and timestamps. Compute amounts from the saved order on the server; a browser-supplied amount or status cannot bypass the cap. Cover already-paid legacy orders through the normal completion guard without charging twice. A rejected/duplicate/stale attempt must not create another payment, reserve a reference or falsely complete an order.

The staff UI must withhold final-payment controls while an order is Pending, Confirmed or Preparing. At Ready for pickup, show the remaining balance and actual collection/verification confirmation. Assistant users can perform their permitted normal status updates and view payment information; recording settlement remains Owner-only. Do not leave a separate unguarded final-payment endpoint that can collect a balance early or leave a newly settled order cancellable before completion.

Distinguish customer cancellation from bakery failure and customer late collection. The Owner declares actual bakery failure with a reason; a pending refund covers all verified money actually received. An unpaid order creates no invented monetary refund. Refund completion requires acknowledgment of actual return and the existing audit details. No automatic money transfer occurs. Completed pickup is terminal; a customer arriving late alone is not bakery failure. Review the current readiness-timestamp guard: on-time readiness alone must not prevent the Owner recording a subsequent genuine inability to fulfil an uncollected order. Record this policy change rather than silently removing the customer-lateness protection.

For ordinary new customer cancellations, retained income is the verified booking deposit, dated by the cancellation event. Bakery failures are excluded; their pending and completed refunds remain distinct, and payment collections keep their own event dates. Do not add an invented `payments.payment_status` filter. Check existing fully paid, overpaid and unclassified historical cancellations in a read-only preflight. The advance cap does not erase money already received. Preserve those records and flag any actual unresolved case for an owner decision before changing its refund or retained-income classification. Do not implement the previous report's all-payments query as an assumed answer to that case.

The automatic amount QR and any new excess-transfer resolution workflow are deferred. The existing static QR may still be used, with the exact required deposit shown clearly. The software can enforce recorded/accepted amounts; it cannot cap a transfer made directly in GCash. Retain existing mismatch feedback and actual-account verification, and ensure a proof for an excessive amount cannot be accepted as the exact deposit. Do not credit it as an early final payment or mark a refund completed. A future QR feature needs its own investigation of the payment provider's supported behavior before making an amount-enforcement promise.

Align report labels, metric definitions, exact-value tables, drill-downs, CSV definitions, public cancellation messages and staff confirmation with this workflow. A verified booking deposit retained on customer cancellation may correctly be labelled a deposit. Explain full refund for bakery failure and payment-at-pickup timing consistently. Operational result stays a defined business metric, with expenses/refunds correctly scoped, and is not presented as full accounting profit.

## Phone and pickup-calendar implementation

Use a shared, server-side Philippine contact parser/validator across customer create/edit, inline creation and public checkout, followed by consistent normalization and matching. Prefer maintained number metadata over a single guessed regex or an arbitrary seven-digit minimum. A small Composer dependency for core number handling may be necessary; verify PHP compatibility and mbstring, pin its resolved version and record the dependency change before implementation. No dependency is installed during planning.

The library supports region parsing, validation and fixed/mobile types; pattern validity does not prove a subscriber is reachable. Philippine metadata includes distinct mobile/fixed-line patterns. [Library documentation](https://github.com/giggsey/libphonenumber-for-php/blob/master/docs/PhoneNumberUtil.md), [Philippine metadata](https://raw.githubusercontent.com/giggsey/libphonenumber-for-php/master/src/data/PhoneNumberMetadata_PH.php), [dependency requirements](https://github.com/giggsey/libphonenumber-for-php/blob/master/README.md).

Accept domestic/international `+63` representations, ordinary formatting separators, supported mobile prefixes and complete landline numbers including their area code. Use the label **Mobile or landline number** and help text requesting the area code for landlines. Reject empty/nondigit/malformed/wrong-region input with field-level feedback. Do not silently assume a landline's area code. Preserve the entered value after rejection and the draft's other fields/uploads. Preserve existing records and identity matching; accommodate equivalent historical international landline representations when matching, and do not bulk merge or rewrite customers. A currently invalid old contact must not be guessed into a valid one.

Derive operational “today” from `bakery.pickup_timezone`, while retaining existing UTC payment/lifecycle storage and business-period conversion. Apply one shared date source to dashboard pickups, default pickup-schedule filtering, server booking validation, service checks and the public/staff date-input minimum. Test before/at/after Manila midnight plus month/year boundaries. Same-day production lead time is outside this fix and remains undefined; do not introduce a cutoff without owner approval.

## UI/UX scope

Preserve the existing warm palette, typography and table-first stock/expense workspaces. Keep forms on request, local table scrolling, two phone catalog columns at normal text scale and an enlarged-text fallback. Make permitted actions obvious for the actual role; retain read-only order/payment context without presenting forbidden forms.

Use the owner's requested approximately 44px touch ergonomics as a product target, measuring both width and height of relevant phone controls. Identify the 36px-wide target and validate row-action measurements first; the audit's 37.8px claim is not sufficiently supported. Apply specific shared classes/selectors to confirmed deficient controls rather than adding redundant global padding. Check 360/390/430/768/1024/1440px and enlarged text, focus visibility, drawer containment/Escape/restoration, preserved inputs, loading/error/retry feedback and page-versus-table overflow. These are interaction acceptance checks, not a blanket accessibility certification.

## Verification, preservation and delivery

All mutation/recovery/concurrency fixtures run on disposable storage/databases after actual PDO driver/name/database checks inside the executing process, including workers and named connections. Revalidate cached-config and storage paths; do not run setup/reset scripts against regular MariaDB or reuse the old browser scripts as-is. Label regular read-only 8000 evidence and any separate synthetic preview accurately; no silent 8123 substitution.

Use a new implementation-verification directory so previous evidence remains immutable. Add negative direct-request/service authorization cases and positive Owner/Assistant cases. Adjust existing tests that expect broad Assistant writes or early final-payment acceptance to the approved rules, retaining the original business validation assertions with properly authorized fixtures. Preserve historical reporting cases separately and explicitly record any policy-dependent unresolved result. Do not simply remove assertions or mark failing cases skipped.

Historical fully paid fixtures should be explicitly seeded as legacy records; they must not depend on an API path that the new rule prohibits. Ordinary reporting assertions follow the new deposit-only-before-pickup workflow; a legacy money-policy assertion needs an applicable owner decision. Independently reconcile centavo-based expected totals with summary, trends, records and CSV. Cover ordinary stock/expense operations, required reasons, stale edits, duplicate financial/refund actions, actual in-transaction rollback and meaningful concurrent contention. SQLite proof does not establish MariaDB locking; exercise a disposable MariaDB instance when available and report any remaining engine limit.

Only plan documents are created now. During later implementation, preserve uncommitted work, account credentials, order/payment/refund history, stock movements and expense audits. Core gate/date/report fixes should not require a live data reset. Any policy-driven new schema must be additive and verified against a disposable baseline first.

Delivery will contain the implemented-task matrix, before/after findings, exact commands and exits, new screenshots by role/width/state, financial reconciliation and preservation evidence, migration/dependency instructions where applicable, and remaining explicit limits. Each scoped defect must be resolved with evidence or deliberately deferred by the owner. Do not claim all audit areas pass merely because a suite is green.
