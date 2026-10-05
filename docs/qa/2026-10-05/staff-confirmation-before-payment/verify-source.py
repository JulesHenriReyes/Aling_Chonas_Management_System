from pathlib import Path
import json

root = Path(__file__).resolve().parents[4]
evidence = Path(__file__).parent / 'verification' / 'evidence'
before = json.loads((evidence / 'source-before.json').read_text(encoding='utf-8'))
latest = next(p for p in ['source-complete.json', 'source-final.json', 'source-after.json'] if (evidence / p).exists())
after = json.loads((evidence / latest).read_text(encoding='utf-8'))
historical = [p for p in before if p.startswith('docs/qa/')]
unchanged = ['.env', 'composer.lock', 'public/js/bakery-ui.js',
    'resources/views/admin/supplies/index.blade.php', 'resources/views/auth/login.blade.php',
    'resources/views/layouts/admin.blade.php', 'resources/views/public/layout.blade.php']
assert all(before[p] == after[p] for p in historical + unchanged)
contention = json.loads((evidence / 'mariadb-contention.json').read_text())
assert len(contention['cases']) == 12
assert all(case['held_lock_blocked_both_workers'] and len(case['results']) == 2 for case in contention['cases'].values())
result = {'historical_qa_files_unchanged': len(historical), 'preserved_runtime_and_unrelated_ui': unchanged,
    'contention_cases': len(contention['cases']), 'guarded_contention_workers': 24,
    'source_before_files': len(before), 'source_after_files': len(after)}
(evidence / 'source-preservation.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps(result, indent=2))
