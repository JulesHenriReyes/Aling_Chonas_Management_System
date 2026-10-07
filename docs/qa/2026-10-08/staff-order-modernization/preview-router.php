<?php
$fixture = json_decode(file_get_contents(__DIR__.'/preview-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (str_starts_with($path, '/storage/') && !str_starts_with($path, '/storage/order_drafts/')) {
    $root = realpath($fixture['storage'].'/app/public');
    $file = realpath($root.'/'.substr($path, 9));
    if (!$file || !str_starts_with(strtolower($file), strtolower($root).DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); return; }
    header('Content-Type: '.(str_ends_with($file, '.svg') ? 'image/svg+xml' : mime_content_type($file)));
    readfile($file);
    return;
}
$publicRoot = realpath(dirname(__DIR__, 4).'/public');
$publicFile = realpath($publicRoot.$path);
if ($path !== '/' && $publicFile && str_starts_with(strtolower($publicFile), strtolower($publicRoot).DIRECTORY_SEPARATOR) && is_file($publicFile) && !str_contains(strtolower($publicFile), DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR)) return false;
require dirname(__DIR__, 2).'/2026-10-05/staff-confirmation-before-payment/preview-bootstrap.php';
$app = previewApplication($fixture['database'], $fixture['storage']);
config(['app.url' => 'http://127.0.0.1:8138', 'session.cookie' => 'bakery_staff_workspace_preview']);
$app->handleRequest(Illuminate\Http\Request::capture());
