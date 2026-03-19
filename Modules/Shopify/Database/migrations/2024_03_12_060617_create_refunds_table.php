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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shopify_order_id')->nullable();
            $table->bigInteger('shopify_product_id')->nullable();
            $table->string('shopify_refund_string_id')->nullable();
            $table->bigInteger('shopify_refund_id')->nullable();
            $table->integer('order_number')->nullable();
            $table->decimal('total_refunded', 10, 2); # Sum of all refund_line_items amounts for this refund
            $table->string('currency'); #Currency of the refunded amount
            $table->string('refund_type')->nullable();
            $table->text('refund_reason')->nullable(); #Reason for the refund, if provided
            $table->string('order_type')->nullable();
            $table->decimal('refund_shiiping_amount',6,2)->nullable();
            $table->boolean('restock_flag')->default(1);
            $table->timestamp('order_updated_date')->nullable();
            $table->boolean('pending_process')->default(1);
            $table->unsignedBigInteger('erply_id')->nullable();
            $table->integer('erply_credit_invoice_id')->nullable();
            $table->timestamp('shopify_retrived_time')->nullable();
            $table->timestamp('refund_created_exact')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};