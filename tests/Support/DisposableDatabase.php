<?php

namespace Tests\Support;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Application;
use RuntimeException;

final class DisposableDatabase
{
    public static function tempDir(): string
    {
        $temp = getenv('TEMP') ?: getenv('TMP') ?: sys_get_temp_dir();
        if ((str_starts_with($temp, 'C:\\WINDOWS') || str_starts_with($temp, 'C:/WINDOWS')) && getenv('USERPROFILE')) {
            $userTemp = getenv('USERPROFILE').'/AppData/Local/Temp';
            if (is_dir($userTemp)) {
                $temp = $userTemp;
            }
        }

        return $temp;
    }

    public static function storage(): string
    {
        $path = self::tempDir().'/bakery-implementation-tests-'.getmypid();
        foreach (['', '/framework/views', '/framework/cache', '/framework/sessions', '/logs', '/app/private', '/app/public', '/app/payment-receipts'] as $directory) {
            if (! is_dir($path.$directory)) {
                mkdir($path.$directory, 0777, true);
            }
        }
        foreach (['APP_CONFIG_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE'] as $variable) {
            putenv($variable.'='.$path.'/'.strtolower($variable).'.php');
            $_ENV[$variable] = $_SERVER[$variable] = $path.'/'.strtolower($variable).'.php';
        }

        return $path;
    }

    public static function guard(Application $app, string $database = ':memory:'): void
    {
        if ($manifest = getenv('BAKERY_QA_MYSQL_MANIFEST')) {
            DisposableMariaDb::guard($app, $manifest);

            return;
        }
        if (! $app->environment('testing')) {
            throw new RuntimeException('Disposable tests require APP_ENV=testing.');
        }
        $storage = strtolower(str_replace('\\', '/', realpath($app->storagePath()) ?: ''));
        $temporary = strtolower(str_replace('\\', '/', realpath(self::tempDir()))).'/';
        if (! str_starts_with($storage, $temporary) || $app->configurationIsCached()) {
            throw new RuntimeException('Tests require temporary storage and uncached configuration.');
        }
        $sqlite = $app['config']->get('database.connections.sqlite');
        $sqlite['database'] = $database;
        $sqlite['url'] = null;
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections', ['sqlite' => $sqlite]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('view.compiled', $app->storagePath('framework/views'));
        $app['events']->listen(ConnectionEstablished::class, function ($event) use ($app) {
            $connection = $event->connection;
            $database = $connection->getDatabaseName();
            $resolved = str_replace('\\', '/', realpath((string) $database) ?: '');
            $temporary = str_replace('\\', '/', realpath(self::tempDir())).'/';
            $fileAllowed = str_starts_with(strtolower($resolved), strtolower($temporary))
                && str_contains(basename($resolved), 'workflow-concurrency-');
            if ($connection->getDriverName() !== 'sqlite' || ($database !== ':memory:' && ! $fileAllowed)
                || $connection->getName() !== $connection->getConfig('name')) {
                throw new RuntimeException('Refusing a non-disposable or mismatched database connection.');
            }
            $pdo = $connection->getPdo();
            $actual = $pdo->query('PRAGMA database_list')->fetchAll(\PDO::FETCH_ASSOC);
            if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite' || count($actual) !== 1
                || ($database === ':memory:' ? $actual[0]['file'] !== '' : realpath($actual[0]['file']) !== realpath($database))) {
                throw new RuntimeException('Actual PDO database failed disposable isolation checks.');
            }
            $record = ['pid' => getmypid(), 'name' => $connection->getName(), 'driver' => 'sqlite', 'database' => $database,
                'actual' => $actual, 'storage' => $app->storagePath(), 'config_cached' => $app->configurationIsCached()];
            $evidence = getenv('BAKERY_QA_EVIDENCE_DIR') ?: $app->storagePath('logs');
            file_put_contents($evidence.'/isolation.jsonl',
                json_encode($record, JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
        });
        $app['db']->purge();
        $app['db']->connection()->getPdo(); // Force inspection before RefreshDatabase or worker mutations.
    }
}
