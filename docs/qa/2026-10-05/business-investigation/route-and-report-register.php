<?php
// Only regular-app bootstrap and SELECTs. No migration/schema/load/recovery checks.
$root=dirname(__DIR__,4);
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes=[];
foreach (Illuminate\Support\Facades\Route::getRoutes() as $route) {
    if (!str_starts_with($route->getActionName(),'App\\')) continue;
    $routes[]=['methods'=>$route->methods(),'uri'=>$route->uri(),'name'=>$route->getName(),
        'action'=>$route->getActionName(),'middleware'=>$route->gatherMiddleware()];
}
file_put_contents(__DIR__.'/evidence/routes.json',json_encode($routes,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
$conn=Illuminate\Support\Facades\DB::connection();
$environment=['date'=>'2026-10-05','regular_url'=>config('app.url'),'environment'=>app()->environment(),
    'laravel'=>app()->version(),'php'=>PHP_VERSION,'driver'=>$conn->getDriverName(),'connection_name'=>$conn->getName(),
    'database'=>$conn->getDatabaseName(),'session_driver'=>config('session.driver'),
    'business_timezone'=>config('bakery.business_timezone'),'storage_timezone'=>config('app.timezone'),
    'route_count'=>count($routes),'ui_browser_checks'=>'Deferred A','reliability_checks'=>'Deferred D'];
$evidence=[];
try {
    $environment['actual_database']=$conn->getPdo()->query('select database()')->fetchColumn();
    $environment['database_server']=$conn->getPdo()->query('select version()')->fetchColumn();
    foreach ([['2026-09-01','2026-09-30'],['2026-10-01','2026-10-05']] as [$start,$end]) {
        $tz=new DateTimeZone(config('bakery.business_timezone')); $storage=new DateTimeZone(config('app.timezone'));
        $lo=(new DateTimeImmutable($start.' 00:00:00',$tz))->setTimezone($storage)->format('Y-m-d H:i:s');
        $hi=(new DateTimeImmutable($end.' 00:00:00',$tz))->modify('+1 day')->setTimezone($storage)->format('Y-m-d H:i:s');
        $cents=fn($amount)=>(int)round((float)$amount*100);
        $sum=fn($rows)=>array_sum(array_map(fn($row)=>$cents($row->amount),$rows));
        $sales=0; $completed=$conn->select('select id from orders where status=? and completed_at>=? and completed_at<?',['completed',$lo,$hi]);
        foreach($completed as $order) {
            $lines=$conn->select('select id, quantity, unit_price from order_details where order_id=?',[$order->id]);
            foreach($lines as $line) {
                $sales+=(int)$line->quantity*$cents($line->unit_price);
                foreach($conn->select('select quantity, unit_price from order_add_ons where order_detail_id=?',[$line->id]) as $extra) $sales+=(int)$extra->quantity*$cents($extra->unit_price);
            }
        }
        $gross=$sum($conn->select('select amount from payments where payment_date>=? and payment_date<?',[$lo,$hi]));
        $refunds=$sum($conn->select('select amount from refunds where status=? and completed_at>=? and completed_at<?',['completed',$lo,$hi]));
        $retained=$sum($conn->select("select p.amount from payments p join orders o on o.id=p.order_id where o.status='cancelled' and (o.cancellation_kind='customer' or o.cancellation_kind is null) and o.cancelled_at>=? and o.cancelled_at<?",[$lo,$hi]));
        $nextEnd=(new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');
        $expenses=$sum($conn->select('select amount from expenses where deleted_at is null and expense_date>=? and expense_date<?',[$start,$nextEnd]));
        $expected=['sales'=>$sales/100,'gross_collections'=>$gross/100,'refunds_completed'=>$refunds/100,
            'payment_collections'=>($gross-$refunds)/100,'cancellation_income'=>$retained/100,'expenses'=>$expenses/100,
            'operational_net_income'=>($sales+$retained-$expenses)/100,'completed_order_count'=>count($completed)];
        $actual=app(App\Services\FinancialReportService::class)->report(App\Services\ReportPeriod::dates($start,$end))['summary'];
        $differences=[]; foreach($expected as $k=>$v) if(abs($v-$actual[$k])>0.001) $differences[$k]=['expected'=>$v,'actual'=>$actual[$k]];
        $evidence[]=['business_period'=>[$start,$end],'independent_storage_bounds'=>[$lo,$hi],'expected'=>$expected,
            'actual'=>array_intersect_key($actual,$expected),'differences'=>$differences,'scope'=>'Read-only regular MariaDB service calculation; no browser/controller authorization verification'];
    }
} catch(Throwable $error) { $environment['read_status']='blocked'; $environment['error_code']=$error->getCode(); $environment['error_summary']=strtok($error->getMessage(),'('); }
file_put_contents(__DIR__.'/evidence/environment.json',json_encode($environment,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
file_put_contents(__DIR__.'/evidence/regular-report-reconciliation.json',json_encode($evidence,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode(['environment'=>$environment,'reconciliation'=>$evidence],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
