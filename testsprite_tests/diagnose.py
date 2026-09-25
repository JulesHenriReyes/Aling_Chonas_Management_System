import subprocess
import sys

failures = [
    "testsprite_tests/TC026_Show_only_active_products_to_public_buyers.py",
    "testsprite_tests/TC027_Create_a_new_customer_record.py",
    "testsprite_tests/TC028_Prevent_incorrect_payment_amounts_on_an_order.py",
    "testsprite_tests/TC029_Record_stock_in_for_a_supply_item.py",
    "testsprite_tests/TC030_Edit_an_existing_customer_record.py",
    "testsprite_tests/TC031_Filter_reports_by_date_range.py",
    "testsprite_tests/TC032_Add_an_operating_expense.py",
    "testsprite_tests/TC033_Add_a_supply_item_to_inventory.py",
    "testsprite_tests/TC034_Block_editing_of_a_locked_order.py",
    "testsprite_tests/TC036_Edit_a_supply_item.py",
    "testsprite_tests/TC037_See_low_stock_warnings_for_supplies.py",
]

for tf in failures:
    print(f"\n==================== {tf} ====================")
    res = subprocess.run([sys.executable, tf], capture_output=True, text=True)
    if res.returncode == 0:
        print("PASSED")
    else:
        print("FAILED")
        for line in res.stderr.splitlines():
            if "Error:" in line or "AssertionError" in line or "TimeoutError" in line:
                print("  ", line)
        print("LAST 3 LINES OF STDERR:")
        print("\n".join(res.stderr.splitlines()[-3:]))
