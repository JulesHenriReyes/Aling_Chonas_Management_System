<?php

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path !== '/' && is_file(dirname(__DIR__, 4).'/public'.$path)) return false;
$fixture = json_decode(file_get_contents(__DIR__.'/preview-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
require __DIR__.'/../document-qa-fixes/preview-bootstrap.php';
documentQaApplication($fixture)->handleRequest(Illuminate\Http\Request::capture());
