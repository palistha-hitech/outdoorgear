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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shopify_order_string_id')->nullable();
            $table->bigInteger('shopify_order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedInteger('invoice_sync_count')->default(0);
            $table->dateTime('invoice_sync_date')->nullable();
            $table->string('invoice_failed_reason')->nullable();
            $table->integer('invoice_pending')->default(1); #1 - pending, 2 - failed, 3 - success
            $table->string('shpify_order_number')->nullable();
            $table->decimal('total_order',6,2)->nullable();
            $table->decimal('subtotal_order',8,2)->nullable();
            $table->unsignedInteger('total_items')->nullable();
            $table->dateTime('order_created')->nullable();
            $table->dateTime('order_completed')->nullable();
            $table->string('order_file')->nullable();
            $table->dateTime('order_sync_date')->nullable();
            $table->string('order_type')->default('shopify');
            $table->float('tax_amount')->nullable();
            $table->string('shipping_method')->nullable();
            $table->string('risk_level')->default(0); //'0-Not Set,1- Low,2 - Medium,3 - High, 4 - Other',
            $table->string('risk_level_log')->nullable();
            $table->string('shippit_tracking_code')->nullable();
            $table->dateTime('shippit_synced_dateTime')->nullable();
            $table->string('fullfillment_status')->nullable();
            $table->string('currency')->nullable();
            $table->unsignedInteger('shippit_status_retrieval_count')->default(0);
            $table->unsignedTinyInteger('order_ignore')->default(0);
            $table->unsignedTinyInteger('box_ready_for_shippit')->default(0);
            $table->string('order_status')->nullable();
            $table->decimal('total_shipping', 8, 2)->nullable();
            $table->text('note')->nullable();
            $table->string('view_order_url')->nullable();
            $table->string('coupon_code')->nullable();
            $table->string('coupon_amount')->nullable();
            $table->dateTime('pending_order_process_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};