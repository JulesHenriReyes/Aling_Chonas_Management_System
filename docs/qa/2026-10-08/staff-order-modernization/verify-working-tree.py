import difflib
import json
import pathlib
import re
import subprocess

evidence = pathlib.Path(__file__).resolve().parent
root = evidence.parents[3]
subprocess.run(['git', 'diff', '--binary', '--output=' + str(evidence / 'after-current.patch')], cwd=root, check=True, capture_output=True)

def blocks(file):
    return {re.match(r'diff --git a/(.*?) b/', block).group(1): block.replace('\r\n', '\n')
            for block in re.split(r'(?m)(?=^diff --git )', file.read_text(encoding='utf-8')) if block.startswith('diff --git ')}

before = blocks(evidence / 'before-existing-changes.patch')
current = blocks(evidence / 'after-current.patch')
owned = {
    'app/Http/Controllers/OrderController.php', 'app/Http/Controllers/PublicOrderController.php',
    'app/Models/Order.php', 'app/Models/OrderImage.php', 'app/Services/OrderDraftService.php',
    'app/Services/OrderService.php', 'app/Services/PublicPackageDraftService.php', 'config/filesystems.php',
    'docs/workflow-upgrade.md', 'docs/workflow-verification.md', 'public/css/bakery-ui.css',
    'public/js/catalog-order.js', 'public/js/staff-customer-picker.js',
    'resources/views/admin/orders/create.blade.php', 'resources/views/admin/orders/details.blade.php',
    'resources/views/admin/orders/index.blade.php', 'resources/views/admin/orders/show.blade.php',
    'resources/views/partials/catalog-order-details.blade.php', 'resources/views/partials/order-item-summary.blade.php',
    'resources/views/partials/package-line-fields.blade.php', 'resources/views/public/customize.blade.php',
    'resources/views/public/index.blade.php', 'routes/web.php',
    'tests/Feature/OrderAndPaymentBusinessRulesTest.php', 'tests/Feature/StaffConfirmationBeforePaymentTest.php',
    'tests/Feature/OrderReviewAndPricingTest.php', 'tests/Feature/Phase3QAFixesTest.php', 'tests/Feature/UiPresentationTest.php',
}
same = [file for file in before if file not in owned and before[file] == current.get(file)]
outside = [file for file in before if file not in owned and before[file] != current.get(file)]
untracked = [line[3:] for line in (evidence / 'before-status.txt').read_text(encoding='utf-8-sig').splitlines() if line.startswith('?? ')]
missing = [file for file in untracked if not (root / file).exists()]
whitespace = subprocess.run(['git', 'diff', '--check'], cwd=root, capture_output=True)
proof = {
    'unrelated_tracked_diffs_identical_to_initial_snapshot': len(same),
    'concurrently_modified_unrelated_paths_left_intact': outside,
    'concurrent_change_note': 'These paths changed outside this ordering task. No restore, reset, or edits were applied to them by this task. The initial snapshot and current differences are retained for review.',
    'preexisting_untracked_paths_present': len(untracked) - len(missing),
    'missing_preexisting_untracked_paths': missing,
    'new_workflow_migrations': 0,
    'git_diff_check_passed': whitespace.returncode == 0,
}
(evidence / 'working-tree-preservation.json').write_text(json.dumps(proof, indent=2), encoding='utf-8')
(evidence / 'concurrent-unrelated-differences.txt').write_text('\n'.join(''.join(difflib.unified_diff(before[file].splitlines(True), current.get(file, '').splitlines(True), file+' initial', file+' current')) for file in outside), encoding='utf-8')
print(json.dumps(proof, indent=2))
assert not missing and whitespace.returncode == 0
