<?php
$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema = json_decode(file_get_contents('C:/Users/User/Desktop/Company informations/milestone4-work/erd-revised/active-schema-notes.json'), true, 512, JSON_THROW_ON_ERROR);
$errors = [];
$fieldCount = 0;
$foreignKeyCount = 0;
foreach ($schema['business_tables'] as $table) {
    $name = $table['name'];
    $columns = Illuminate\Support\Facades\Schema::getColumns($name);
    $columnMap = array_column($columns, null, 'name');
    $expected = array_column($table['columns'], 'name');
    $actual = array_keys($columnMap);
    sort($expected); sort($actual);
    if ($actual !== $expected) $errors[] = $name.': column names differ';
    $indexes = Illuminate\Support\Facades\Schema::getIndexes($name);
    $primary = collect($indexes)->firstWhere('primary', true)['columns'] ?? [];
    if ($primary !== $table['primary_key']) $errors[] = $name.': primary key differs';
    $foreignKeys = Illuminate\Support\Facades\Schema::getForeignKeys($name);
    foreach ($table['columns'] as $column) {
        $fieldCount++;
        if (!isset($columnMap[$column['name']])) continue;
        if ($columnMap[$column['name']]['nullable'] !== $column['nullable']) $errors[] = $name.'.'.$column['name'].': nullability differs';
        if (!isset($column['references'])) continue;
        $foreignKeyCount++;
        $actualFk = collect($foreignKeys)->first(fn ($fk) => $fk['columns'] === [$column['name']]);
        if (!$actualFk || $actualFk['foreign_table'] !== $column['references']['table']
            || $actualFk['foreign_columns'] !== [$column['references']['column']]) $errors[] = $name.'.'.$column['name'].': foreign key differs';
        $unique = collect($indexes)->contains(fn ($index) => $index['unique'] && $index['columns'] === [$column['name']]);
        if ($unique !== $column['unique']) $errors[] = $name.'.'.$column['name'].': unique FK cardinality differs';
    }
    if (count($foreignKeys) !== count(array_filter($table['columns'], fn ($column) => isset($column['references'])))) $errors[] = $name.': FK count differs';
}
if (Illuminate\Support\Facades\Schema::hasTable('refunds')) $errors[] = 'Refunds table still exists';
$result = ['business_tables' => count($schema['business_tables']), 'schema_fields' => $fieldCount,
    'foreign_keys' => $foreignKeyCount, 'refunds_table_absent' => !Illuminate\Support\Facades\Schema::hasTable('refunds'),
    'diagram_matches_local_database' => !$errors, 'errors' => $errors];
file_put_contents(__DIR__.'/active-schema-verification.json', json_encode($result, JSON_PRETTY_PRINT).PHP_EOL);
echo json_encode($result, JSON_PRETTY_PRINT), PHP_EOL;
exit($errors ? 1 : 0);
