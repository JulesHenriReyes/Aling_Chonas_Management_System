<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'completed_at']);
            $table->index(['status', 'cancelled_at']);
        });
        Schema::table('refunds', fn (Blueprint $table) => $table->index(['status', 'completed_at']));
    }
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'completed_at']); $table->dropIndex(['status', 'cancelled_at']);
        });
        Schema::table('refunds', fn (Blueprint $table) => $table->dropIndex(['status', 'completed_at']));
    }
};
