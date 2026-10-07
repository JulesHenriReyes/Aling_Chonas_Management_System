<?php
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if ($path !== '/' && is_file(dirname(__DIR__,4).'/public'.$path)) return false;
require dirname(__DIR__,2).'/2026-10-05/staff-confirmation-before-payment/preview-bootstrap.php';
$fixture=json_decode(file_get_contents(__DIR__.'/preview-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$app=previewApplication($fixture['database'],$fixture['storage']);
config(['app.url'=>'http://127.0.0.1:8136','session.cookie'=>'bakery_expiry_preview']);
$app->handleRequest(Illuminate\Http\Request::capture());
