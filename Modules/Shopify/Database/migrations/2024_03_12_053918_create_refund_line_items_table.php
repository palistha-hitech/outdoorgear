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
        Schema::create('refund_line_items', function (Blueprint $table) {
            $table->id();
            $table->BigInteger('shopify_refund_id');
            $table->string('shopify_product_string_id');
            $table->BigInteger('shopify_product_id');   
            $table->integer('product_quantity');
            $table->string('product_code');
            $table->decimal('product_price');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_line_items');
    }
};