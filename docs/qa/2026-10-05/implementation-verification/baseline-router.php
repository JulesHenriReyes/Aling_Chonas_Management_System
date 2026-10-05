<?php

// Read-only visual comparison. Original presentation files, current isolated read controllers.
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit; }
$baseline = json_decode(file_get_contents(__DIR__.'/evidence/visual-baseline.json'), true, 512, JSON_THROW_ON_ERROR);
$directory = $baseline['directory'];
if (in_array($uri, ['/css/bakery-ui.css', '/js/bakery-ui.js'], true)) {
    header('Content-Type: '.(str_ends_with($uri, '.css') ? 'text/css' : 'text/javascript'));
    readfile($directory.'/public'.$uri); return true;
}
if ($uri !== '/' && is_file(dirname(__DIR__, 4).'/public'.$uri) && !str_starts_with($uri, '/storage/')) return false;
require __DIR__.'/preview-bootstrap.php';
$fixture = json_decode(file_get_contents(__DIR__.'/evidence/preview-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$app = previewApplication($fixture['database'], $fixture['storage']);
$viewPath = $directory.'/resources/views';
$compiled = $directory.'/compiled';
if (!is_dir($compiled)) mkdir($compiled);
config(['view.paths' => [$viewPath], 'view.compiled' => $compiled, 'session.cookie' => 'bakery_implementation_preview', 'app.url' => 'http://127.0.0.1:8125']);
$app['view']->getFinder()->setPaths([$viewPath]);
$app->handleRequest(Illuminate\Http\Request::capture());
