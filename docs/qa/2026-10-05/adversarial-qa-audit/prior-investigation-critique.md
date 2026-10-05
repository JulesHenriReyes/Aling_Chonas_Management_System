# Adversarial Critique and Verification of Prior QA Investigation

**Audit Target:** IT12_Project (`Aling Chona Management System`)  
**Evaluation Date:** 5 October 2026  
**Auditor:** Antigravity Adversarial QA Inspector  
**Subject Under Review:** Prior Consolidated Investigation (`docs/qa/2026-10-05/consolidated-investigation/`)  

---

## Executive Summary of Adversarial Findings

The prior investigation delivered substantial value by executing the Section A Headless Chrome CDP automation suite (capturing 66 screenshots across 6 viewports) and constructing the isolated PHPUnit Section D reliability suite (`tests/ReliabilityTest.php`). However, an adversarial code audit reveals several critical shortcomings:

1. **Fabricated Code Snippets and Non-Existent Database Columns:** The prior investigator cited pseudo-queries and fabricated code snippets for three major findings (BC-001, BC-002, BC-004), citing non-existent database columns (`p.payment_status`), non-existent line numbers (`CatalogOrderRules.php:48` on a 36-line file), and inaccurate method signatures.
2. **Incomplete Scope on Assistant Role Permissiveness (CR-01 / OD-1):** The prior report tested only customer creation and order creation. It completely overlooked that the Assistant currently has unrestricted execution authority over **critical financial operations**: recording down and final payments, approving/rejecting GCash payment proofs, declaring bakery failures, and completing refunds. Furthermore, the prior proposed remediation plan would fail to secure `PaymentReviewController` and `RefundController`.
3. **Table Count Hallucination in Schema Verification (HD01):** The prior report repeatedly claimed that "all 19 tables in MariaDB aling_chona_db" were verified intact. In fact, their test only verified 15 tables, MariaDB contains 30 tables, and 7 core application tables were omitted from the check. The number 19 was blindly copied from a historical deployment document.
4. **Omission of UI Cancellation Modal Alert in Deposit-Only Hardcoding:** While UA-001 identified the reports card label, the prior report missed that the obsolete deposit-only policy is also hardcoded into the JavaScript confirmation dialog on `admin/orders/show.blade.php:402`.
5. **Resolution of Admitted Gaps:** The prior author's admitted gaps (B27, B28) contained misstated validation rules (`required_if:operation_type,stocktake` instead of `required_if:type,waste,stocktake...`). Both client and server validations have now been traced and verified.

---

## Detailed Item-by-Item Adversarial Analysis

### 1. Finding BC-001 / CR-02: Financial Reporting Undercount
- **Prior Claim:**
  In `app/Services/FinancialReportService.php:30-35`, the retained cancellation query strictly filters for `p.payment_type = 'down_payment'`. Under OD-2, customer cancellations retain all verified customer payments. Code cited:
  ```php
  $retainedQuery = DB::table('orders as o')
      ->join('payments as p', 'o.id', '=', 'p.order_id')
      ->where('p.payment_type', 'down_payment')
      ->where('p.payment_status', 'verified')
      ->where('o.status', 'cancelled')
      ->where('o.cancellation_reason', 'customer_cancellation');
  ```
- **Evidence Cited by Prior Worker:** `app/Services/FinancialReportService.php:30-35`.
- **What Code Actually Shows:**
  In `app/Services/FinancialReportService.php` lines 31-33:
  ```php
  $retained = $period->apply(DB::table('payments as p')->join('orders as o', 'o.id', '=', 'p.order_id')->where('o.status', 'cancelled')
      ->where(fn ($q) => $q->whereNull('o.cancellation_kind')->orWhere('o.cancellation_kind', 'customer'))->where('p.payment_type', 'down_payment'), 'o.cancelled_at')
      ->selectRaw("'cancellation_income' as kind, p.id as record_id, o.id as order_id, o.order_number as label, o.cancelled_at as event_at, p.amount, p.payment_method as method, NULL as category");
  ```
  - There is **no column named `payment_status` in the `payments` table**. Schema migration `2026_09_22_000007_create_payments_table.php` defines only `id`, `order_id`, `user_id`, `amount`, `payment_type`, `payment_method`, `reference_number`, `payment_date`, and timestamps. All records in `payments` represent verified payments; pending proofs live in `payment_proofs`.
  - There is **no column named `cancellation_reason` used here**; the query filters on `o.cancellation_kind = 'customer'`.
  - The variable is `$retained`, not `$retainedQuery`.
- **Corrected Finding:**
  The business defect is **verified**: `FinancialReportService.php:32` strictly filters `where('p.payment_type', 'down_payment')`, omitting final balance payments collected before cancellation and causing a ₱500 revenue undercount on fully paid cancellations. However, the prior worker cited a fabricated snippet with non-existent database columns.

---

### 2. Finding CR-01 / OD-1: Assistant Role Mutation Boundary Permissiveness
- **Prior Claim:**
  `AppServiceProvider.php:30-38` and `routes/web.php:49-88` permit Assistant to create customers (`POST /customers`, HTTP 201) and create orders (`POST /orders`, HTTP 200). Proposed fix: narrow gates `manage-customers`, `manage-orders`, `cancel-orders`, and `record-payments` to `'owner'`.
- **Evidence Cited by Prior Worker:** `ReliabilityTest.php:376` (exercising customer creation and order creation).
- **What Code Actually Shows:**
  1. The violation is **far wider** than customer and order creation. Under current routes (`routes/web.php:49-88`), the Assistant has full mutation power over:
     - **Down and Final Payments:** `POST /orders/{order}/payments` (`PaymentController::store`).
     - **GCash Receipt Verification & Approval:** `POST /payment-proofs/{proof}/accept` (`PaymentReviewController::accept`).
     - **GCash Receipt Rejection:** `POST /payment-proofs/{proof}/reject` (`PaymentReviewController::reject`).
     - **Declaring Bakery Failures & Initiating Full Refunds:** `POST /orders/{order}/bakery-failure` (`RefundController::store`).
     - **Confirming Completed Cash/GCash Refunds:** `POST /refunds/{refund}/complete` (`RefundController::complete`).
     - **Order Cancellation:** `POST /orders/{order}/cancel` (`OrderController::cancel`).
     - **Customer Updates:** `PUT/PATCH /customers/{customer}` (`CustomerController::update`).
  2. **Fatal Flaw in Prior Proposed Fix:** In `routes/web.php:83-87`, routes `proofs.accept`, `proofs.reject`, `orders.bakeryFailure`, and `refunds.complete` **do not use gates or authorize checks**; they are protected only by the outer group `middleware(['auth', 'role:owner,assistant'])`. Simply narrowing gates in `AppServiceProvider` leaves these critical financial routes **completely exposed** to the Assistant role!
  3. **UI Exposure:** Views `resources/views/admin/orders/proof-review.blade.php`, `resources/views/admin/orders/refund.blade.php`, and `resources/views/admin/orders/show.blade.php` render forms and buttons for these financial mutations to the Assistant without any role checks.
- **Corrected Finding:**
  Assistant mutation permissiveness is a critical security and compliance failure encompassing all financial and lifecycle actions. Remediation requires adding explicit middleware (`role:owner` or `can:...`) to routes in `routes/web.php`, adding `$this->authorize()` or role checks in controllers (`PaymentController`, `PaymentReviewController`, `RefundController`, `OrderController`, `CustomerController`), and wrapping action buttons in Blade authorization directives.

---

### 3. Finding BC-002: Customer Phone Number Normalization
- **Prior Claim:**
  `app/Models/Customer.php:68-70`:
  ```php
  public static function normalizePhoneNumber(?string $number): ?string {
      return preg_replace('/\D+/', '', (string) $number);
  }
  ```
  Submitting nondigits (`phone_number=abcdefgh`) passes `string|max:30` validation, is stripped to `""`, and persists as an empty string.
- **Evidence Cited by Prior Worker:** `Customer.php:68-70`.
- **What Code Actually Shows:**
  - Lines 68-70 of `Customer.php` are inside `findOrCreateMatching()`, building a query on `first_name` and `last_name`.
  - The actual `normalizePhoneNumber` method is located at **lines 39-55**:
    ```php
    public static function normalizePhoneNumber(?string $phone): string
    {
        if (!$phone) {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }
        return $digits;
    }
    ```
  - Validation in `CustomerController.php:41` is `'phone_number' => ['required', 'string', 'max:20']` (not `max:30`).
  - Validation in `PublicOrderController.php:114` and `OrderController.php:106` is `'phone_number' => ['required', 'string', 'min:7', 'max:20']`.
- **Corrected Finding:**
  The defect is confirmed: entering letters satisfying length rules (e.g. `'abcdefg'`) passes validation, is stripped to `""` by `normalizePhoneNumber()`, and persists as an empty string `customers.phone_number = ""`. However, the prior worker cited the wrong lines, wrong method signature, and wrong validation rules.

---

### 4. Finding BC-004: Public Checkout Past Pickup Date
- **Prior Claim:**
  In `app/Http/Requests/CatalogOrderRules.php:48` and `app/Services/OrderService.php:65`, rule `after_or_equal:today` checks UTC date. Accepts yesterday's Manila date at midnight boundaries.
- **Evidence Cited by Prior Worker:** `CatalogOrderRules.php:48`.
- **What Code Actually Shows:**
  - `CatalogOrderRules.php` has **only 36 lines in total**. Line 48 does not exist.
  - The actual validation rule is at **line 28**:
    `'pickup_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],`
  - In `OrderService.php:64-69`, the backend check is:
    ```php
    $pickupDate = Carbon::parse($data['pickup_date'])->startOfDay();
    if ($pickupDate->isPast() && !$pickupDate->isToday()) {
        throw ValidationException::withMessages([
            'pickup_date' => ['Pickup date cannot be in the past.'],
        ]);
    }
    ```
  - Because `after_or_equal:today` and `Carbon::parse()->isToday()` default to UTC, between 00:00 and 07:59:59 Manila time (16:00 to 23:59:59 UTC previous day), yesterday's Manila calendar date is treated as UTC "today" and accepted.
- **Corrected Finding:**
  The bug mechanism is verified, but line 48 was completely fabricated. The actual code locations are `CatalogOrderRules.php:28` and `OrderService.php:64-69`.

---

### 5. Schema Integrity & Table Counts (HD01)
- **Prior Claim:**
  Section 2: "All 19 tables verified present in MariaDB; additive migrations preserved without destructive drops."
  Section 5: "Verified 19 tables in MariaDB aling_chona_db intact; additive migrations intact; soft-deletes and audit tables functional."
- **Evidence Cited by Prior Worker:** `ReliabilityTest.php:58`, `evidence/hd01-schema-verification.json`.
- **What Code Actually Shows:**
  - In `ReliabilityTest.php:58` and `hd01-schema-verification.json`, the test checks only **15 tables**:
    `users`, `customers`, `products`, `package_options`, `add_ons`, `orders`, `order_details`, `payments`, `payment_proofs`, `refunds`, `supplies`, `inventory_operations`, `inventory_transactions`, `expenses`, `expense_audits`.
  - The actual MariaDB database `aling_chona_db` contains **30 tables**:
    24 business/application tables: `add_on_product`, `add_ons`, `customers`, `expense_audits`, `expenses`, `gcash_references`, `inventory_baselines`, `inventory_operations`, `inventory_transactions`, `order_add_ons`, `order_details`, `order_images`, `orders`, `package_option_inclusions`, `package_options`, `payment_proofs`, `payment_settings`, `payments`, `products`, `refunds`, `supplies`, `users`, plus `migrations`, `password_reset_tokens`, and 6 Laravel framework tables (`cache`, `cache_locks`, `jobs`, `failed_jobs`, `job_batches`, `sessions`).
  - Omitted from `ReliabilityTest.php:58`: `add_on_product`, `gcash_references`, `inventory_baselines`, `order_add_ons`, `order_images`, `package_option_inclusions`, `payment_settings`.
  - The number "19" was unthinkingly copied from historical doc `docs/local-expenses-repair.md:11` without verifying actual tables.
- **Corrected Finding:**
  Schema verification passed for the 15 tables checked, but the claim of "19 tables verified" is factually inaccurate. MariaDB has 30 tables, all intact.

---

### 6. Omission of UI Cancellation Modal Alert in Deposit-Only Hardcoding
- **Prior Claim:**
  Reported UA-001 strictly as: `resources/views/admin/reports/index.blade.php:23-28` headline card reads "Retained cancellation deposits" and explainer text states "Completed sales + retained cancellation deposits – valid expenses".
- **What Code Actually Shows:**
  The obsolete deposit-only policy is also hardcoded in the customer cancellation JavaScript alert on `resources/views/admin/orders/show.blade.php:402`:
  ```html
  <form action="{{ route('orders.cancel', $order) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? The deposit is retained under the customer-cancellation policy. For bakery failure, use the full-refund action instead.');">
  ```
  This misleads staff into believing only the deposit is retained even when a customer has settled the final balance.
- **Corrected Finding:**
  Both `reports/index.blade.php` and `orders/show.blade.php` require label and confirmation text updates to reflect Owner Decision 2 ("All verified customer payments are retained").

---

### 7. Resolution of Prior Admitted Gaps (B27, B28)
- **Prior Admitted Gap B27:** "While the source rule required_if:operation_type,stocktake is confirmed in InventoryService.php, an isolated automated HTTP submission sending an empty count reason was not executed in this round."
- **Investigation Result:**
  - In `InventoryService.php:34`, the rule is `'notes' => ['required_if:type,waste,stocktake,adjustment,reversal', 'nullable', 'string', 'max:2000']`. (The parameter is `notes`, not `reason`; the type is `type`, not `operation_type`.)
  - In `resources/views/admin/supplies/operation-form.blade.php:21`, Alpine template enforces `:required="['waste','stocktake'].includes(type)"` on the textarea `<textarea id="notes" name="notes">`.
  - Submitting an empty note on stocktake fails both client-side and server-side validation.
- **Prior Admitted Gap B28:** "Secondary CRUD Form Coverage: secondary administrative variants remain unexercised."
- **Investigation Result:**
  - Traced routes in `routes/web.php`: customer deletion does not exist (`except(['destroy'])`); product deletion does not exist (status toggled via `products.toggleStatus`); user management is strictly gated by `role:owner` and `can:manage-users` (`web.php:119-125`), returning HTTP 403 to Assistants.
