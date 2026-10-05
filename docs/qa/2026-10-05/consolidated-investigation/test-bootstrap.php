<?php
// Isolated QA Test Bootstrap for Section D Reliability
require dirname(__DIR__, 4).'/vendor/autoload.php';

class IsolatedQaTestCase extends Illuminate\Foundation\Testing\TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (!$app->environment('testing')) {
            throw new RuntimeException('QA refuses non-testing environment.');
        }

        // Remove regular connections so a misspelled temporary name cannot fall back.
        $sqlite = $app['config']->get('database.connections.sqlite');
        $app['config']->set('database.connections', ['sqlite' => $sqlite]);

        $app['events']->listen(Illuminate\Database\Events\ConnectionEstablished::class, function ($event) {
            $conn = $event->connection;
            $db = $conn->getDatabaseName();
            $allowedFile = is_string($db) && str_contains(basename($db), 'workflow-concurrency-')
                && str_starts_with(strtolower(str_replace('\\', '/', realpath($db) ?: '')), strtolower(str_replace('\\', '/', realpath(sys_get_temp_dir()))).'/');

            if ($conn->getDriverName() !== 'sqlite' || ($db !== ':memory:' && !$allowedFile)
                || $conn->getConfig('name') !== $conn->getName()) {
                throw new RuntimeException('Unsafe QA database connection.');
            }

            $actual = $conn->getPdo()->query('pragma database_list')->fetchAll(PDO::FETCH_ASSOC);
            if ($db === ':memory:' && ($actual[0]['file'] ?? null) !== '') {
                throw new RuntimeException('Memory database did not resolve to memory.');
            }
            if ($allowedFile && realpath($actual[0]['file']) !== realpath($db)) {
                throw new RuntimeException('SQLite resolved to another file.');
            }

            $logPath = dirname(__DIR__).'/consolidated-investigation/evidence/test-isolation.jsonl';
            if (!is_dir(dirname($logPath))) {
                mkdir(dirname($logPath), 0777, true);
            }
            file_put_contents($logPath, json_encode([
                'timestamp' => date('c'),
                'name' => $conn->getName(),
                'driver' => $conn->getDriverName(),
                'database' => $db,
                'actual' => $actual
            ]).PHP_EOL, FILE_APPEND);
        });

        $app['db']->connection(); // Force actual isolation verification before migrations/writes in setUp traits.
        return $app;
    }
}

class_alias(IsolatedQaTestCase::class, 'Tests\\TestCase');
