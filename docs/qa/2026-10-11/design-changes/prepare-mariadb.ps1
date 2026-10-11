$ErrorActionPreference = 'Stop'
$taskTempRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
$taskDataDirectory = Join-Path $taskTempRoot ('bakery-implementation-mysql-' + [Guid]::NewGuid().ToString('N'))
if (-not ([System.IO.Path]::GetFullPath($taskDataDirectory).StartsWith($taskTempRoot, [StringComparison]::OrdinalIgnoreCase))) { throw 'Unsafe fixture directory' }
if (Get-NetTCPConnection -LocalPort 33317 -State Listen -ErrorAction SilentlyContinue) { throw 'Fixture port already in use' }
New-Item -ItemType Directory -Path $taskDataDirectory | Out-Null
& 'C:/xampp/mysql/bin/mysql_install_db.exe' "--datadir=$taskDataDirectory" '--port=33317' '--silent'
if ($LASTEXITCODE -ne 0) { throw 'Disposable server initialization failed' }
@{datadir=$taskDataDirectory;port=33317;database='bakery_implementation_qa'} | ConvertTo-Json | Set-Content -LiteralPath 'docs/qa/2026-10-11/design-changes/mariadb-fixture.json'
Write-Output 'Disposable MariaDB prepared on port 33317.'
