<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('refunds') && DB::table('refunds')->exists()) {
            throw new RuntimeException('Refund records exist. Archive and reconcile those records before removing the refunds table. No records have been deleted.');
        }

        Schema::dropIfExists('refunds');
    }

    public function down(): void
    {
        if (Schema::hasTable('refunds')) {
            return;
        }

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->string('status', 24)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('method', 20)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'completed_at']);
        });
    }
};
