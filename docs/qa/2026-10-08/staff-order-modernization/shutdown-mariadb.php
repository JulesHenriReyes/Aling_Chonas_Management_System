<?php
require dirname(__DIR__, 4).'/tests/Support/mariadb-bootstrap.php';
disposableMariaDbApplication(); // Checks actual PDO server, schema, port and temporary datadir before shutdown.
$actual = Illuminate\Support\Facades\DB::selectOne('SELECT @@port AS port, @@datadir AS datadir, DATABASE() AS db');
Illuminate\Support\Facades\DB::connection()->getPdo()->exec('SHUTDOWN');
file_put_contents(__DIR__.'/mariadb-shutdown.json', json_encode(['checked_server' => $actual, 'shutdown_sent' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Stopped the checked disposable MariaDB server on port 33317.\n";
