<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_entries')) Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->constrained()->restrictOnDelete();
            $table->string('source', 40);
            $table->date('stock_in_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('opening_quantity', 10, 2)->default(0);
            $table->decimal('remaining_quantity', 10, 2)->default(0);
            $table->foreignId('parent_entry_id')->nullable()->constrained('stock_entries')->restrictOnDelete();
            $table->timestamps();
            $table->index(['supply_id', 'expiry_date', 'id']);
        });
        if (!Schema::hasTable('stock_allocations')) Schema::create('stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_entry_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->decimal('quantity_before', 10, 2);
            $table->decimal('quantity_after', 10, 2);
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('stock_allocations')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['inventory_transaction_id', 'stock_entry_id']);
        });
        DB::table('supplies')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) DB::transaction(function () use ($row) {
                $supply = DB::table('supplies')->where('id', $row->id)->lockForUpdate()->first();
                if ((float)$supply->current_quantity <= 0 || DB::table('stock_entries')->where('supply_id', $supply->id)->exists()) return;
                DB::table('stock_entries')->insert([
                    'supply_id' => $supply->id, 'source' => 'opening_stock', 'stock_in_date' => null, 'expiry_date' => null,
                    'opening_quantity' => $supply->current_quantity, 'remaining_quantity' => $supply->current_quantity,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Stock history is preserved. Restore a verified pre-adoption backup or apply a forward correction.');
    }
};
