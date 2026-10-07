<?php

namespace Tests\Feature;

use App\Models\{Customer, Expense, Order, Payment, User};
use App\Services\{FinancialReportService, ReportPeriod};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportPeriodsAndReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    public function test_periods_resolve_month_range_year_boundary_and_leap_day(): void
    {
        foreach ([
            [['mode'=>'month','month'=>'2026-09'],'2026-09-01','2026-09-30'],
            [['mode'=>'month_range','start_month'=>'2026-09','end_month'=>'2026-11'],'2026-09-01','2026-11-30'],
            [['mode'=>'month_range','start_month'=>'2025-12','end_month'=>'2026-01'],'2025-12-01','2026-01-31'],
            [['mode'=>'month','month'=>'2024-02'],'2024-02-01','2024-02-29'],
            [['mode'=>'month','month'=>'2025-02'],'2025-02-01','2025-02-28'],
            [['mode'=>'preset','preset'=>'last_month','as_of'=>'2026-01-15'],'2025-12-01','2025-12-31'],
            [['mode'=>'preset','preset'=>'this_year','as_of'=>'2026-10-02'],'2026-01-01','2026-10-02'],
        ] as [$input,$start,$end]) {
            $period=ReportPeriod::resolve($input); $this->assertSame($start,$period->start->toDateString()); $this->assertSame($end,$period->end->toDateString());
            $this->assertSame($period->label(),ReportPeriod::resolve($period->query())->label());
        }
    }

    public function test_invalid_or_incomplete_periods_produce_validation_errors(): void
    {
        foreach ([['mode'=>'month','month'=>'2026-13'],['mode'=>'month_range','start_month'=>'2026-10'],['mode'=>'month_range','start_month'=>'2026-10','end_month'=>'2026-09'],['mode'=>'custom','start_date'=>'2026-10-02','end_date'=>'2026-10-01'],['start_date'=>'2026-01-01']] as $input) {
            try { ReportPeriod::resolve($input); $this->fail('Invalid range accepted'); }
            catch(ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        }
    }

    public function test_business_timezone_inclusive_end_and_fractional_datetime_boundaries(): void
    {
        config(['bakery.business_timezone'=>'Asia/Manila','app.timezone'=>'UTC']);
        $actor=User::factory()->create(['role'=>'owner']); $customer=Customer::create(['first_name'=>'Test','last_name'=>'Buyer','phone_number'=>'09171234567']);
        $order=Order::create(['order_number'=>'BOUNDARY','customer_id'=>$customer->id,'user_id'=>$actor->id,'status'=>'pending','pickup_date'=>'2026-10-02','pickup_time'=>'12:00']);
        foreach (['2026-08-31 15:59:59'=>1,'2026-08-31 16:00:00'=>2,'2026-09-30 15:59:59.999'=>4,'2026-09-30 16:00:00'=>8] as $date=>$amount) DB::table('payments')->insert(['order_id'=>$order->id,'user_id'=>$actor->id,'amount'=>$amount,'payment_type'=>'down_payment','payment_method'=>'cash','payment_date'=>$date]);
        $report=app(FinancialReportService::class)->report(ReportPeriod::resolve(['mode'=>'month','month'=>'2026-09']));
        $this->assertSame(6.0,$report['summary']['gross_collections']);
        $this->assertSame(2.0,$report['trends'][0]['gross_collections']); $this->assertSame(4.0,$report['trends'][29]['gross_collections']);
        $this->assertCount(30,$report['trends']);
    }

    public function test_summaries_chart_breakdowns_drilldowns_exports_and_saved_package_prices_reconcile(): void
    {
        config(['bakery.business_timezone'=>'Asia/Manila']);
        $actor=User::factory()->create(['role'=>'owner']); $this->actingAs($actor);
        $buyer=Customer::create(['first_name'=>'Report','last_name'=>'Buyer','phone_number'=>'09171234567']);
        $product=$this->catalogProduct(['product_name'=>'Current renamed package','price'=>99000,'is_active'=>true]);
        $makeOrder=function($number,$status,$kind=null) use($actor,$buyer) { return Order::create(['order_number'=>$number,'customer_id'=>$buyer->id,'user_id'=>$actor->id,'status'=>$status,'pickup_date'=>'2026-09-15','pickup_time'=>'12:00','completed_at'=>$status==='completed'?'2026-09-15 10:00:00':null,'cancelled_at'=>$status==='cancelled'?'2026-09-15 10:00:00':null,'cancellation_kind'=>$kind]); };
        $completed=$makeOrder('DONE','completed');
        $line=$completed->orderDetails()->create(['product_id'=>$product->id,'product_name_snapshot'=>'Saved cake name','quantity'=>2,'unit_price'=>1000,'layers'=>2]);
        $extra=\App\Models\AddOn::create(['name'=>'New extra name','description'=>'Paid extra','price'=>999,'is_active'=>true]);
        $line->addOns()->create(['name_snapshot'=>'Saved extras','description_snapshot'=>'Extra description','quantity'=>3,'unit_price'=>100,'add_on_id'=>$extra->id]);
        $customerCancel=$makeOrder('CUSTOMER','cancelled','customer'); $bakeryCancel=$makeOrder('BAKERY','cancelled','bakery_failure');
        foreach ([[$completed,2300,'cash'],[$customerCancel,500,'gcash'],[$bakeryCancel,700,'gcash']] as [$order,$amount,$method]) Payment::create(['order_id'=>$order->id,'user_id'=>$actor->id,'amount'=>$amount,'payment_type'=>'down_payment','payment_method'=>$method,'payment_date'=>'2026-09-10 08:00:00']);
        Expense::create(['user_id'=>$actor->id,'description'=>'Flour','category'=>'ingredients','amount'=>300,'expense_date'=>'2026-09-30']);
        $void=Expense::create(['user_id'=>$actor->id,'description'=>'Voided','category'=>'equipment','amount'=>900,'expense_date'=>'2026-09-30']); $void->delete();
        $period=ReportPeriod::resolve(['mode'=>'month','month'=>'2026-09']); $service=app(FinancialReportService::class); $report=$service->report($period);
        $expected=['sales'=>2300,'gross_collections'=>3500,'payment_collections'=>3500,'cancellation_income'=>500,'expenses'=>300,'operational_net_income'=>2500];
        foreach($expected as $metric=>$amount) { $this->assertEquals($amount,$report['summary'][$metric]); $this->assertEquals($amount,array_sum(array_column($report['trends'],$metric))); }
        $this->assertSame(1,$report['summary']['completed_order_count']);
        $this->assertEquals(2300,$report['packages']->sum('total_amount')); $this->assertEquals(300,$report['packages']->sum('extras_amount'));
        $this->assertSame('Saved cake name',$report['packages']->sole()->product_name_snapshot);
        $this->assertEquals(300,$report['expensesByCategory']->sum('total_amount')); $this->assertEquals(3500,$report['methods']->sum('net'));
        foreach(FinancialReportService::LABELS as $metric=>$label) {
            $response=$this->get(route('reports.records',$period->query()+['metric'=>$metric]));
            $response->assertOk()->assertViewHas('total',$expected[$metric]);
            $this->assertEquals($expected[$metric],$response->viewData('records')->sum('amount'));
        }
        $this->get(route('reports.index',$period->query()))->assertOk()->assertSee('Sep 1, 2026')->assertSee('Saved cake name');
        $csv=$this->get(route('reports.export',$period->query()))->assertOk()->streamedContent();
        $rows=array_map('str_getcsv',explode("\n",trim($csv))); $header=array_shift($rows);
        $rows=array_map(fn($row)=>array_combine($header,$row),$rows);
        $summary=collect($rows)->firstWhere('section','summary');
        foreach($expected as $metric=>$amount) $this->assertEquals($amount,$summary[$metric]);
        $this->assertEquals(2300,collect($rows)->where('section','trend')->sum('sales'));
        $this->assertEquals(3500,collect($rows)->where('section','payment_method')->sum('payment_collections'));
    }

    public function test_empty_reports_zero_fill_and_use_monthly_trends_for_long_periods(): void
    {
        $period=ReportPeriod::resolve(['mode'=>'month_range','start_month'=>'2026-09','end_month'=>'2026-11']);
        $report=app(FinancialReportService::class)->report($period);
        $this->assertSame('Monthly',$report['grain']); $this->assertCount(3,$report['trends']);
        $this->assertEquals(0,$report['summary']['sales']); $this->assertEquals(0,collect($report['methods'])->sum('net'));
    }
}
