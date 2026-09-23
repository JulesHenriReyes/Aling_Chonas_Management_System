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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('description', 255);
            $table->enum('category', ['ingredients', 'packaging', 'equipment', 'miscellaneous']);
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->timestamps();

            $table->index('expense_date', 'idx_expenses_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
