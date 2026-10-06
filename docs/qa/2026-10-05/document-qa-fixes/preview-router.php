<?php

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$fixture = json_decode(file_get_contents(__DIR__.'/preview-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
if (str_starts_with($path, '/storage/')) {
    $root = realpath($fixture['storage'].'/app/public');
    $file = realpath($root.'/'.substr($path, 9));
    if (! $file || ! str_starts_with(strtolower($file), strtolower($root).DIRECTORY_SEPARATOR) || ! is_file($file)) {
        http_response_code(404);
        return;
    }
    header('Content-Type: '.mime_content_type($file));
    readfile($file);
    return;
}
if ($path !== '/' && is_file(dirname(__DIR__, 4).'/public'.$path)) return false;
require __DIR__.'/preview-bootstrap.php';
documentQaApplication($fixture)->handleRequest(Illuminate\Http\Request::capture());
