<?php

namespace Tests\Support;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Application;
use RuntimeException;

final class DisposableMariaDb
{
    public static function guard(Application $app, string $manifestPath): void
    {
        $fixture = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $temporary = self::path(sys_get_temp_dir()).'/';
        $dataDirectory = self::path($fixture['datadir']);
        if (! $app->environment('testing') || $app->configurationIsCached()
            || ! str_starts_with(self::path($app->storagePath()), $temporary)
            || ! str_starts_with($dataDirectory, $temporary)
            || ! str_starts_with(basename($dataDirectory), 'bakery-implementation-mysql-')
            || $fixture['database'] !== 'bakery_implementation_qa' || $fixture['port'] !== 33317) {
            throw new RuntimeException('Disposable MariaDB configuration was not proven.');
        }
        $mysql = $app['config']->get('database.connections.mysql');
        $mysql = array_replace($mysql, ['url' => null, 'host' => '127.0.0.1', 'port' => 33317,
            'database' => $fixture['database'], 'username' => 'root', 'password' => '', 'unix_socket' => '']);
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections', ['mysql' => $mysql]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('view.compiled', $app->storagePath('framework/views'));
        $app['events']->listen(ConnectionEstablished::class, function ($event) use ($app, $fixture, $dataDirectory) {
            $connection = $event->connection;
            $pdo = $connection->getPdo();
            $actual = $pdo->query('SELECT DATABASE() AS db, @@port AS port, @@datadir AS datadir, VERSION() AS version')->fetch(\PDO::FETCH_ASSOC);
            if ($connection->getName() !== 'mysql' || $connection->getName() !== $connection->getConfig('name')
                || $connection->getDatabaseName() !== $fixture['database'] || $connection->getDriverName() !== 'mysql'
                || $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql' || $actual['db'] !== $fixture['database']
                || (int) $actual['port'] !== 33317 || self::path($actual['datadir']) !== $dataDirectory) {
                throw new RuntimeException('Actual PDO server, schema, driver or connection name is unsafe.');
            }
            file_put_contents(base_path('docs/qa/2026-10-05/implementation-verification/evidence/mariadb-isolation.jsonl'),
                json_encode(['pid' => getmypid(), 'name' => $connection->getName(), 'driver' => 'mysql', 'actual' => $actual,
                    'storage' => $app->storagePath(), 'config_cached' => false], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
        });
        $app['db']->purge();
        $app['db']->connection()->getPdo();
    }

    private static function path(string $path): string
    {
        return strtolower(rtrim(str_replace('\\', '/', realpath($path) ?: ''), '/'));
    }
}
