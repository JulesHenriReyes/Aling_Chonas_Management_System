# Executed B/C evidence index

These IDs identify the exact recorded test methods; they do not add tests or expand their scope. R = selected existing regression, Q = separate targeted QA. See [report](report.md) for commands/isolation/limits.

## R — recorded methods

| ID | Exact method | Actual result | Test source |
|---|---|---|---|
| R01 | `test_50_percent_down_payment_accepted_and_confirms_order` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/OrderAndPaymentBusinessRulesTest.php>) |
| R02 | `test_incorrect_down_payment_rejected` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/OrderAndPaymentBusinessRulesTest.php>) |
| R03 | `test_confirmation_without_deposit_rejected` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/OrderAndPaymentBusinessRulesTest.php>) |
| R04 | `test_final_payment_accepted_when_exact_balance_is_paid` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/OrderAndPaymentBusinessRulesTest.php>) |
| R05 | `test_payment_exceeding_balance_rejected` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/OrderAndPaymentBusinessRulesTest.php>) |
| R06 | `test_confirmed_order_retains_option_price_after_catalog_price_changes` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/OrderReviewAndPricingTest.php>) |
| R07 | `test_invalid_status_transitions_and_terminal_state_changes_are_rejected` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/OrderReviewAndPricingTest.php>) |
| R08 | `test_guest_can_view_active_products_but_not_inactive_products` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/PublicOrderingTest.php>) |
| R09 | `test_guest_can_submit_multiple_customized_products_as_a_pending_public_order` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/PublicOrderingTest.php>) |
| R10 | `test_customer_matching_uses_normalized_phone_and_exact_names` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/PublicOrderingTest.php>) |
| R11 | `test_public_submission_creates_a_new_normalized_customer_when_no_exact_match_exists` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/PublicOrderingTest.php>) |
| R12 | `test_public_image_upload_is_saved_against_its_matching_order_detail` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/PublicOrderingTest.php>) |
| R13 | `test_both_paths_use_catalog_prices_and_keep_inclusions_separate_from_extras` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/FixedCatalogAndPaymentReviewTest.php>) |
| R14 | `test_snapshots_survive_catalog_changes_and_manual_price_changes_are_rejected` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/FixedCatalogAndPaymentReviewTest.php>) |
| R15 | `test_invalid_option_unavailable_product_inapplicable_extra_and_fractional_quantity_are_rejected` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/FixedCatalogAndPaymentReviewTest.php>) |
| R16 | `test_paid_extra_selection_is_independent_of_package_visibility_and_included_items` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/PackageInclusionsTest.php>) |
| R17 | `test_public_and_staff_orders_use_included_quantities_per_package_and_paid_extras_once_per_line` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/PackageInclusionsTest.php>) |
| R18 | `test_catalog_changes_do_not_rewrite_saved_inclusions_or_prices` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/PackageInclusionsTest.php>) |
| R19 | `test_customer_phone_normalization_handles_various_philippine_formats` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/Phase3QAFixesTest.php>) |
| R20 | `test_internal_staff_order_creation_handles_per_item_image_uploads` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/Phase3QAFixesTest.php>) |
| R21 | `test_payment_controller_requires_reference_number_for_gcash_payments` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/Phase3QAFixesTest.php>) |
| R22 | `test_staff_uses_two_steps_and_can_create_and_select_customer_inline` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/TwoStepOrderingAndReceiptScreenTest.php>) |
| R23 | `test_periods_resolve_month_range_year_boundary_and_leap_day` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/ReportPeriodsAndReconciliationTest.php>) |
| R24 | `test_invalid_or_incomplete_periods_produce_validation_errors` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/ReportPeriodsAndReconciliationTest.php>) |
| R25 | `test_business_timezone_inclusive_end_and_fractional_datetime_boundaries` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/ReportPeriodsAndReconciliationTest.php>) |
| R26 | `test_summaries_chart_breakdowns_drilldowns_exports_and_saved_package_prices_reconcile` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/ReportPeriodsAndReconciliationTest.php>) |
| R27 | `test_empty_reports_zero_fill_and_use_monthly_trends_for_long_periods` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/ReportPeriodsAndReconciliationTest.php>) |
| R28 | `test_low_stock_supplies_query` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/tests/Feature/FinancialReportingTest.php>) |

Actual run: [output](evidence/business-regression.txt), [JUnit](evidence/business-regression-junit.xml).

## Q — recorded methods

| ID | Exact method | Actual result | Test source |
|---|---|---|---|
| Q01 | `test_ordinary_receiving_usage_count_and_linked_correction_have_no_implicit_expenses` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q02 | `test_expense_creator_editor_void_and_active_totals` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q03 | `test_expense_filtered_total_covers_matching_records` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q04 | `test_fully_paid_customer_cancellation_recognizes_all_verified_retained_money` | Verified fail | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q05 | `test_required_customer_contact_cannot_normalize_to_empty` | Verified fail | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q06 | `test_public_contact_cannot_normalize_to_empty` | Verified fail | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q07 | `test_refund_only_period_has_negative_net_collections_and_no_retention` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q08 | `test_pickup_schedule_date_and_terminal_status_scope` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q09 | `test_single_row_stock_rules_and_baseline_are_explicit` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q10 | `test_receipt_review_is_separate_from_verified_money` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q11 | `test_bakery_failure_full_refund_waits_for_transfer_confirmation` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q12 | `test_ordinary_package_line_add_edit_remove_and_checkout` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q13 | `test_owner_normal_catalog_settings_users_and_customer_edits` | Verified pass | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q14 | `test_dashboard_today_pickups_uses_the_bakery_calendar` | Verified fail | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |
| Q15 | `test_new_public_order_rejects_a_past_bakery_pickup_date` | Verified fail | [source](<C:/Users/User/Desktop/IT12_Project/docs/qa/2026-10-05/business-investigation/tests/BusinessInvestigationTest.php>) |

Actual run: [output](evidence/business-qa.txt), [JUnit](evidence/business-qa-junit.xml).

