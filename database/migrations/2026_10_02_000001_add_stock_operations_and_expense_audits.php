<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_version')->default(0);
            $table->index(['is_active', 'category', 'supply_name']);
        });
        Schema::create('inventory_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('opening_quantity', 14, 2);
            $table->decimal('observed_quantity', 14, 2);
            $table->decimal('legacy_net_quantity', 14, 2)->default(0);
            $table->string('unit', 50);
            $table->string('source');
            $table->timestamp('established_at');
        });
        Schema::create('inventory_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_key')->unique();
            $table->string('payload_hash', 64);
            $table->string('type', 30);
            $table->date('operation_date')->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('supplier')->nullable();
            $table->string('delivery_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('inventory_operations')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('inventory_operation_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity_before', 10, 2)->nullable();
            $table->decimal('quantity_after', 10, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('inventory_transactions')->restrictOnDelete();
            $table->index(['supply_id', 'transaction_date']);
        });
        // This is a reconciliation baseline, not an invented historical delivery.
        DB::table('supplies')->orderBy('id')->chunkById(200, function ($supplies) {
            foreach ($supplies as $supply) {
                $net = DB::table('inventory_transactions')->where('supply_id', $supply->id)
                    ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'stock_out' THEN -quantity ELSE quantity END), 0) AS net")->value('net');
                DB::table('inventory_baselines')->insert([
                    'supply_id' => $supply->id,
                    'opening_quantity' => round($supply->current_quantity - $net, 2),
                    'observed_quantity' => $supply->current_quantity,
                    'legacy_net_quantity' => $net, 'unit' => $supply->unit,
                    'source' => 'legacy_reconciliation', 'established_at' => now(),
                ]);
            }
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('edited_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('deletion_reason')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->uuid('submission_key')->nullable()->unique();
            $table->index(['deleted_at', 'category', 'expense_date']);
        });
        Schema::create('expense_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('recorded_at')->index();
        });
        DB::table('expenses')->orderBy('id')->chunkById(200, function ($expenses) {
            foreach ($expenses as $expense) {
                DB::table('expense_audits')->insert([
                    'expense_id' => $expense->id, 'actor_id' => null, 'action' => 'legacy_baseline',
                    'after_values' => json_encode($expense), 'recorded_at' => now(),
                    'reason' => 'Existing record at audit adoption. Earlier edit history is unavailable.',
                ]);
            }
        });
    }

    public function down(): void
    {
        // Financial and movement history must never be discarded by a routine rollback.
        throw new RuntimeException('This data-preserving migration is forward-only. Restore a verified backup to roll back.');
    }
};
