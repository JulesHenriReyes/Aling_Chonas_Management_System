<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
        });
        Schema::create('package_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('layers');
            $table->text('included_contents');
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'layers']);
        });
        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('add_on_product', function (Blueprint $table) {
            $table->foreignId('add_on_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['add_on_id', 'product_id']);
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->foreignId('package_option_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('product_name_snapshot')->nullable();
            $table->text('included_contents_snapshot')->nullable();
        });
        Schema::create('order_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained()->restrictOnDelete();
            $table->string('name_snapshot');
            $table->text('description_snapshot');
            // Quantity is the total extras for this line, not extras per cake.
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();
            $table->unique(['order_detail_id', 'add_on_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('private_token', 64)->nullable()->unique();
            $table->dateTime('ready_at')->nullable();
            $table->string('cancellation_kind', 32)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('fixed_catalog_pricing')->default(false);
        });
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('file_path');
            $table->string('reference_number', 100);
            $table->string('status', 24)->default('awaiting_verification');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        // A separate reference registry enforces uniqueness without rejecting
        // legacy databases that may already contain duplicate references.
        Schema::create('gcash_references', function (Blueprint $table) {
            $table->string('reference_number', 100)->primary();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
        });
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
        });
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('account_name')->nullable();
            $table->string('account_number', 32)->nullable();
            $table->string('qr_path')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Preserve historical amounts and layers. Never invent package contents
        // or prices for unconfigured catalog options during an upgrade.
        DB::table('order_details')->orderBy('id')->chunkById(200, function ($details) {
            foreach ($details as $detail) {
                DB::table('order_details')->where('id', $detail->id)->update([
                    'product_name_snapshot' => DB::table('products')->where('id', $detail->product_id)->value('product_name'),
                ]);
            }
        });
        DB::table('orders')->where('status', 'cancelled')->update(['cancellation_kind' => 'customer']);
        DB::table('orders')->whereNull('user_id')->orderBy('id')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                DB::table('orders')->where('id', $order->id)->update(['private_token' => bin2hex(random_bytes(32))]);
            }
        });
        DB::table('payments')->where('payment_method', 'gcash')->whereNotNull('reference_number')
            ->orderBy('id')->chunkById(200, function ($payments) {
                foreach ($payments as $payment) {
                    $reference = strtoupper(preg_replace('/\s+/', '', $payment->reference_number));
                    if ($reference !== '') {
                        DB::table('gcash_references')->insertOrIgnore([
                            'reference_number' => $reference, 'payment_id' => $payment->id,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('gcash_references');
        Schema::dropIfExists('payment_proofs');
        Schema::table('orders', fn (Blueprint $table) => $table->dropUnique(['private_token']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn([
            'private_token', 'ready_at', 'cancellation_kind', 'cancellation_reason', 'fixed_catalog_pricing',
        ]));
        Schema::dropIfExists('order_add_ons');
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_option_id');
            $table->dropColumn(['product_name_snapshot', 'included_contents_snapshot']);
        });
        Schema::dropIfExists('add_on_product');
        Schema::dropIfExists('add_ons');
        Schema::dropIfExists('package_options');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['description', 'photo_path']));
    }
};
