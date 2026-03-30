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
        Schema::table('shopify_products', function (Blueprint $table) {
            $table->string('handle')->nullable();
            $table->string('Shopify_added_date', 50)->nullable();
            $table->string('Shopify_updated_date', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_products', function (Blueprint $table) {
            $table->dropColumn('handle');
            $table->dropColumn('Shopify_added_date');
            $table->dropColumn('Shopify_updated_date');
        });
    }
};
