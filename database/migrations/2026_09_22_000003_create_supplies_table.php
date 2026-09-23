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
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('supply_name', 255);
            $table->enum('category', ['ingredients', 'packaging']);
            $table->string('unit', 50);
            $table->decimal('current_quantity', 10, 2)->default(0.00);
            $table->decimal('reorder_level', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['current_quantity', 'reorder_level'], 'idx_supplies_reorder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
