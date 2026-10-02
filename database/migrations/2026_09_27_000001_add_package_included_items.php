<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_option_inclusions', function (Blueprint $table) {
            $table->foreignId('package_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->primary(['package_option_id', 'add_on_id']);
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->json('included_items_snapshot')->nullable();
        });
        // Do not infer quantities from legacy text or rewrite existing orders.
    }

    public function down(): void
    {
        Schema::table('order_details', fn (Blueprint $table) => $table->dropColumn('included_items_snapshot'));
        Schema::dropIfExists('package_option_inclusions');
    }
};
