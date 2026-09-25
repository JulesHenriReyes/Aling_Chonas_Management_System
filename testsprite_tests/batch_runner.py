import subprocess
import sys
import glob
import os

test_files = sorted(glob.glob("testsprite_tests/TC*.py"))
results = {}

print(f"Discovered {len(test_files)} test files.")

for tf in test_files:
    # Cleanup test users before each test run
    subprocess.run(["php", "artisan", "tinker", "--execute=App\\Models\\User::whereNotIn('email', ['owner@alingchona.local', 'assistant@alingchona.local'])->delete();"], capture_output=True)
    
    print(f"Running {tf}...", end=" ", flush=True)
    res = subprocess.run([sys.executable, tf], capture_output=True, text=True)
    if res.returncode == 0:
        print("PASSED")
        results[tf] = "PASSED"
    else:
        print("FAILED")
        print("--- STDERR ---")
        print(res.stderr[:500])
        print("--- STDOUT ---")
        print(res.stdout[:500])
        results[tf] = "FAILED"

print("\n=== SUMMARY ===")
passed_count = sum(1 for v in results.values() if v == "PASSED")
failed_count = sum(1 for v in results.values() if v == "FAILED")
print(f"Passed: {passed_count}/{len(results)}, Failed: {failed_count}/{len(results)}")
for tf, status in results.items():
    if status == "FAILED":
        print(f"FAILED: {tf}")
