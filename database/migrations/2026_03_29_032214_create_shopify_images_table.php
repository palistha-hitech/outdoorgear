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
      Schema::create('shopify_images', function (Blueprint $table) {
            $table->id(); // bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->string('imageId')->nullable();
            $table->string('productId')->nullable();
            $table->string('product_string_id', 100);
            $table->string('name')->nullable();
            $table->string('shopifyMediaId')->nullable();
            $table->string('url')->nullable();

            $table->timestamps(); // created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_images');
    }
};
