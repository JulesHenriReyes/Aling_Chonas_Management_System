$ErrorActionPreference = 'Stop'
$dataDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ('bakery-implementation-mysql-' + [Guid]::NewGuid().ToString('N'))
$resolvedParent = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
if (-not ([System.IO.Path]::GetFullPath($dataDirectory).StartsWith($resolvedParent, [StringComparison]::OrdinalIgnoreCase))) { throw 'Unsafe fixture directory' }
if (Get-NetTCPConnection -LocalPort 33317 -State Listen -ErrorAction SilentlyContinue) { throw 'Fixture port is already in use' }
New-Item -ItemType Directory -Path $dataDirectory | Out-Null
& 'C:\xampp\mysql\bin\mysql_install_db.exe' "--datadir=$dataDirectory" '--port=33317' '--silent'
if ($LASTEXITCODE -ne 0) { throw 'Disposable server initialization failed' }
$manifest = @{datadir = $dataDirectory; port = 33317; database = 'bakery_implementation_qa'; environment = 'Disposable local MariaDB, separate from regular port 3306'}
$manifest | ConvertTo-Json | Set-Content 'docs\qa\2026-10-05\implementation-verification\evidence\mariadb-fixture.json'
$dataDirectory
