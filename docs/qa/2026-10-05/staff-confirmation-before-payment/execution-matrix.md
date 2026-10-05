# Execution and acceptance matrix: staff confirmation before payment

Date: 5 October 2026  
Status: **All packages and acceptance cases below are planned; none are claimed implemented or passed.**  
Primary specification: [implementation plan](C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/staff-confirmation-before-payment/plan.md).

## Package register

| ID | Deliverable | Required evidence | Status |
|---|---|---|---|
| SC-00 | Source/data baseline and read-only classification of existing pending, paid, and receipt-bearing orders. | Baseline revision, file hashes, safe counts and exception register; current unrelated UI work preserved. | Planned |
| SC-01 | Additive review metadata and shared eligibility. | SQLite/MariaDB upgrade preservation; explicit new pending state; null legacy metadata preserved. | Planned |
| SC-02 | Staff confirmation and Owner decline with attribution. | Route/service authorization; atomic review, reason/acknowledgment validation; replay and conflict cases. | Planned |
| SC-03 | Confirmation-gated QR, receipt, deposit, and preparation. | Direct requests and service calls; before/after ledger, proof, reference, and file assertions. | Planned |
| SC-04 | Clear customer/staff review and payment controls. | Rendered state screenshots and interaction results; corrected copy and payment panel predicates. | Planned |
| SC-05 | Distinct review/deposit/booked queues and unchanged financial source rules. | Filter/count reconciliation, pickup-state labels, independent financial assertions. | Planned |
| SC-06 | Compatibility for existing paid orders and pre-change proof submissions. | Fixture outcomes and documented disposition of actual financial exceptions. | Planned |
| SC-07 | Workflow regression and real contention checks. | Exact commands, engine/isolation identity, exit codes, JUnit/results; no skipped policy failures. | Planned |
| SC-08 | Focused responsive and accessibility verification. | Browser/viewport/role/state register, redacted screenshots, keyboard/error/stale-form evidence. | Planned |
| SC-09 | Implementation handoff and future gateway requirements. | Completed task matrix, migration/rollback instructions, evidence links and outstanding limits. | Planned |

## Functional and direct-request acceptance

Use valid requests so a validation failure cannot mask missing authorization. Assert database **and file/reference** effects. Each rejected action must leave the relevant existing state unchanged, apart from explicitly recorded non-business framework behavior. Use saved server amounts and real private-link fixtures.

| ID | Scenario | Expected result | Verification |
|---|---|---|---|
| AC-01 | Submit a valid public order. | `pending` + review `pending`; private link and items/images retained; no payment/proof/refund/reference created; success explains staff review first. | Feature + browser |
| AC-02 | Owner creates an internal order. | Remains awaiting explicit review; creation alone cannot approve or record a deposit. Assistant creation remains denied. | Feature/service |
| AC-03 | Open an unreviewed private order page. | Summary and status/link visible; no QR, account payment instruction, receipt form, or claim of secured booking. | Response + browser |
| AC-04 | Call an unreviewed order's QR route directly, or submit a valid receipt POST. | Approval rule denies the action; no proof, uploaded file, reference reservation, or payment. | Feature/service |
| AC-05 | Owner attempts valid cash/internal GCash deposit before confirmation; call service directly too. | Rejected by approval prerequisite; no payment, reference reservation, or status change. | Feature/service |
| AC-06 | Active Owner or Assistant explicitly confirms a feasible pending request. | `confirmed` + review `approved`; actual actor/time saved; no financial rows; private page unlocks configured payment instructions. | Feature/service + browser |
| AC-07 | Confirmation without acknowledgment, with invalid/past pickup, or by guest/inactive user. | Correct validation/auth denial and no review/payment/status write. Public review fields cannot self-approve. | Feature/service |
| AC-08 | Generic status update targets `confirmed`, including a crafted unpaid request. | Cannot bypass the dedicated review action or its acknowledgment; no approval side effect. | Feature/service |
| AC-09 | Repeat an approved confirmation from a stale page. | Idempotent result or clear conflict; first reviewer/time preserved; no duplicate side effects or reset to unpaid/pending. | Feature/service |
| AC-10 | Owner declines a genuinely unpaid, unreviewed request with a reason. | `cancelled`, `staff_rejected`, review `rejected`, reason/actor/time saved; customer sees Request declined; zero financial/refund effects. | Feature/service + browser |
| AC-11 | Decline without reason, as Assistant/guest/inactive user, or after funds/legacy proof require reconciliation. | Denied under the defined boundary; no ordinary unpaid-decline classification or fabricated no-money statement. | Feature/service |
| AC-12 | Submit receipt for an approved public order. | One awaiting proof/file; order remains confirmed; ledger still unpaid; UI says awaiting verification and discourages a second transfer. | Feature/service + browser |
| AC-13 | Submit a second awaiting receipt or replay the submit. | Existing duplicate rules reject or safely reuse; no second awaiting proof/file or duplicate reference/payment. | Feature/service |
| AC-14 | Owner verifies matching real-account facts and the exact 50% deposit. | One verified deposit, proof/payment association, and unique reference; order remains confirmed; original approval audit preserved. | Feature/service |
| AC-15 | Wrong amount, fractional centavos, mismatched/used reference, stale proof, inactive/Assistant acceptance, or unapproved service call. | No verified payment/proof transition/reference reservation; existing exactness, role and reference guards retained. | Feature/service |
| AC-16 | Owner rejects an awaiting receipt on an approved order. | Order stays approved/confirmed; reason saved on proof; replacement allowed; customer is not told automatically to transfer again. | Feature/service + browser |
| AC-17 | Attempt Preparing after approval but before a verified deposit, including direct service call. | Rejected; approval alone and uploaded/rejected proof do not satisfy the deposit prerequisite. | Feature/service |
| AC-18 | Owner/Assistant starts Preparing after verified exact deposit. | Allowed normal progression; no new payment or duplicate approval. | Feature/service |
| AC-19 | Additional/early final payment before Ready for pickup or actual collection. | Existing advance cap and pickup guard still reject; balances/history unchanged. | Existing financial regression |
| AC-20 | Ready order is collected with Owner verification and acknowledgment. | Exact remaining balance and Completed persist atomically; failed attempts roll back; already-paid legacy orders are not charged twice. | Existing financial regression/contention |
| AC-21 | Customer cancellation and genuine bakery failure after verified payment. | Retention/full-refund distinctions and actual-return acknowledgment remain unchanged; staff rejection is not a substitute for either. | Existing refund/report regression |
| AC-22 | Try payment/receipt/status actions on declined, cancelled or completed requests. | Terminal guards hold; history/link remain readable; no reapproval or payment invitation. | Feature/service + browser |
| AC-23 | Review-only approvals and unpaid declines in financial reports. | Zero new collections/sales/refunds/retained deposits. Report totals/drill-downs/CSV still reconcile to verified events. | Independent centavo assertions |
| AC-24 | Review/awaiting-deposit/proof-review/booked queues and pickup views. | Mutually intelligible labels and correct filter/count results; confirmed-unpaid distinguishable from booked work; no new Assistant financial authority. | Feature + browser |
| AC-25 | Upgrade populated databases with old confirmed, pending, completed/cancelled, payment/proof/refund histories. | New nullable fields added; all old column values, ledger amounts, tokens and timestamps retained; no false reviewer. | SQLite + MariaDB migration rehearsal |
| AC-26 | Legacy pending request has an awaiting/rejected proof and may have transferred money. | No further payment invitation. Owner investigates; Assistant review of this financial exception is denied. Feasible request can be explicitly approved and existing proof verified without another transfer. Ordinary unpaid decline cannot discard possible funds. | Legacy fixture tests + exception register |
| AC-27 | Legacy confirmed/advanced order already has verified funds. | Existing work progresses under documented compatibility; no second deposit or fabricated audit record. | Legacy fixture tests |
| AC-28 | Legacy confirmed request has no verified money, or pending history has inconsistent verified funds. | Require real staff review before new collection/preparation; inconsistent funds routed to Owner reconciliation without reset/reclassification. | Legacy fixture tests |
| AC-29 | Saved private link, refresh/back, missing QR settings, and failed/stale submissions. | Link compatibility and submitted data/errors preserved; missing configuration never becomes an invitation to send money blindly. | Browser + feature |
| AC-30 | Future gateway invitation boundary in handoff. | Document approval before payment-session/QR creation, trusted verification before recording, idempotency, and late-event reconciliation; no provider endpoint or integration built now. | Documentation review |

## Authorization matrix

| Action | Active Owner | Active Assistant | Public customer / guest | Inactive staff |
|---|---|---|---|---|
| Inspect internal saved request | Allowed | Allowed | Internal routes denied; valid private own-order route separate | Denied |
| Confirm feasibility | Allowed | Allowed | Denied | Denied |
| Decline request | Allowed for eligible unpaid request | Denied | Denied | Denied |
| Initiate public manual deposit/proof submission | Valid private public order, approved and eligible | Same public path confers no staff financial authority | Valid own-order private link after approval | No staff privilege |
| Record internal deposit / accept or reject proof | Allowed after required approval | Denied | Denied | Denied |
| Start Preparing / mark Ready | Allowed when prerequisites met | Allowed when prerequisites met | Denied | Denied |
| Record pickup balance / financial refund actions | Existing Owner prerequisites | Denied | Denied | Denied |
| Inventory and expenses | Existing access retained | Existing access retained | Denied | Denied |

## Contention and preservation checks

Use separate workers against a held order row lock in the guarded disposable MariaDB environment. A sequential replay alone does not prove contention safety. Record actual schema, port, datadir, connection name, worker identity, transaction outcomes, and reconciled post-state.

| Case | Required invariant |
|---|---|
| Two confirmations of the same pending request | One actual review decision; first reviewer/time preserved; no financial writes. |
| Confirmation versus unpaid decline | One valid final decision; cannot end approved and staff-rejected simultaneously. |
| Receipt submission versus cancellation | Either an eligible proof commits before the state change, or submission fails without a file/proof leak; existing possible funds cannot be discarded. |
| Two attempts to accept/record the same deposit | One ledger payment and one unique reference; no double credit. |
| Preparing versus deposit acceptance | Preparation cannot commit without the deposit; once verified, a new valid attempt can proceed. |
| Pickup settlement/completion | Existing single settlement, exact balance, rollback, timestamp, and no-second-charge guarantees. |

Read-only regular-data comparisons distinguish additive review columns from existing business data. Compare old columns and ledger tables separately; "entire order-row hash unchanged" is not a valid assertion after deliberately adding review metadata. Record framework/session effects separately. Never mutate the regular database to create test cases.

## Rendered UI verification

Inspect changed screen families at **360, 390, 430, 768, 1024 and 1440 CSS px**. Add a 390px 200% root-text pass. Record viewport height, browser, role, fixture, state and screenshot path. Use synthetic data and redact private bearer URLs, receipt/account identifiers, and credentials from shared screenshots/evidence.

| Screen | States and interactions to verify |
|---|---|
| Public request/contact summary | Review-first helper and submit CTA; quote/deposit clearly distinguished from an immediate amount due; retained errors/details/images. |
| Private status/payment page | Under review; approved unpaid; missing settings; receipt awaiting review; receipt correction; deposit verified; declined; existing cancellation/refund/completed states. |
| Staff order review | Owner and Assistant; acknowledgment/error; long specifications/images; approval feedback; review attribution; decline reason; Assistant financial controls absent. |
| Staff proof/deposit/preparation | Approved-unpaid is not verified; receipt actions change independently; no unpaid preparation; exact amount; duplicate/stale/error feedback. |
| Order list/dashboard/pickup views | Review, awaiting-deposit, receipt-review and booked distinctions; correct filtering; tentative unpaid pickup label; phone layout and local table scrolling. |

Verify keyboard focus/order, visible status text, associated validation errors, submit feedback, 44px changed controls, and no page overflow. Preserve the existing phone catalog layout and concurrent skeleton/navigation improvements. Target the affected states; previous screenshot totals are not evidence for this new workflow.

## Test and handoff rules

Add focused workflow tests, for example `StaffConfirmationBeforePaymentTest`, and change only expectations genuinely superseded by the professor's rule. A prior test asserting that confirmation without a deposit fails becomes a test that explicit staff confirmation without a deposit succeeds **and preparation without a verified deposit fails**. Do not remove the payment guard without its replacement. Approval must precede payment setup in new fixtures; historical fixtures must be explicitly labelled.

Invoke the existing guarded PHPUnit harness directly; avoid broad setup/config-reset scripts that touch the regular runtime before isolation. Run the relevant migration, workflow, role, payment/refund, reporting, contact/calendar, and presentation regression checks. Perform the real-engine contention checks above, then the focused browser verification. Broaden testing only where failures or changed dependencies justify it.

The implementation handoff must record actual package/case status, commands, exit codes, engines, migration behavior, legacy disposition, UI evidence, and remaining limits. Keep this plan and prior investigations intact. Do not label gateway integration, physical-device testing, full accessibility certification, deployment, or unresolved legacy money cases complete without their actual evidence.
