<?php
namespace Qa\Business;

use App\Models\{Customer, Expense, ExpenseAudit, InventoryOperation, Order, Payment, Product, Refund, Supply, User};
use App\Services\{ExpenseService, FinancialReportService, InventoryService, OrderService, ReportPeriod};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessInvestigationTest extends TestCase
{
    use RefreshDatabase;
    private function actor(string $role='owner'): User { return User::factory()->create(['role'=>$role,'is_active'=>true]); }
    private function buyer(): Customer { return Customer::create(['first_name'=>'Synthetic','last_name'=>'Buyer','phone_number'=>'09170000000']); }
    private function supply(string $name, string $unit='kg'): Supply { return Supply::create(['supply_name'=>$name,'category'=>'ingredients','unit'=>$unit,'current_quantity'=>10,'reorder_level'=>2,'is_active'=>true]); }
    private function observation(string $name,array $data): void { file_put_contents(dirname(__DIR__).'/evidence/'.$name.'.json',json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); }

    public function test_ordinary_receiving_usage_count_and_linked_correction_have_no_implicit_expenses(): void
    {
        foreach(['owner','assistant'] as $role) {
            $actor=$this->actor($role); $one=$this->supply('Flour '.$role); $two=$this->supply('Boxes '.$role,'piece');
            $service=app(InventoryService::class);
            $make=fn($type,$lines)=>['submission_key'=>(string)Str::uuid(),'type'=>$type,'operation_date'=>'2026-10-05','notes'=>'Synthetic functional fixture','lines'=>$lines];
            $receipt=$service->post($make('receipt',[['supply_id'=>$one->id,'quantity'=>2],['supply_id'=>$two->id,'quantity'=>3]]),$actor);
            $this->assertCount(2,$receipt->movements); $this->assertEquals(12,$one->fresh()->current_quantity); $this->assertEquals(13,$two->fresh()->current_quantity);
            $service->post($make('usage',[['supply_id'=>$one->id,'quantity'=>1]]),$actor);
            $service->post($make('waste',[['supply_id'=>$one->id,'quantity'=>1]]),$actor);
            $service->post($make('stocktake',[['supply_id'=>$two->id,'quantity'=>11,'expected_version'=>$two->fresh()->stock_version]]),$actor);
            $this->assertEquals(10,$one->fresh()->current_quantity); $this->assertEquals(11,$two->fresh()->current_quantity);
            $reverse=$service->reverse($receipt,(string)Str::uuid(),'Synthetic correction',$actor);
            $this->assertEquals($receipt->id,$reverse->reversal_of_id); $this->assertEquals(8,$one->fresh()->current_quantity); $this->assertEquals(8,$two->fresh()->current_quantity);
            $this->assertSame('kg',$receipt->movements->first()->unit); $this->assertDatabaseCount('expenses',0);
        }
    }

    public function test_expense_creator_editor_void_and_active_totals(): void
    {
        $assistant=$this->actor('assistant'); $owner=$this->actor(); $service=app(ExpenseService::class);
        $expense=$service->create(['submission_key'=>(string)Str::uuid(),'description'=>'Synthetic invoice','category'=>'ingredients','amount'=>250,'expense_date'=>'2026-09-30'],$assistant);
        $expense=$service->update($expense,['description'=>'Synthetic corrected invoice','category'=>'ingredients','amount'=>300,'expense_date'=>'2026-09-30','version'=>0,'reason'=>'Synthetic invoice correction'],$owner);
        $this->assertEquals($assistant->id,$expense->user_id); $this->assertEquals($owner->id,$expense->edited_by);
        $this->assertEquals(250,ExpenseAudit::where('action','edited')->sole()->before_values['amount']);
        $this->assertEquals(300,app(FinancialReportService::class)->getExpenses('2026-09-01','2026-09-30'));
        $service->void($expense,1,'Synthetic duplicate invoice',$owner);
        $this->assertSoftDeleted($expense); $this->assertEquals(0,app(FinancialReportService::class)->getExpenses('2026-09-01','2026-09-30'));
        $this->assertDatabaseCount('expense_audits',3); $this->assertEquals('Synthetic duplicate invoice',$expense->fresh()->deletion_reason);
        $this->actingAs($owner)->get('/expenses?status=voided')->assertOk()->assertViewHas('totalExpenses',0);
    }

    public function test_expense_filtered_total_covers_matching_records(): void
    {
        $owner=$this->actor(); $service=app(ExpenseService::class);
        for($i=0;$i<25;$i++) $service->create(['submission_key'=>(string)Str::uuid(),'description'=>'Synthetic flour '.$i,'category'=>'ingredients','amount'=>10,'expense_date'=>'2026-09-30'],$owner);
        $service->create(['submission_key'=>(string)Str::uuid(),'description'=>'Outside category','category'=>'equipment','amount'=>100,'expense_date'=>'2026-09-30'],$owner);
        $this->actingAs($owner)->get('/expenses?category=ingredients&start_date=2026-09-01&end_date=2026-09-30')->assertOk()->assertViewHas('totalExpenses',250);
    }

    public function test_fully_paid_customer_cancellation_recognizes_all_verified_retained_money(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-15 08:00:00','UTC'));
        $owner=$this->actor(); $buyer=$this->buyer(); $product=Product::create(['product_name'=>'Synthetic package','price'=>1000,'is_active'=>true]);
        $option=$product->options()->create(['layers'=>1,'price'=>1000,'included_contents'=>'Synthetic cake','is_active'=>true]);
        $service=app(OrderService::class); $order=$service->createInternalOrder(['customer_id'=>$buyer->id,'pickup_date'=>'2026-09-20','pickup_time'=>'15:00','items'=>[['product_id'=>$product->id,'package_option_id'=>$option->id,'quantity'=>1]]],$owner);
        $service->recordDownPayment($order,500,'cash',null,$owner); $service->recordFinalPayment($order,500,'cash',null,$owner); $service->cancelOrder($order,$owner);
        $period=ReportPeriod::resolve(['mode'=>'month','month'=>'2026-09']);
        $report=app(FinancialReportService::class)->report($period); $summary=$report['summary'];
        $csv=$this->actingAs($owner)->get(route('reports.export',$period->query()))->assertOk()->streamedContent();
        file_put_contents(dirname(__DIR__).'/evidence/customer-cancellation.csv',$csv);
        $records=$this->get(route('reports.records',$period->query()+['metric'=>'cancellation_income']))->assertOk();
        $this->observation('customer-cancellation',['owner_decision'=>'Retain all verified payments','verified_paid'=>$order->fresh()->amount_paid,'refund_count'=>Refund::count(),'expected_retained'=>1000,'actual_summary'=>$summary,
            'actual_trend_retention_sum'=>array_sum(array_column($report['trends'],'cancellation_income')),'actual_drilldown_total'=>$records->viewData('total')]);
        $this->assertEquals(1000,$order->fresh()->amount_paid); $this->assertDatabaseCount('refunds',0);
        $this->assertEquals(1000,$summary['cancellation_income'],'Owner-approved retention includes the final payment.');
        $this->assertEquals(1000,$summary['operational_net_income']);
    }

    public function test_required_customer_contact_cannot_normalize_to_empty(): void
    {
        $response=$this->actingAs($this->actor())->post('/customers',['first_name'=>'Synthetic','last_name'=>'Invalid phone','phone_number'=>'abcdefgh']);
        $this->observation('customer-phone',['input'=>'abcdefgh','normalized'=>Customer::normalizePhoneNumber('abcdefgh'),'response_status'=>$response->getStatusCode(),'customer_count'=>Customer::count(),'saved_phone'=>Customer::first()?->phone_number]);
        $response->assertSessionHasErrors('phone_number');
    }

    public function test_public_contact_cannot_normalize_to_empty(): void
    {
        $product=Product::create(['product_name'=>'Synthetic public package','price'=>1000,'is_active'=>true]);
        $option=$product->options()->create(['layers'=>1,'price'=>1000,'included_contents'=>'Synthetic cake','is_active'=>true]);
        $response=$this->post('/order',['first_name'=>'Synthetic','last_name'=>'Invalid phone','phone_number'=>'abcdefgh','pickup_date'=>now()->addDay()->toDateString(),'pickup_time'=>'15:00','expected_total'=>1000,'items'=>[['product_id'=>$product->id,'package_option_id'=>$option->id,'quantity'=>1]]]);
        $this->observation('public-phone',['input'=>'abcdefgh','response_status'=>$response->getStatusCode(),'order_count'=>Order::count(),'saved_phone'=>Customer::first()?->phone_number]);
        $response->assertSessionHasErrors('phone_number');
    }

    public function test_refund_only_period_has_negative_net_collections_and_no_retention(): void
    {
        $owner=$this->actor(); $buyer=$this->buyer();
        $order=Order::create(['order_number'=>'SYNTHETIC-REFUND','customer_id'=>$buyer->id,'user_id'=>$owner->id,'status'=>'cancelled','cancellation_kind'=>'bakery_failure','cancelled_at'=>'2026-08-31 08:00:00','pickup_date'=>'2026-08-31','pickup_time'=>'16:00']);
        Payment::create(['order_id'=>$order->id,'user_id'=>$owner->id,'amount'=>500,'payment_type'=>'down_payment','payment_method'=>'cash','payment_date'=>'2026-08-30 08:00:00']);
        Refund::create(['order_id'=>$order->id,'requested_by'=>$owner->id,'completed_by'=>$owner->id,'amount'=>500,'reason'=>'Synthetic bakery failure','status'=>'completed','method'=>'cash','completed_at'=>'2026-09-01 08:00:00']);
        $period=ReportPeriod::resolve(['mode'=>'month','month'=>'2026-09']); $report=app(FinancialReportService::class)->report($period);
        $this->assertEquals(0,$report['summary']['gross_collections']); $this->assertEquals(500,$report['summary']['refunds_completed']);
        $this->assertEquals(-500,$report['summary']['payment_collections']); $this->assertEquals(0,$report['summary']['cancellation_income']);
        $this->assertEquals(-500,array_sum(array_column($report['trends'],'payment_collections')));
        $this->assertEquals(-500,$report['methods']->sum('net'));
        $csv=$this->actingAs($owner)->get(route('reports.export',$period->query()))->assertOk()->streamedContent();
        $this->assertStringContainsString('-500',$csv);
    }

    public function test_pickup_schedule_date_and_terminal_status_scope(): void
    {
        $owner=$this->actor(); $buyer=$this->buyer();
        foreach(['pending','confirmed','preparing','ready_for_pickup','completed','cancelled'] as $status) Order::create(['order_number'=>'SYNTHETIC-'.$status,'customer_id'=>$buyer->id,'user_id'=>$owner->id,'status'=>$status,'pickup_date'=>'2026-10-10','pickup_time'=>'15:00']);
        $response=$this->actingAs($owner)->get('/pickup-schedule?pickup_date=2026-10-10')->assertOk();
        $orders=$response->viewData('orders')->flatten(); $this->assertCount(4,$orders);
        $this->assertEqualsCanonicalizing(['pending','confirmed','preparing','ready_for_pickup'],$orders->pluck('status')->all());
    }

    public function test_single_row_stock_rules_and_baseline_are_explicit(): void
    {
        $actor=$this->actor(); $s=$this->supply('Synthetic unit baseline'); $service=app(InventoryService::class);
        $service->establishBaseline($s,'legacy_reconciliation');
        $this->assertDatabaseHas('inventory_baselines',['supply_id'=>$s->id,'opening_quantity'=>10,'unit'=>'kg','source'=>'legacy_reconciliation']);
        $legacy=$this->supply('Synthetic pre-adoption stock'); $legacy->update(['current_quantity'=>15]);
        foreach([['stock_in',8],['stock_out',3]] as [$kind,$quantity]) \App\Models\InventoryTransaction::create(['supply_id'=>$legacy->id,'user_id'=>$actor->id,'transaction_type'=>$kind,'quantity'=>$quantity,'transaction_date'=>'2026-09-01']);
        $service->establishBaseline($legacy,'legacy_reconciliation');
        $this->assertDatabaseHas('inventory_baselines',['supply_id'=>$legacy->id,'opening_quantity'=>10,'observed_quantity'=>15,'legacy_net_quantity'=>5,'source'=>'legacy_reconciliation']);
        foreach([['type'=>'usage','quantity'=>11,'notes'=>'Synthetic excessive quantity'],['type'=>'waste','quantity'=>1,'notes'=>'']] as $case) {
            try { $service->post(['submission_key'=>(string)Str::uuid(),'type'=>$case['type'],'operation_date'=>'2026-10-05','notes'=>$case['notes'],'lines'=>[['supply_id'=>$s->id,'quantity'=>$case['quantity']]]],$actor); $this->fail('Invalid business quantity/reason accepted.'); }
            catch(\Illuminate\Validation\ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->actingAs($actor)->patch('/supplies/'.$s->id,['supply_name'=>$s->supply_name,'category'=>'ingredients','unit'=>'bag','current_quantity'=>10,'reorder_level'=>2,'is_active'=>1])->assertSessionHasErrors('unit');
    }

    public function test_receipt_review_is_separate_from_verified_money(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-15 08:00:00','UTC'));
        \Illuminate\Support\Facades\Storage::fake('receipts');
        $actor=$this->actor(); $buyer=$this->buyer(); $p=Product::create(['product_name'=>'Synthetic proof package','price'=>1000,'is_active'=>true]);
        $option=$p->options()->create(['layers'=>1,'price'=>1000,'included_contents'=>'Synthetic cake','is_active'=>true]);
        $order=app(OrderService::class)->createPublicOrder(['customer_id'=>$buyer->id,'pickup_date'=>'2026-09-20','pickup_time'=>'15:00','items'=>[['product_id'=>$p->id,'package_option_id'=>$option->id,'quantity'=>1]]]);
        $reviews=app(\App\Services\PaymentReviewService::class);
        $first=$reviews->submit($order,\Illuminate\Http\UploadedFile::fake()->image('synthetic-first.png'),'QA-REJECT');
        $reviews->reject($first,'Synthetic transaction not found',$actor);
        $second=$reviews->submit($order,\Illuminate\Http\UploadedFile::fake()->image('synthetic-second.png'),'QA-ACCEPT');
        $this->assertSame('rejected',$first->fresh()->status); $this->assertSame('awaiting_verification',$second->status);
        $this->assertEquals(0,$order->fresh()->amount_paid);
        $this->assertEquals(0,app(FinancialReportService::class)->getPaymentCollections('2026-09-01','2026-09-30'));
        $this->actingAs($actor)->post('/payment-proofs/'.$second->id.'/accept',['amount'=>500,'reference_number'=>'QA-ACCEPT'])->assertSessionHasErrors('account_checked');
        $this->post('/payment-proofs/'.$second->id.'/accept',['amount'=>500,'reference_number'=>'QA-ACCEPT','account_checked'=>'1'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed',$order->fresh()->status); $this->assertEquals(500,$order->fresh()->amount_paid);
        $this->assertEquals(500,app(FinancialReportService::class)->getPaymentCollections('2026-09-01','2026-09-30'));
    }

    public function test_bakery_failure_full_refund_waits_for_transfer_confirmation(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-15 08:00:00','UTC'));
        $actor=$this->actor(); $buyer=$this->buyer(); $p=Product::create(['product_name'=>'Synthetic failure package','price'=>1000,'is_active'=>true]);
        $option=$p->options()->create(['layers'=>1,'price'=>1000,'included_contents'=>'Synthetic cake','is_active'=>true]);
        $orders=app(OrderService::class);
        $order=$orders->createInternalOrder(['customer_id'=>$buyer->id,'pickup_date'=>'2026-09-20','pickup_time'=>'15:00','items'=>[['product_id'=>$p->id,'package_option_id'=>$option->id,'quantity'=>1]]],$actor);
        $orders->recordDownPayment($order,500,'cash',null,$actor); $orders->recordFinalPayment($order,500,'gcash','QA-FINAL',$actor);
        $refunds=app(\App\Services\RefundService::class); $refund=$refunds->markBakeryFailure($order,'Synthetic oven failure',$actor);
        $this->assertEquals(1000,$refund->amount); $this->assertSame('pending',$refund->status);
        $reports=app(FinancialReportService::class); $this->assertEquals(1000,$reports->getPaymentCollections('2026-09-01','2026-09-30'));
        $this->assertEquals(0,$reports->getCancellationIncome('2026-09-01','2026-09-30'));
        $refunds->complete($refund,['method'=>'cash','reference_number'=>'QA-REFUND','transfer_confirmed'=>'1'],$actor);
        $this->assertEquals(0,$reports->getPaymentCollections('2026-09-01','2026-09-30')); $this->assertSame('completed',$refund->fresh()->status);
    }

    public function test_ordinary_package_line_add_edit_remove_and_checkout(): void
    {
        $p=Product::create(['product_name'=>'Synthetic draft package','price'=>1000,'is_active'=>true]);
        $option=$p->options()->create(['layers'=>1,'price'=>1000,'included_contents'=>'Synthetic cake','is_active'=>true]);
        $a=(string)Str::uuid(); $b=(string)Str::uuid();
        $payload=fn($quantity,$theme)=>['items'=>[['product_id'=>$p->id,'package_option_id'=>$option->id,'quantity'=>$quantity,'themes'=>$theme]]];
        $this->post('/packages/'.$p->id.'/customize/'.$a,$payload(1,'First line'))->assertSessionHasNoErrors();
        $this->post('/packages/'.$p->id.'/customize/'.$b,$payload(2,'Second line'))->assertSessionHasNoErrors();
        $this->assertCount(2,session('public_order_draft.items'));
        $this->post('/packages/'.$p->id.'/customize/'.$a,$payload(3,'Edited first line'))->assertSessionHasNoErrors();
        $lines=collect(session('public_order_draft.items')); $this->assertEquals(3,$lines->firstWhere('draft_key',$a)['quantity']);
        $this->assertSame('Second line',$lines->firstWhere('draft_key',$b)['themes']);
        $this->post('/order/packages/'.$b.'/remove')->assertRedirect(); $this->assertCount(1,session('public_order_draft.items'));
        $this->get('/order/details')->assertOk()->assertViewHas('quote',fn($q)=>(float)$q['total']===3000.0);
        $this->post('/order',['first_name'=>'Synthetic','last_name'=>'Draft buyer','phone_number'=>'09170000000','pickup_date'=>now()->addDay()->toDateString(),'pickup_time'=>'15:00','expected_total'=>3000])->assertSessionHasNoErrors()->assertRedirect();
        $order=Order::sole(); $this->assertCount(1,$order->orderDetails); $this->assertSame('Edited first line',$order->orderDetails->sole()->themes); $this->assertEquals(3000,$order->total_amount);
        $this->assertDatabaseCount('payments',0);
    }

    public function test_owner_normal_catalog_settings_users_and_customer_edits(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $owner=$this->actor(); $this->actingAs($owner);
        $this->post('/products',['product_name'=>'Synthetic editable package','description'=>'QA fixture only','is_active'=>1])->assertSessionHasNoErrors();
        $p=Product::sole(); $this->patch('/products/'.$p->id,['product_name'=>'Synthetic edited package','description'=>'QA fixture only','is_active'=>1])->assertSessionHasNoErrors();
        $this->post('/products/'.$p->id.'/options',['layers'=>1,'price'=>1000,'included_contents'=>'Synthetic cake','is_active'=>1])->assertSessionHasNoErrors();
        $this->post('/add-ons',['name'=>'Synthetic extra','description'=>'QA fixture only','price'=>50,'products'=>[$p->id],'is_active'=>1])->assertSessionHasNoErrors();
        $extra=\App\Models\AddOn::sole(); $option=$p->options()->sole();
        $this->patch('/products/'.$p->id.'/options/'.$option->id,['layers'=>1,'price'=>1200,'included_contents'=>'Synthetic cake','included_items'=>[['add_on_id'=>$extra->id,'quantity'=>2]],'is_active'=>1])->assertSessionHasNoErrors();
        $this->assertEquals(1200,$option->fresh()->price); $this->assertCount(1,$option->fresh()->includedItems);
        $this->patch('/add-ons/'.$extra->id,['name'=>'Synthetic changed extra','description'=>'QA fixture only','price'=>60,'products'=>[$p->id],'is_active'=>1])->assertSessionHasNoErrors();
        $this->post('/payment-settings',['account_name'=>'SYNTHETIC QA ONLY','account_number'=>'TEST-NOT-PAYABLE','photo'=>\Illuminate\Http\UploadedFile::fake()->image('synthetic-nonpayment-placeholder.png')])->assertSessionHasNoErrors();
        $this->assertSame('SYNTHETIC QA ONLY',\App\Models\PaymentSetting::sole()->account_name);
        $this->post('/users',['first_name'=>'Synthetic','last_name'=>'Assistant','email'=>'synthetic-staff@example.test','role'=>'assistant','is_active'=>1,'password'=>'SyntheticFixtureOnly123','password_confirmation'=>'SyntheticFixtureOnly123'])->assertSessionHasNoErrors();
        $assistant=User::where('role','assistant')->sole();
        $this->patch('/users/'.$assistant->id,['first_name'=>'Synthetic','last_name'=>'Renamed assistant','email'=>$assistant->email,'role'=>'assistant','is_active'=>0,'password'=>''])->assertSessionHasNoErrors();
        $this->assertFalse($assistant->fresh()->is_active); $this->assertSame('Renamed assistant',$assistant->fresh()->last_name);
        $this->post('/customers',['first_name'=>'Synthetic','last_name'=>'Editable buyer','phone_number'=>'+63 917 000 0000'])->assertSessionHasNoErrors();
        $buyer=Customer::sole();
        $this->patch('/customers/'.$buyer->id,['first_name'=>'Synthetic','last_name'=>'Edited buyer','phone_number'=>'09170000001'])->assertSessionHasNoErrors();
        $this->assertSame('09170000001',$buyer->fresh()->phone_number); $this->assertSame('Edited buyer',$buyer->fresh()->last_name);
    }

    public function test_dashboard_today_pickups_uses_the_bakery_calendar(): void
    {
        config(['app.timezone'=>'UTC','bakery.pickup_timezone'=>'Asia/Manila','bakery.business_timezone'=>'Asia/Manila']);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-04 17:00:00','UTC'));
        $owner=$this->actor(); $buyer=$this->buyer();
        foreach(['2026-10-04'=>'SYNTHETIC-YESTERDAY','2026-10-05'=>'SYNTHETIC-TODAY'] as $date=>$number) Order::create(['order_number'=>$number,'customer_id'=>$buyer->id,'user_id'=>$owner->id,'status'=>'pending','pickup_date'=>$date,'pickup_time'=>'15:00']);
        $response=$this->actingAs($owner)->get('/dashboard')->assertOk();
        $actual=$response->viewData('todayPickups')->pluck('order_number')->all();
        $this->observation('dashboard-pickup-date',['frozen_utc'=>'2026-10-04 17:00:00','bakery_date'=>'2026-10-05','app_today'=>today()->toDateString(),'expected'=>['SYNTHETIC-TODAY'],'actual'=>$actual]);
        $this->assertSame(['SYNTHETIC-TODAY'],$actual);
    }

    public function test_new_public_order_rejects_a_past_bakery_pickup_date(): void
    {
        config(['app.timezone'=>'UTC','bakery.pickup_timezone'=>'Asia/Manila']);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-04 17:00:00','UTC'));
        $p=Product::create(['product_name'=>'Synthetic dated package','price'=>1000,'is_active'=>true]);
        $option=$p->options()->create(['layers'=>1,'price'=>1000,'included_contents'=>'Synthetic cake','is_active'=>true]);
        $response=$this->post('/order',['first_name'=>'Synthetic','last_name'=>'Date buyer','phone_number'=>'09170000000','pickup_date'=>'2026-10-04','pickup_time'=>'15:00','expected_total'=>1000,'items'=>[['product_id'=>$p->id,'package_option_id'=>$option->id,'quantity'=>1]]]);
        $this->observation('past-pickup-date',['frozen_utc'=>'2026-10-04 17:00:00','bakery_now'=>'2026-10-05 01:00:00','submitted_pickup'=>'2026-10-04 15:00:00 Asia/Manila','response_status'=>$response->getStatusCode(),'created_order_count'=>Order::count()]);
        $response->assertSessionHasErrors('pickup_date');
    }
}
