# Company requirement → implementation → fresh QA

Original sources are preserved in `evidence/company-sources.json` with hashes and native DOCX paragraph references. Historical project reports are context, not current passes. Status starts **Not tested** and is resolved in the final report.

| Requirement / authority | Current implementation | Evidence to gather |
|---|---|---|
| Web application, Laravel/PHP/Blade/Tailwind/MySQL; M3-P355 | routes/web.php, shared layouts, config/database.php | Regular 8000 render and read-only database preflight; isolated SQLite cannot prove MySQL readiness |
| Owner + Assistant daily staff; M1-P024–026; M3-P366 | AppServiceProvider gates, EnsureRole, StaffAccess | Both roles via HTTP and browser |
| Assistant views product/customer/payment records and updates order status; M3-P366 **plus owner clarification 5 October** | Current gates grant broader customer/order/payment/cancellation/report management | Direct mutation requests and UI affordances; inventory/expenses remain approved exceptions |
| 50% down payment reserves order; balance at pickup; M1-P034/039/042, M3-P131 | OrderService, PaymentReviewService, PaymentController | Exact deposits, submitted proof excluded from verified collections, completion needs settled balance |
| Cash/GCash and actual transaction verification; M1-P039, M3-P131 | Private payment pages, payment proofs/review/refund services | Accept/reject/replacement, amount/reference validation and privacy; no real money |
| Customized cake specifications/reference images; M2-P127/129, M3-P129/131 | CatalogPricingService, public drafts/shared package fields | Multi-line extras/design/images, uploads/refresh/back, saved snapshot prices |
| Owner pricing; M3-P129; **2 October approved fixed-catalog upgrade** | Owner catalog/options/add-ons, snapshots | Owner-only mutations, server revalidation, historical prices unchanged |
| Scheduled pickup; M1-P020/035/042, M3-P131 | Order lifecycle, PickupScheduleController | Readiness/completion, schedule, boundary rules; delivery scope not inferred |
| Shared stock/restocking; M1-P040, M2-P114; **2 October approved batch inventory** | InventoryService, SupplyController | Atomic grouped receipts/usage/waste/count; versions/units/baseline/reversals/concurrent writes; no costs/recipes inferred |
| Finance and expense breakdowns; M3-P161, M1-P025; **2 October approved expense/report behavior** | ExpenseService, FinancialReportService, ReportPeriod | Creator/editor/void audits, all-match total, dates/timezone, exact reconciliation/export |
| Phones currently used; M1-P045; proposed browser use M3-P320/355; **2 October approved responsive sizes** | Shared cocoa/cream CSS, Blade/Alpine components | Actual 360/390/430/768/1024/1440 renders, text, focus/touch/overflow/validation/navigation |
| Customer-cancellation deposit retention / bakery-failure full refund | Existing code and 2 October explicit preserve-existing-policy instruction; original DOCX does not define refund details | Verify preserved behavior separately; ask unresolved final-balance policy, never invent it |
| Scope/limitations completeness | M3-P368 heading followed by **M3-P370 “Insert text here”** | Record missing authoritative scope, ask only when it affects a decision |

Clarification received: “Follow Milestone 3’s narrower view/status role, while retaining the explicitly approved inventory and expense access.” This audit records discrepancies; no permissions are changed.
