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
        Schema::create('source_products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('stockId')->unique()->comment('Product StockId')->index();
            $table->bigInteger('category_id')->nullable();
            $table->string('handle')->comment('product stockCode')->index('handle');

            $table->string('productType')->nullable();
            $table->string('vendor')->nullable();
            $table->string('productTags')->nullable();
            $table->string('brand')->nullable();

            $table->string('title')->nullable();

            $table->text('descriptionHtml')->nullable();
            $table->boolean('status')->default(1)->comment('1 => active');
            $table->string('mainImage')->nullable();
            # flags for product
            $table->enum('isMatrix', [0, 1])->default('0');
            $table->boolean('shopifyPendingProcess')->default(0)->comment('1 => need to push');
            $table->tinyInteger('sohPendingProcess')->default(0);
            $table->tinyInteger('pricePendingProcess')->default(0);
            $table->tinyInteger('imagePendingProcess')->default(0);
            $table->tinyInteger('varinatsAppendPending')->default(0);

            # shopify details
            $table->datetime('lastSyncDate')->nullable();
            $table->datetime('lastPushedDate')->nullable();
            $table->string('shopifyProductId')->nullable()->comment('product ShopifyId')->index('shopifyProductId');
            $table->string('errorMessage')->nullable();

            $table->datetime('sourceUpdatedDate')->nullable();
            $table->datetime('sourceAddedDate')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_products');
    }
};
