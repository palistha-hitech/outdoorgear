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
        Schema::create('source_variants', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('varinatId')->unsigned();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();

            $table->string('image')->nullable();
            $table->string('weight')->nullable();
            $table->string('weightUnit')->nullable()->default('KILOGRAMS');
            $table->string('price')->nullable();
            $table->string('priceWithTax')->nullable();

            $table->string('compareAtPrice')->nullable();
            $table->string('color')->nullable();
            $table->string('size')->nullable();

            $table->bigInteger('colorOrder')->nullable();
            $table->bigInteger('sizeOrder')->nullable();
            $table->boolean('shopifyPendingProcess')->default(0)->comment('1 => need to push');

            # flags for product
            $table->tinyInteger('sohPendingProcess')->default(0);
            $table->tinyInteger('pricePendingProcess')->default(0);

            $table->string('shopifyVariantId')->nullable()->comment('variant ShopifyId')->index('shopifyVariantId');

            $table->string('inventoryItemId')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_variants');
    }
};
