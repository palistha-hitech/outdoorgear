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
        Schema::create('shopify_products', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_products_string_id')->nullable();
            $table->string('shopify_products_id')->nullable();
            $table->string('title')->nullable();
            $table->string('totalInventory')->nullable();
            $table->string('totalVariants')->nullable();
            $table->string('status')->nullable();
            $table->string('vendor')->nullable();
            $table->string('productType')->nullable();
            $table->string('description')->nullable();
            $table->string('tags')->nullable();
            $table->string('images')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_products');
    }
};
