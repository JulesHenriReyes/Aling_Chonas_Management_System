$ErrorActionPreference = 'Stop'
$retentionTempRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
$retentionDataDirectory = Join-Path $retentionTempRoot ('bakery-implementation-mysql-' + [Guid]::NewGuid().ToString('N'))
if (-not ([System.IO.Path]::GetFullPath($retentionDataDirectory).StartsWith($retentionTempRoot, [StringComparison]::OrdinalIgnoreCase))) { throw 'Unsafe fixture directory' }
if (Get-NetTCPConnection -LocalPort 33317 -State Listen -ErrorAction SilentlyContinue) { throw 'Fixture port already in use' }
New-Item -ItemType Directory -Path $retentionDataDirectory | Out-Null
& 'C:/xampp/mysql/bin/mysql_install_db.exe' "--datadir=$retentionDataDirectory" '--port=33317' '--silent'
if ($LASTEXITCODE -ne 0) { throw 'Disposable server initialization failed' }
@{datadir=$retentionDataDirectory;port=33317;database='bakery_implementation_qa'} | ConvertTo-Json | Set-Content -LiteralPath 'docs/qa/2026-10-11/order-reference-retention/mariadb-fixture.json'
Write-Output 'Disposable MariaDB prepared on port 33317.'
