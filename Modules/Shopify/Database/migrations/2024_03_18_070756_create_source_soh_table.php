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
        Schema::create('source_soh', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('product_id');
            $table->bigInteger('variant_id');
            $table->bigInteger('location_id');
            $table->bigInteger('currentStock')->nullable();

            $table->date('lastStockUpdate')->nullable();
            $table->date('lastPushedDate')->nullable();
            $table->tinyInteger('pendingProcess')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_soh');
    }
};
