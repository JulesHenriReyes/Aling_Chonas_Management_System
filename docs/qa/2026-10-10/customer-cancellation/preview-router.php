<?php
$fixture = json_decode(file_get_contents(__DIR__.'/preview-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = realpath(dirname(__DIR__, 4).'/public');
$file = realpath($root.$path);
if ($path !== '/' && $file && str_starts_with(strtolower($file), strtolower($root).DIRECTORY_SEPARATOR)
    && is_file($file) && !str_contains(strtolower($file), DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR)) return false;
require dirname(__DIR__, 2).'/2026-10-05/staff-confirmation-before-payment/preview-bootstrap.php';
$app = previewApplication($fixture['database'], $fixture['storage']);
config(['app.url' => 'http://127.0.0.1:8146', 'session.cookie' => 'bakery_customer_cancellation_preview']);
$app->handleRequest(Illuminate\Http\Request::capture());
