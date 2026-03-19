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
        Schema::create('source_locations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('warehouseID');
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('address2')->nullable();

            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('zipCode')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('bankAccountNumber')->nullable();
            $table->string('timeZone')->nullable();

            $table->string('shopifyLocationId')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_locations');
    }
};
