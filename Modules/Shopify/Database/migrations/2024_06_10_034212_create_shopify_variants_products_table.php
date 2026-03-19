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
        Schema::create('shopify_variants_products', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_products_string_id')->nullable();
            $table->string('shopify_products_id')->nullable();
            $table->string('shiopifyVariantId')->nullable();
            $table->string('inventoryitemId')->nullable();
            $table->string('title')->nullable();
            $table->string('totalInventory')->nullable();
            $table->string('status')->nullable();
            $table->string('productType')->nullable();
            $table->string('description')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('weightUnit')->nullable();
            $table->string('displayName')->nullable();
            $table->string('price')->nullable();
            $table->string('weight')->nullable();
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
        Schema::dropIfExists('shopify_variants_products');
    }
};