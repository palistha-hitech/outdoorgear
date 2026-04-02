<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
    {
        Schema::table('source_variants', function (Blueprint $table) {
            $table->boolean('myobsohpendingprocess')
                  ->default(false)
                  ->after('quantityOnHand'); // adjust column position if needed
        });
    }

    public function down()
    {
        Schema::table('source_variants', function (Blueprint $table) {
            $table->dropColumn('myobsohpendingprocess');
        });
    }
};
