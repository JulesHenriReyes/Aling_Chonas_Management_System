<?php
require dirname(__DIR__, 4).'/vendor/autoload.php';
$fixture = json_decode(file_get_contents(__DIR__.'/mariadb-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$normalize = fn ($path) => strtolower(rtrim(str_replace('\\', '/', realpath($path) ?: ''), '/'));
$expected = $normalize($fixture['datadir']);
if ($fixture['port'] !== 33317 || $fixture['database'] !== 'bakery_implementation_qa'
    || ! str_starts_with($expected, $normalize(sys_get_temp_dir()).'/')
    || ! str_starts_with(basename($expected), 'bakery-implementation-mysql-')) throw new RuntimeException('Unsafe fixture.');
$pdo = new PDO('mysql:host=127.0.0.1;port=33317;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$actual = $pdo->query('SELECT @@port AS port, @@datadir AS datadir')->fetch(PDO::FETCH_ASSOC);
if ((int) $actual['port'] !== 33317 || $normalize($actual['datadir']) !== $expected) throw new RuntimeException('Actual server is not the disposable fixture.');
if (in_array('--shutdown', $argv, true)) {
    $process = new Symfony\Component\Process\Process(['C:/xampp/mysql/bin/mysqladmin.exe', '--no-defaults',
        '--protocol=tcp', '--host=127.0.0.1', '--port=33317', '--user=root', 'shutdown']);
    $process->mustRun();
    echo "Verified disposable MariaDB stopped.\n";
} else {
    $pdo->exec('CREATE DATABASE IF NOT EXISTS bakery_implementation_qa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "Actual disposable server verified and test schema prepared.\n";
}
