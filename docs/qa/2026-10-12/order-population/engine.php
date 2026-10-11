<?php
declare(strict_types=1);
use App\Models\{Customer, Order, User};
use App\Services\{OrderService, OrderReviewService, PaymentReviewService, FinancialReportService, ReportPeriod};
use App\Http\Controllers\{OrderController, ReportsController};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{DB, Storage, Auth};
function demand(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
function money(mixed $value):int {return (int)round((float)$value*100);}
function clockAt(string $iso):void {
    $date=Carbon::parse($iso)->setTimezone(config('app.timezone'));
    Carbon::setTestNow($date); CarbonImmutable::setTestNow(CarbonImmutable::instance($date));
}
function clearClock():void {Carbon::setTestNow();CarbonImmutable::setTestNow();}
function addRequestedOrders(array $prepared,array &$writtenReceipts):array {
    $owner=User::where('role','owner')->where('is_active',true)->firstOrFail();
    Auth::setUser($owner);
    $orders=app(OrderService::class); $reviews=app(OrderReviewService::class);
    $created=[];
    try {
        foreach($prepared['plan'] as $row) {
            clockAt($row['created_at']);
            demand(!Order::where('submission_key',$row['submission_key'])->exists(),'A planned submission already exists; use the audit instead of inserting again.');
            $customer=Customer::findOrCreateMatching(['first_name'=>$row['first_name'],'last_name'=>$row['last_name'],'phone_number'=>$row['phone_number']]);
            $pickup=Carbon::parse($row['pickup_at']);
            $data=['customer_id'=>$customer->id,'pickup_date'=>$pickup->toDateString(),'pickup_time'=>$pickup->format('H:i'),
                'submission_key'=>$row['submission_key'],'expected_total'=>$row['expected_total'],'items'=>$row['items']];
            $order=$row['public']?$orders->createPublicOrder($data):$orders->createInternalOrder($data,$owner);
            if($row['public'] && in_array($row['stage'],['deposit','receipts'],true)) {
                clockAt(Carbon::parse($row['created_at'])->addHour()->toIso8601String());
                $order=$reviews->confirm($order,$owner,true);
            }
            clockAt(Carbon::parse($row['created_at'])->addHours(2)->toIso8601String());
            if($row['stage']==='receipts') {
                $image=imagecreatetruecolor(720,260);
                $white=imagecolorallocate($image,255,255,255); $black=imagecolorallocate($image,25,25,25);
                imagefill($image,0,0,$white);
                $text=['SAMPLE PAYMENT RECEIPT FOR ORDER PREVIEW','Customer: '.$row['first_name'].' '.$row['last_name'],
                    'Reference: '.$row['proof_reference'],'Reported deposit: PHP '.number_format($row['expected_deposit'],2),
                    'Awaiting Owner verification - no money recorded'];
                foreach($text as $index=>$line)imagestring($image,5,22,22+42*$index,$line,$black);
                $temp=tempnam(sys_get_temp_dir(),'sample-receipt-'); imagepng($image,$temp); imagedestroy($image);
                try {
                    $proof=app(PaymentReviewService::class)->submit($order,new UploadedFile($temp,'payment-receipt.png','image/png',null,true),$row['proof_reference']);
                    $writtenReceipts[]=$proof->file_path;
                } finally {unlink($temp);}
            }
            if(in_array($row['stage'],['confirmed','preparing','ready_for_pickup','completed','cancelled_deposit'],true))
                $orders->recordDownPayment($order,$row['expected_deposit'],$row['deposit_method'],$row['deposit_reference'],$owner);
            if(in_array($row['stage'],['preparing','ready_for_pickup','completed'],true)) {
                clockAt($row['preparing_at']); $order=$orders->updateStatus($order,'preparing',$owner);
            }
            if(in_array($row['stage'],['ready_for_pickup','completed'],true)) {
                clockAt($row['ready_at']); $order=$orders->updateStatus($order,'ready_for_pickup',$owner);
            }
            if($row['stage']==='completed') {
                clockAt($row['pickup_at']);
                $orders->recordFinalPayment($order,$row['expected_total']-$row['expected_deposit'],$row['final_method'],$row['final_reference'],$owner,null,true);
            }
            if(str_starts_with($row['stage'],'cancelled_')) {
                clockAt($row['cancelled_at']); $order=$orders->cancelOrder($order,$owner);
            }
            $created[]=$order->id;
        }
    } finally {clearClock();}
    return $created;
}
function canonicalRow(array $row):string {
    ksort($row); foreach($row as &$value)if($value!==null) {
        $value=(string)$value;
        // SQLite and MariaDB expose decimal scale differently on identical copied rows.
        if(is_numeric($value) && str_contains($value,'.'))$value=rtrim(rtrim($value,'0'),'.');
    }
    return hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));
}
function auditOrders(array $prepared,array $ids,array $snapshot):array {
    demand(count($ids)===20 && count(array_unique($ids))===20,'Exactly 20 unique orders are required.');
    demand(Order::count()===$prepared['baseline_order_count']+20,'Active order count does not match exactly 20 additions.');
    $owner=User::where('role','owner')->where('is_active',true)->firstOrFail(); Auth::setUser($owner);
    app('view')->share('errors',new Illuminate\Support\ViewErrorBag());
    $expected=['sales'=>0,'gross_collections'=>0,'cancellation_income'=>0,'expenses'=>0,'completed_order_count'=>3];
    $methodExpected=['cash'=>0,'gcash'=>0]; $expectedDays=[];$expectedPackages=[];
    $stageIds=[]; $records=[];
    foreach($prepared['plan'] as $i=>$row) {
        $order=Order::with(['customer','orderDetails.addOns','payments','paymentProofs'])->findOrFail($ids[$i]);
        $stage=$row['stage']; $stageIds[$stage][]=$order->id;
        $status=in_array($stage,['deposit','receipts'],true)?'confirmed':(str_starts_with($stage,'cancelled_')?'cancelled':$stage);
        demand($order->status===$status,'Incorrect lifecycle status for '.$order->id);
        demand($order->submission_key===$row['submission_key'],'Wrong submission identity.');
        demand($order->customer->first_name===$row['first_name'] && $order->customer->last_name===$row['last_name'],'Customer name mismatch.');
        demand(stripos($order->customer->full_name,'demo')===false,'Unexpected demo customer name.');
        demand(money($order->total_amount)===money($row['expected_total']),'Saved price snapshots do not reconcile.');
        demand($order->orderDetails->count()===1 && $order->orderDetails[0]->themes===$row['items'][0]['themes']
            && $order->orderDetails[0]->special_request===$row['items'][0]['special_request'],'Design instructions mismatch.');
        $paidStage=in_array($stage,['confirmed','preparing','ready_for_pickup','completed','cancelled_deposit'],true);
        $paid=$stage==='completed'?$row['expected_total']:($paidStage?$row['expected_deposit']:0);
        demand(money($order->amount_paid)===money($paid),'Payment amount inconsistent with stage.');
        demand(money($order->remaining_balance)===money($row['expected_total']-$paid),'Incorrect balance.');
        demand($order->payments->count()===($stage==='completed'?2:($paidStage?1:0)),'Payment count mismatch.');
        demand($order->hasAwaitingReceipt()===($stage==='receipts'),'Pending proof mismatch.');
        if($stage==='receipts') {
            demand($order->paymentProofs->count()===1 && Storage::disk('receipts')->exists($order->paymentProofs[0]->file_path),'Receipt attachment missing.');
            demand($order->paymentProofs[0]->reference_number===$row['proof_reference'],'Proof reference mismatch.');
        }
        if($paidStage)demand($order->hasVerifiedDeposit(),'Missing exact verified 50% deposit.');
        foreach($order->payments as $payment) {
            demand($payment->user_id===$owner->id,'Payment is not attributable to Owner.');
            demand($payment->payment_date->lte(now()),'Future payment incorrectly appears in collections.');
            demand($payment->payment_date->gte($order->created_at),'Payment predates creation.');
            $isDeposit=$payment->payment_type==='down_payment';
            $prefix=$isDeposit?'deposit':'final';
            demand($payment->payment_method===$row[$prefix.'_method'],'Wrong payment method.');
            if($payment->payment_method==='gcash') {
                demand((bool)preg_match('/^[0-9]{13}$/',$payment->reference_number),'GCash reference must be numeric.');
                demand($payment->reference_number===$row[$prefix.'_reference'],'Wrong numeric reference.');
                demand(DB::table('gcash_references')->where('reference_number',$payment->reference_number)->value('payment_id')===$payment->id,'Reference not bound to ledger.');
            } else demand($payment->reference_number===null,'Cash should not carry a fabricated transfer reference.');
            $expected['gross_collections']+=money($payment->amount);
            $methodExpected[$payment->payment_method]+=money($payment->amount);
            $day=($isDeposit?Carbon::parse($row['created_at'])->addHours(2):Carbon::parse($row['pickup_at']))->format('Y-m-d');
            $expectedDays[$day]['gross_collections']=($expectedDays[$day]['gross_collections']??0)+money($payment->amount);
        }
        if($stage==='completed') {
            demand($order->payment_status==='fully_paid' && $order->completed_at!==null && $order->ready_at!==null,'Completed order missing payment or timestamps.');
            demand($order->completed_at->eq(Carbon::parse($row['pickup_at'])),'Completion must use actual pickup date.');
            $expected['sales']+=money($row['expected_total']);
            $day=Carbon::parse($row['pickup_at'])->format('Y-m-d');
            $expectedDays[$day]['sales']=($expectedDays[$day]['sales']??0)+money($row['expected_total']);
            foreach($order->orderDetails as $detail) {
                $key=$detail->product_id;
                $expectedPackages[$key]['package_quantity']=($expectedPackages[$key]['package_quantity']??0)+$detail->quantity;
                $expectedPackages[$key]['total_amount']=($expectedPackages[$key]['total_amount']??0)+money($row['expected_total']);
            }
        } else demand($order->completed_at===null,'Noncompleted order has completion timestamp.');
        if(str_starts_with($stage,'cancelled_'))demand($order->cancelled_at!==null && $order->cancellation_kind==='customer','Cancellation record missing.');
        if($stage==='cancelled_deposit') {
            $expected['cancellation_income']+=money($row['expected_deposit']);
            $day=Carbon::parse($row['cancelled_at'])->format('Y-m-d');
            $expectedDays[$day]['cancellation_income']=($expectedDays[$day]['cancellation_income']??0)+money($row['expected_deposit']);
        }
        $records[]=['id'=>$order->id,'order_number'=>$order->order_number,'customer'=>$row['first_name'].' '.$row['last_name'],
          'stage'=>$stage,'status'=>$order->status,'label'=>$order->workflowLabel(),'total'=>$order->total_amount,'paid'=>$order->amount_paid,'balance'=>$order->remaining_balance];
    }
    $unmodified=0;
    foreach($snapshot['tables'] as $table=>$before) {
        if(in_array($table,['sessions','cache','cache_locks','jobs','failed_jobs','job_batches'],true))continue;
        $current=[];foreach(DB::table($table)->get() as $record)$current[canonicalRow((array)$record)]=true;
        foreach($before as $record)demand(isset($current[canonicalRow($record)]),'A pre-existing record changed in '.$table);
        $unmodified+=count($before);
    }
    $tabCriteria=['pending'=>['status'=>'pending'],'deposit'=>['queue'=>'deposit'],'receipts'=>['queue'=>'receipts'],
      'confirmed'=>['status'=>'confirmed'],'preparing'=>['status'=>'preparing'],'ready_for_pickup'=>['status'=>'ready_for_pickup'],
      'completed'=>['status'=>'completed'],'cancelled'=>['status'=>'cancelled']];
    $tabCounts=[]; $htmlChecks=0;
    foreach($tabCriteria as $tab=>$criteria) {
        $view=app(OrderController::class)->index(Request::create('/orders','GET',$criteria));
        $orders=$view->getData()['orders'];
        $seen=array_values(array_intersect($ids,$orders->pluck('id')->all()));
        $wanted=$tab==='cancelled'?array_merge($stageIds['cancelled_unpaid'],$stageIds['cancelled_deposit']):$stageIds[$tab];
        sort($seen);sort($wanted);demand($seen===$wanted,'Tab '.$tab.' includes orders from the wrong workflow stage.');
        $tabCounts[$tab]=count($seen);
        $html=$view->render();
        demand(!str_contains($html,'Pending verification') || $tab==='receipts','Unverified payment unexpectedly shown in '.$tab.' tab.');
        $htmlChecks++;
    }
    $period=ReportPeriod::resolve(['mode'=>'month','month'=>$prepared['report_month']]);
    $service=app(FinancialReportService::class);$report=$service->report($period);
    foreach($expected as $key=>$value) {
        $delta=$report['summary'][$key]-$prepared['baseline_report'][$key];
        demand($key==='completed_order_count'?$delta===$value:money($delta)===$value,'Report summary fails reconciliation: '.$key);
    }
    foreach(['sales','gross_collections','cancellation_income','expenses'] as $key) {
        demand(money(array_sum(array_column($report['trends'],$key)))===money($report['summary'][$key]),'Trend does not sum to summary.');
        $batch=$service->events($period,$key)->whereIn('order_id',$ids)->get();
        demand(money($batch->sum('amount'))===$expected[$key],'Event drilldown fails batch reconciliation: '.$key);
        foreach($report['trends'] as $day) {
            $batchAtDay=$batch->filter(fn($event)=>Carbon::parse($event->event_at,config('app.timezone'))->setTimezone($period->timezone)->toDateString()===$day['period']);
            demand(money($batchAtDay->sum('amount'))===($expectedDays[$day['period']][$key]??0),'Daily attribution mismatch for '.$key);
        }
    }
    demand(money($report['summary']['operational_net_income']-$prepared['baseline_report']['operational_net_income'])===$expected['sales']+$expected['cancellation_income'],'Net result double counts collections.');
    demand(money($report['packages']->sum('total_amount'))===money($report['summary']['sales']),'Package totals disagree with completed sales.');
    foreach($expectedPackages as $product=>$values) {
        $pkg=$report['packages']->firstWhere('product_id',$product);
        // Baseline has no orders; this is deliberately checked before population.
        demand($prepared['baseline_order_count']===0 && (int)$pkg->package_quantity===$values['package_quantity']
            && money($pkg->total_amount)===$values['total_amount'],'Package snapshot reconciliation mismatch.');
    }
    foreach($report['methods'] as $method)demand(money($method['gross'])===$methodExpected[$method['method']],'Payment method mismatch.');
    $reportsController=app(ReportsController::class);
    $query=['mode'=>'month','month'=>$prepared['report_month']];
    $reportsController->index(Request::create('/reports','GET',$query))->render();
    foreach(['sales','gross_collections','cancellation_income','expenses'] as $key) {
        $view=$reportsController->records(Request::create('/reports/records','GET',$query+['metric'=>$key]));
        demand(money($view->getData()['total'])===money($report['summary'][$key]),'Report controller drilldown mismatch.');
        $view->render();
    }
    $csvResponse=$reportsController->export(Request::create('/reports/export','GET',$query));
    ob_start();$csvResponse->sendContent();$csv=ob_get_clean();
    $lines=array_values(array_filter(explode("\n",trim($csv))));$columns=str_getcsv(array_shift($lines));
    $csvRows=array_map(fn($line)=>array_combine($columns,str_getcsv($line)),$lines);
    $summary=collect($csvRows)->firstWhere('section','summary');
    foreach(['sales','gross_collections','cancellation_income','expenses','operational_net_income'] as $key)
        demand(money($summary[$key])===money($report['summary'][$key]),'CSV does not match report: '.$key);
    return ['orders'=>$records,'tab_counts'=>$tabCounts,'report_summary'=>$report['summary'],
      'batch_report_delta'=>array_map(fn($cents)=>$cents/100,array_diff_key($expected,['completed_order_count'=>true])),
      'completed_order_count'=>3,'payment_methods'=>$report['methods']->all(),'rendered_order_tabs'=>$htmlChecks,
      'csv_and_drilldowns_match'=>true,'preexisting_business_rows_unchanged'=>$unmodified,'receipt_count'=>2];
}

