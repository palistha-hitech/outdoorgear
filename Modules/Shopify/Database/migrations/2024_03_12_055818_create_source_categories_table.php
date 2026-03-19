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
        Schema::create('source_categories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('categoryID')->default(0);
            $table->bigInteger('categoryPatentId')->default(0);
            $table->string('categoryTitle')->nullable();
            $table->string('slug')->nullable();
            $table->string('image')->nullable();

            $table->string('categoryTags')->nullable();
            $table->boolean('shopifyPendingProcess')->default(0)->comment('1 => need to push');
            $table->datetime('lastSyncDate')->nullable();
            $table->datetime('lastPushedDate')->nullable();

            $table->string('shopifyCollectionId')->nullable()->comment('category ShopifyId')->index('shopifyCollectionId');
            $table->string('shopifyParentId')->nullable()->comment('category ShopifyId')->index('shopifyParentId');
            $table->string('errorMessage')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_categories');
    }
};
