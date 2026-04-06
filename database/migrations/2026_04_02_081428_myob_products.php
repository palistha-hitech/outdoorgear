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
        Schema::create('myob_products', function (Blueprint $table) {
            $table->id(); // bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->unsignedBigInteger('erplyID')->nullable();
            $table->text('all')->nullable();

            $table->string('type')->nullable();
            $table->string('status')->nullable();

            $table->dateTime('lastModified')->nullable();

            $table->timestamps(); // created_at & updated_at
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
     Schema::dropIfExists('myob_products');
    }
};
