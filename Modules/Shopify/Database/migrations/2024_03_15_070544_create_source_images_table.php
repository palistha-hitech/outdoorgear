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
        Schema::create('source_images', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pictureID');
            $table->bigInteger('product_id');
            $table->bigInteger('variant_id')->nullable();
            $table->bigInteger('colorID')->nullable();
            $table->string('name')->nullable()->comment('something like 1.jpg');
            $table->string('shopifyMediaId')->nullable();
            $table->string('alt')->nullable();
            $table->tinyInteger('pendingProcess')->default(1);
            $table->tinyInteger('isDeleted')->default(0);
            $table->integer('order')->nullable();
            $table->date('lastsyncDate')->nullable();
            $table->date('pushedDate')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_images');
    }
};
