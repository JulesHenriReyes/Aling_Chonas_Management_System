from pathlib import Path
import hashlib
import json
import subprocess
import sys

root = Path(__file__).resolve().parents[4]
paths = subprocess.check_output(['git', 'ls-files', '-z'], cwd=root).decode().split('\0')
paths += ['.env', 'composer.lock']
if len(sys.argv) > 1:
    paths += subprocess.check_output(['git', 'ls-files', '--others', '--exclude-standard', '-z', '--', 'app', 'database', 'tests', 'resources/views'], cwd=root).decode().split('\0')
snapshot = {p: hashlib.sha256((root / p).read_bytes()).hexdigest()
            for p in sorted(set(paths)) if p and (root / p).is_file()}
destination = Path(__file__).parent / 'verification' / 'evidence' / (sys.argv[1] if len(sys.argv) > 1 else 'source-before.json')
destination.parent.mkdir(parents=True, exist_ok=True)
if destination.exists():
    raise RuntimeError('Existing baseline is immutable')
destination.write_text(json.dumps(snapshot, indent=2), encoding='utf-8')
print(f'Fingerprinted {len(snapshot)} files; no file contents exported.')
