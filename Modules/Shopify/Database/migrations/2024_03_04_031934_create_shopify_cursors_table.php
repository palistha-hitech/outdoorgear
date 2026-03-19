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

        Schema::create('shopify_cursors', function (Blueprint $table) {
            $table->id();
            $table->string("clientCode")->nullable();
            $table->string("cursorName")->nullable();
            $table->string("cursor")->nullable();
            $table->tinyInteger("isLive")->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_cursors');
    }
};
