<?php
declare(strict_types=1);
use App\Services\{FinancialReportService, ReportExportService, ReportPdfService, ReportPeriod};
use Illuminate\Support\Facades\DB;
require dirname(__DIR__,4).'/vendor/autoload.php';
$app=require dirname(__DIR__,4).'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('C:');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
function fingerprint():array {
    $result=[];
    foreach(['orders','order_details','order_add_ons','payments','payment_proofs','gcash_references','customers','products','package_options','add_ons','expenses','supplies','inventory_transactions','users'] as $table) {
        if(!Illuminate\Support\Facades\Schema::hasTable($table))continue;
        $rows=DB::table($table)->get()->map(fn($row)=>json_encode((array)$row,JSON_THROW_ON_ERROR))->all();
        sort($rows);$result[$table]=['count'=>count($rows),'sha256'=>hash('sha256',implode("\n",$rows))];
    }
    return $result;
}
try {
    $actual=DB::selectOne('SELECT DATABASE() db, @@port port');
    if(!$app->environment('local') || $actual->db!=='aling_chona_db' || (int)$actual->port!==3306)throw new RuntimeException('Wrong read-only report source.');
    $out=__DIR__.'/samples';if(!is_dir($out))mkdir($out,0700,true);
    $before=fingerprint();$exporter=app(ReportExportService::class);$pdf=app(ReportPdfService::class);
    $reports=[];
    foreach(['populated'=>['mode'=>'month','month'=>'2026-10'],'empty'=>['mode'=>'month','month'=>'2024-02'],
        'monthly'=>['mode'=>'month_range','start_month'=>'2026-01','end_month'=>'2026-12']] as $name=>$query) {
        $report=app(FinancialReportService::class)->report(ReportPeriod::resolve($query));
        file_put_contents($out.'/'.$name.'.csv',$exporter->csv($report));
        file_put_contents($out.'/'.$name.'.pdf',$pdf->render($report,$exporter->sections($report)));
        $reports[$name]=['summary'=>$report['summary'],'trends'=>$report['trends'],'sections'=>$exporter->sections($report)];
        if($name==='populated')$stress=$report;
    }
    // Presentation-only stress data; it is never inserted into any database.
    $stress['packages']=collect(array_map(fn($i)=>(object)[
        'product_id'=>$i,'product_name_snapshot'=>'Niño Celebration Package '.$i.' with Chocolate Cupcakes and a Personalized Two Layer Cake for Anniversaries and Birthdays',
        'package_quantity'=>12345,'order_count'=>3210,'package_amount'=>98765432.10,'extras_amount'=>543210.98,'total_amount'=>99308643.08,
    ],range(1,35)));
    $stress['summary']['sales']=$stress['packages']->sum('total_amount');
    $stress['summary']['expenses']=5000000000.99;
    $stress['summary']['operational_net_income']=$stress['summary']['sales']+$stress['summary']['cancellation_income']-$stress['summary']['expenses'];
    file_put_contents($out.'/stress.pdf',$pdf->render($stress,$exporter->sections($stress)));
    $after=fingerprint();
    if($before!==$after)throw new RuntimeException('Report export changed business data.');
    file_put_contents(__DIR__.'/sample-audit.json',json_encode(['reports'=>$reports,'business_data_unchanged'=>true,'row_fingerprints'=>$after],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode(['pdfs_generated'=>4,'business_data_unchanged'=>true,'summary'=>$reports['populated']['summary']],JSON_PRETTY_PRINT)."\n";
} catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");exit(1);}

