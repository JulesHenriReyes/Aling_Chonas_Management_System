<?php
declare(strict_types=1);
require __DIR__.'/engine.php';
$prepared=json_decode(file_get_contents(__DIR__.'/prepared.json'),true,512,JSON_THROW_ON_ERROR);
$root=dirname(__DIR__,4);$private=dirname($prepared['backup']);$index=0;$unchanged=[];$concurrent=[];
foreach($prepared['dirty_hashes'] as $file=>$hash) {
    if($file!=='app/Http/Controllers/OrderController.php') {
        if(hash_file('sha256',$root.'/'.$file)===$hash)$unchanged[]=$file;
        else $concurrent[]=['path'=>$file,'modified_at'=>date(DATE_ATOM,filemtime($root.'/'.$file)),
            'current_sha256'=>hash_file('sha256',$root.'/'.$file),'baseline_sha256'=>$hash];
    } else {
        $original=str_replace("\r\n","\n",file_get_contents($private.'/working-file-'.$index));
        $current=str_replace("\r\n","\n",file_get_contents($root.'/'.$file));
        $addition= <<<'TEXT'
            } elseif ($request->status === 'confirmed') {
                $query->where('status', 'confirmed')->workflowQueue('booked')
                    ->whereDoesntHave('paymentProofs', fn ($proof) => $proof->where('status', 'awaiting_verification'));
TEXT;
        demand(substr_count($current,$addition."\n")===1,'Intended controller fix differs.');
        demand(str_replace($addition."\n",'',$current)===$original,'Controller has changes beyond intended queue fix.');
    }
    $index++;
}
$docx='C:/Users/User/Desktop/Company informations/milestone4-work/defense-audio-2026-10-11/Defense_Study_Guide.docx';
demand(hash_file('sha256',$docx)==='8edd0519b4eb865b0beadcfd1fa1591078e253087f191730bd81766596ff8e37','Verified study DOCX changed.');
$result=['unrelated_dirty_files_unchanged'=>$unchanged,'unrelated_concurrent_changes_preserved'=>$concurrent,
    'controller_only_intended_fix'=>true,'study_docx_present_and_unchanged'=>true];
file_put_contents(__DIR__.'/file-preservation.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($result,JSON_PRETTY_PRINT)."\n";

