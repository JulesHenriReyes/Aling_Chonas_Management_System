<?php

$fixture = json_decode(file_get_contents(__DIR__.'/evidence/mariadb-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$expected = strtolower(rtrim(str_replace('\\', '/', realpath($fixture['datadir'])), '/'));
$temporary = strtolower(str_replace('\\', '/', realpath(sys_get_temp_dir()))).'/';
if (!str_starts_with($expected, $temporary) || !str_starts_with(basename($expected), 'bakery-implementation-mysql-') || $fixture['port'] !== 33317 || $fixture['database'] !== 'bakery_implementation_qa') throw new RuntimeException('Unsafe fixture configuration');
$pdo = new PDO('mysql:host=127.0.0.1;port=33317;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$actual = $pdo->query('SELECT @@port AS port, @@datadir AS datadir, VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || (int) $actual['port'] !== 33317 || strtolower(rtrim(str_replace('\\', '/', realpath($actual['datadir'])), '/')) !== $expected) throw new RuntimeException('Unsafe actual PDO server');
file_put_contents(__DIR__.'/evidence/mariadb-initial-server-proof.json', json_encode($actual, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$pdo->exec('CREATE DATABASE bakery_implementation_qa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "Disposable MariaDB schema created after actual PDO inspection.\n";
