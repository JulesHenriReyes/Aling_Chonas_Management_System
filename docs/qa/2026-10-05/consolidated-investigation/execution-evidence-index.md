# Consolidated QA Execution and Evidence Index — 5 October 2026

Repository: `C:\Users\User\Desktop\IT12_Project`  
HEAD Revision: `9dbd138bf7f02fae780fc7b6e2896e0a6a94b389`  
Regular Application: `http://127.0.0.1:8000` (Laravel 12.69.2 / PHP 8.2.12 / MariaDB 10.4.32)  
Audit Date: 5 October 2026 (Client date / Asia/Singapore; Bakery business/pickup Asia/Manila)

---

## 1. Evidence Registers & Metadata

| Artifact Path | Format | Description / Scope |
|---|---|---|
| [environment.json](evidence/environment.json) | JSON | Regular application runtime, MariaDB connection, versions, timezone config |
| [routes.json](evidence/routes.json) | JSON | 88 discovered application routes, methods, names, middleware, actions |
| [viewport-state.json](evidence/viewport-state.json) | JSON | Automated measurements across 57 rendered browser states and 6 viewports |
| [working-rule-4-analysis.md](working-rule-4-analysis.md) | Markdown | Detailed evaluation of the six historical documentation files vs authority |
| [reliability-junit.xml](evidence/reliability-junit.xml) | JUnit XML | Section D reliability test results (12 tests, 58 assertions, exit 0) |
| [test-isolation.jsonl](evidence/test-isolation.jsonl) | JSONL | In-process PDO database verification logs for every executing test connection |
| [hd01-schema-verification.json](evidence/hd01-schema-verification.json) | JSON | Additive migration & table existence verification |
| [hd03-atomic-rollback.json](evidence/hd03-atomic-rollback.json) | JSON | Proof of atomic database transaction rollback on failed multi-row receipt |
| [hd03-concurrent-workers.json](evidence/hd03-concurrent-workers.json) | JSON | 2 independent worker processes contending on stock with barrier |
| [hd06-assistant-role-boundary.json](evidence/hd06-assistant-role-boundary.json) | JSON | Direct server test proof of Assistant customer/order mutations (CR-01) |
| [hd06-guest-inactive-security.json](evidence/hd06-guest-inactive-security.json) | JSON | Guest redirects (302) and inactive staff rejection (403) proof |
| [hd06-upload-security.json](evidence/hd06-upload-security.json) | JSON | Mime validation and >5MB file rejection proof |
| [hd07-query-counts.json](evidence/hd07-query-counts.json) | JSON | Query count benchmark on primary staff views (eager loading verified) |

---

## 2. Integrated B/C Investigation Evidence (Carried Forward)

| Artifact Path | Format | Findings Supported |
|---|---|---|
| `../business-investigation/evidence/customer-cancellation.json` | JSON | BC-001: ₱1,000 paid order retains ₱500 in reporting due to down-payment filter |
| `../business-investigation/evidence/customer-cancellation.csv` | CSV | BC-001: Synthetic CSV export repeating the ₱500 retention undercount |
| `../business-investigation/evidence/csv-independent-decimal-check.json` | JSON | BC-001: Decimal math verification of retention divergence |
| `../business-investigation/evidence/customer-phone.json` | JSON | BC-002: Staff customer creation persists empty phone after normalization |
| `../business-investigation/evidence/public-phone.json` | JSON | BC-002: Public checkout creates order with empty normalized contact |
| `../business-investigation/evidence/dashboard-pickup-date.json` | JSON | BC-003: Frozen boundary test showing yesterday's pickups selected |
| `../business-investigation/evidence/past-pickup-date.json` | JSON | BC-004: Public checkout accepting already-past bakery date at UTC/Manila boundary |
| `../business-investigation/evidence/regular-report-reconciliation.json` | JSON | Independent MariaDB SELECT oracle vs report service totals (₱3,400 sales, ₱100 expenses) |

---

## 3. Fresh Browser Screenshot Gallery (Section A)

All screenshots are real rendered PNG files captured via Headless Chrome 134 CDP at the requested viewports:

### Public Storefront
- **360px:** [public-catalog-360px.png](screenshots/public-catalog-360px.png) (2-column cards, 158.8px cols, 0 overflow)
- **360px Enlarged Text:** [public-catalog-360px-enlarged-text.png](screenshots/public-catalog-360px-enlarged-text.png) (32px root font / 200%, 1-column adaptation, 0 overflow)
- **390px:** [public-catalog-390px.png](screenshots/public-catalog-390px.png) (2-column cards, 173.8px cols, 0 overflow)
- **430px:** [public-catalog-430px.png](screenshots/public-catalog-430px.png) (2-column cards, 193.8px cols, 0 overflow)
- **768px:** [public-catalog-768px.png](screenshots/public-catalog-768px.png) (3-column cards, 229.3px cols, 0 overflow)
- **1024px:** [public-catalog-1024px.png](screenshots/public-catalog-1024px.png) (3-column cards, 304.3px cols, 0 overflow)
- **1440px:** [public-catalog-1440px.png](screenshots/public-catalog-1440px.png) (4-column cards, 292px cols, 0 overflow)
- **Order Bag (1440px):** [public-catalog-with-order-bag-1440px.png](screenshots/public-catalog-with-order-bag-1440px.png) (Multi-line bag at bottom of catalog)
- **Customize (1440px):** [public-customize-1440px.png](screenshots/public-customize-1440px.png) (2-column editor: layers, included items, paid extras, sticky photo card)
- **Customize (390px):** [public-customize-390px.png](screenshots/public-customize-390px.png) (Responsive single-column form on mobile)
- **Details (1440px):** [public-details-1440px.png](screenshots/public-details-1440px.png) (Contact details & pickup schedule entry)
- **Details (390px):** [public-details-390px.png](screenshots/public-details-390px.png) (Mobile contact details)
- **Validation Errors (390px):** [public-details-validation-errors-390px.png](screenshots/public-details-validation-errors-390px.png) (Focused field validation tooltip)
- **Payment Pending (1440px):** [public-payment-pending-1440px.png](screenshots/public-payment-pending-1440px.png) (Tokenized private payment view)
- **Payment Pending (390px):** [public-payment-pending-390px.png](screenshots/public-payment-pending-390px.png) (Mobile payment view)
- **Login (1440px):** [public-login-1440px.png](screenshots/public-login-1440px.png) (Centered clean bakery login card)
- **Login (390px):** [public-login-390px.png](screenshots/public-login-390px.png) (Mobile login form)

### Staff Workspace — Owner
- **Dashboard across 6 widths:**
  - 360px: [staff-owner-dashboard-360px.png](screenshots/staff-owner-dashboard-360px.png)
  - 390px: [staff-owner-dashboard-390px.png](screenshots/staff-owner-dashboard-390px.png)
  - 430px: [staff-owner-dashboard-430px.png](screenshots/staff-owner-dashboard-430px.png)
  - 768px: [staff-owner-dashboard-768px.png](screenshots/staff-owner-dashboard-768px.png)
  - 1024px: [staff-owner-dashboard-1024px.png](screenshots/staff-owner-dashboard-1024px.png)
  - 1440px: [staff-owner-dashboard-1440px.png](screenshots/staff-owner-dashboard-1440px.png)
- **Mobile Navigation Drawer (390px):** [staff-owner-mobile-drawer-open-390px.png](screenshots/staff-owner-mobile-drawer-open-390px.png) (Focus trap active, backdrop blur, Escape restores focus)
- **Pickup Schedule:** [staff-owner-schedule-1440px.png](screenshots/staff-owner-schedule-1440px.png), [staff-owner-schedule-390px.png](screenshots/staff-owner-schedule-390px.png)
- **Orders Index across 6 widths:**
  - 360px: [staff-owner-orders-360px.png](screenshots/staff-owner-orders-360px.png) (Table scrollable, page scroll 0)
  - 390px: [staff-owner-orders-390px.png](screenshots/staff-owner-orders-390px.png)
  - 430px: [staff-owner-orders-430px.png](screenshots/staff-owner-orders-430px.png)
  - 768px: [staff-owner-orders-768px.png](screenshots/staff-owner-orders-768px.png)
  - 1024px: [staff-owner-orders-1024px.png](screenshots/staff-owner-orders-1024px.png)
  - 1440px: [staff-owner-orders-1440px.png](screenshots/staff-owner-orders-1440px.png)
- **Order Detail:** [staff-owner-order-show-1440px.png](screenshots/staff-owner-order-show-1440px.png), [staff-owner-order-show-390px.png](screenshots/staff-owner-order-show-390px.png)
- **Customers Index:** [staff-owner-customers-1440px.png](screenshots/staff-owner-customers-1440px.png), [staff-owner-customers-390px.png](screenshots/staff-owner-customers-390px.png)
- **Products Catalog:** [staff-owner-products-1440px.png](screenshots/staff-owner-products-1440px.png), [staff-owner-products-390px.png](screenshots/staff-owner-products-390px.png)
- **Inventory Workspace across 6 widths:**
  - 360px: [staff-owner-inventory-360px.png](screenshots/staff-owner-inventory-360px.png) (Local table scroll, 0 page overflow)
  - 390px: [staff-owner-inventory-390px.png](screenshots/staff-owner-inventory-390px.png)
  - 430px: [staff-owner-inventory-430px.png](screenshots/staff-owner-inventory-430px.png)
  - 768px: [staff-owner-inventory-768px.png](screenshots/staff-owner-inventory-768px.png)
  - 1024px: [staff-owner-inventory-1024px.png](screenshots/staff-owner-inventory-1024px.png)
  - 1440px: [staff-owner-inventory-1440px.png](screenshots/staff-owner-inventory-1440px.png)
- **Inventory Dedicated Forms:**
  - Receive Stock (1440px): [staff-owner-inventory-receipt-1440px.png](screenshots/staff-owner-inventory-receipt-1440px.png)
  - Receive Stock (390px): [staff-owner-inventory-receipt-390px.png](screenshots/staff-owner-inventory-receipt-390px.png)
  - Usage / Waste (1440px): [staff-owner-inventory-usage-1440px.png](screenshots/staff-owner-inventory-usage-1440px.png)
  - Stocktake (1440px): [staff-owner-inventory-stocktake-1440px.png](screenshots/staff-owner-inventory-stocktake-1440px.png)
- **Expenses Workspace across 6 widths:**
  - 360px: [staff-owner-expenses-360px.png](screenshots/staff-owner-expenses-360px.png) (Local scroll, 0 page overflow)
  - 390px: [staff-owner-expenses-390px.png](screenshots/staff-owner-expenses-390px.png)
  - 430px: [staff-owner-expenses-430px.png](screenshots/staff-owner-expenses-430px.png)
  - 768px: [staff-owner-expenses-768px.png](screenshots/staff-owner-expenses-768px.png)
  - 1024px: [staff-owner-expenses-1024px.png](screenshots/staff-owner-expenses-1024px.png)
  - 1440px: [staff-owner-expenses-1440px.png](screenshots/staff-owner-expenses-1440px.png)
- **Expense Forms & Audit:**
  - Add Expense Form (1440px): [staff-owner-expenses-create-1440px.png](screenshots/staff-owner-expenses-create-1440px.png)
  - Audit History (1440px): [staff-owner-expenses-history-1440px.png](screenshots/staff-owner-expenses-history-1440px.png)
- **Reports Workspace across 6 widths:**
  - 360px: [staff-owner-reports-360px.png](screenshots/staff-owner-reports-360px.png)
  - 390px: [staff-owner-reports-390px.png](screenshots/staff-owner-reports-390px.png)
  - 430px: [staff-owner-reports-430px.png](screenshots/staff-owner-reports-430px.png)
  - 768px: [staff-owner-reports-768px.png](screenshots/staff-owner-reports-768px.png)
  - 1024px: [staff-owner-reports-1024px.png](screenshots/staff-owner-reports-1024px.png)
  - 1440px: [staff-owner-reports-1440px.png](screenshots/staff-owner-reports-1440px.png)
- **Reports Drill-down:** [staff-owner-reports-drilldown-1440px.png](screenshots/staff-owner-reports-drilldown-1440px.png) (Itemized orders supporting ₱3,400 completed sales)
- **Users Management (Owner):** [staff-owner-users-1440px.png](screenshots/staff-owner-users-1440px.png)

### Staff Workspace — Assistant
- **Assistant Dashboard (1440px):** [staff-assistant-dashboard-1440px.png](screenshots/staff-assistant-dashboard-1440px.png) (No Users/Admin link in sidebar)
- **Assistant Accessing `/users` (403):** [staff-assistant-users-403-forbidden.png](screenshots/staff-assistant-users-403-forbidden.png) (Standard 403 Forbidden page)
