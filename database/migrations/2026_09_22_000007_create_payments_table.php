<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_type', ['down_payment', 'final_payment']);
            $table->enum('payment_method', ['cash', 'gcash']);
            $table->string('reference_number', 100)->nullable();
            $table->dateTime('payment_date');
            $table->timestamps();

            $table->index('payment_date', 'idx_payments_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
