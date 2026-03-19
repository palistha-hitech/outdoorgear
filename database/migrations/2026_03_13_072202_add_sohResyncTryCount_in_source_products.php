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
        Schema::table('source_products', function (Blueprint $table) {
          
            $table->tinyInteger('sohResyncTryCount')->nullable();
            $table->tinyInteger('emailSentCount')->nullable();
        });
  
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('source_products', function (Blueprint $table) {
            $table->dropColumn('sohResyncTryCount');
            $table->dropColumn('emailSentCount');
        });
   
    }
};
