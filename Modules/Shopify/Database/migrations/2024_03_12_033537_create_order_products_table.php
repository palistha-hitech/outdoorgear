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
        Schema::create('order_products', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_product_string_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_order_id')->nullable();
            $table->string('product_sku')->nullable();
            $table->bigInteger('quantity');
            $table->string('variant_sku')->nullable();
            $table->string('variant_title')->nullable();
            $table->decimal('discount_amount', 8, 2)->default(0);
            $table->decimal('total_discount', 8, 2)->default(0);
            $table->decimal('variant_price', 8, 2)->nullable();
            $table->string('color')->nullable(); 
            $table->string('size')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_products');
    }
};