from pathlib import Path
import json
import subprocess

directory = Path(__file__).parent
root = directory.parents[3]
before = json.loads((directory/'evidence/regular-before.json').read_text(encoding='utf-8'))
after = json.loads((directory/'evidence/regular-after.json').read_text(encoding='utf-8'))
differences = {name:{'before':rows, 'after':after['tables'][name]} for name,rows in before['tables'].items() if rows != after['tables'][name]}
framework = {'sessions','cache','cache_locks','jobs','job_batches','failed_jobs'}
business_differences = {name:rows for name,rows in differences.items() if name not in framework}
source_before = json.loads((directory/'evidence/source-before.json').read_text(encoding='utf-8'))
source_after = json.loads((directory/'evidence/source-release.json').read_text(encoding='utf-8'))
old_lock = json.loads(subprocess.check_output(['git','show','HEAD:composer.lock'],cwd=root))
new_lock = json.loads((root/'composer.lock').read_text(encoding='utf-8'))
versions = lambda lock: {p['name']:p['version'] for p in lock['packages']+lock['packages-dev']}
old_versions, new_versions = versions(old_lock),versions(new_lock)
dependency_changes = {name:{'before':old_versions.get(name),'after':version} for name,version in new_versions.items() if old_versions.get(name)!=version}
record = {'regular_engine':after['driver'], 'regular_database':after['database'],
    'business_tables_checked':len(before['tables'])-len(framework & before['tables'].keys()),
    'business_differences':business_differences,'framework_differences':differences,
    'env_hash_unchanged':source_before['.env']==source_after['.env'],
    'tracked_source_changes':[name for name,value in source_before.items() if source_after.get(name)!=value],
    'new_source_files':[name for name in source_after if name not in source_before],
    'dependency_version_changes':dependency_changes,'dependency_removals':sorted(old_versions.keys()-new_versions.keys()),
    'historical_cancellation_exceptions':before['legacy_cancellation_exceptions'], 'historical_overpayments':before['legacy_overpayments']}
(directory/'evidence/preservation-reconciliation.json').write_text(json.dumps(record,indent=2))
assert not business_differences, 'Regular business records changed; review before sign-off.'
assert record['env_hash_unchanged'], '.env changed.'
assert dependency_changes=={'giggsey/libphonenumber-for-php-lite':{'before':None,'after':'9.0.40'}}
assert not record['dependency_removals']
print(f'PASS: {record["business_tables_checked"]} regular business tables unchanged; .env hash unchanged; only the pinned phone package added.')
