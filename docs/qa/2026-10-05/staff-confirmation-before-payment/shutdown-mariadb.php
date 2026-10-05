<?php
// Stop only the server whose actual PDO identity matches this disposable manifest.
$fixture = json_decode(file_get_contents(__DIR__.'/verification/evidence/mariadb-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$expected = strtolower(rtrim(str_replace('\\', '/', realpath($fixture['datadir'])), '/'));
$temporary = strtolower(str_replace('\\', '/', realpath(sys_get_temp_dir()))).'/';
if (!str_starts_with($expected, $temporary) || !str_starts_with(basename($expected), 'bakery-implementation-mysql-')
    || $fixture['port'] !== 33317 || $fixture['database'] !== 'bakery_implementation_qa') throw new RuntimeException('Unsafe fixture identity.');
$pdo = new PDO('mysql:host=127.0.0.1;port=33317;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$actual = $pdo->query('SELECT @@port AS port, @@datadir AS datadir')->fetch(PDO::FETCH_ASSOC);
if ((int) $actual['port'] !== 33317 || strtolower(rtrim(str_replace('\\', '/', realpath($actual['datadir'])), '/')) !== $expected) throw new RuntimeException('Actual fixture server mismatch.');
$pipes = [];
$process = proc_open(['C:/xampp/mysql/bin/mysqladmin.exe', '--host=127.0.0.1', '--port=33317', '--user=root', 'shutdown'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); foreach ($pipes as $pipe) fclose($pipe);
$exit = proc_close($process); if ($exit !== 0) throw new RuntimeException('Fixture shutdown failed: '.$error);
file_put_contents(__DIR__.'/verification/evidence/mariadb-shutdown.json', json_encode(['port' => 33317, 'actual_datadir_verified' => true, 'exit_code' => $exit], JSON_PRETTY_PRINT));
echo "Disposable MariaDB on 33317 stopped; identity was verified before shutdown.\n";
