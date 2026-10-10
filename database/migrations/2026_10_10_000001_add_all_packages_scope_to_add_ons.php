<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('add_ons', function (Blueprint $table) {
            $table->boolean('all_packages')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('add_ons', fn (Blueprint $table) => $table->dropColumn('all_packages'));
    }
};
