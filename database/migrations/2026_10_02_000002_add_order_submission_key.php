<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->string('submission_key', 64)->nullable()->unique());
    }
    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('submission_key'));
    }
};
