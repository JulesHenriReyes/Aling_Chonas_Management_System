$ErrorActionPreference = 'Stop'
$evidenceDirectory = $PSScriptRoot
$temporaryRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
$dataDirectory = Join-Path $temporaryRoot ('bakery-implementation-mysql-' + [Guid]::NewGuid().ToString('N'))
if (-not ([System.IO.Path]::GetFullPath($dataDirectory).StartsWith($temporaryRoot, [StringComparison]::OrdinalIgnoreCase))) { throw 'Unsafe fixture directory' }
if (Get-NetTCPConnection -LocalPort 33317 -State Listen -ErrorAction SilentlyContinue) { throw 'Disposable port is already in use' }
New-Item -ItemType Directory -Path $dataDirectory | Out-Null
& 'C:\xampp\mysql\bin\mysql_install_db.exe' "--datadir=$dataDirectory" '--port=33317' '--silent'
if ($LASTEXITCODE -ne 0) { throw 'Disposable initialization failed' }
$manifest = @{datadir = $dataDirectory; port = 33317; database = 'bakery_implementation_qa'}
$manifest | ConvertTo-Json | Set-Content (Join-Path $evidenceDirectory 'mariadb-fixture.json')
$serverArguments = @('--no-defaults', ('--datadir="' + $dataDirectory + '"'), '--port=33317', '--bind-address=127.0.0.1', '--skip-log-bin', '--console')
$server = Start-Process 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList $serverArguments -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $evidenceDirectory 'mariadb-server.txt') -RedirectStandardError (Join-Path $evidenceDirectory 'mariadb-server-errors.txt')
$server.Id | Set-Content (Join-Path $evidenceDirectory 'mariadb-server-pid.txt')
@{datadir = $dataDirectory; port = 33317; pid = $server.Id} | ConvertTo-Json
