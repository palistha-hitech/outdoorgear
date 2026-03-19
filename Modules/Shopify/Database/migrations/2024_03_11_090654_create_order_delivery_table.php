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
        Schema::create('order_delivery', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_customer_string_id')->nullable();
            $table->string('shopify_customer_id')->nullable();
            $table->string('shopify_order_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable(); 
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->nullable();
            $table->string('zip')->nullable();
            $table->string('defalut_phone')->nullable();
            $table->string('billing_address_first_name')->nullable();
            $table->string('billing_address_last_name')->nullable();
            $table->string('billing_address_email')->nullable();
            $table->string('billing_address_city')->nullable();
            $table->string('billing_address_province')->nullable();
            $table->string('billing_address_country')->nullable();
            $table->string('billing_address_zip')->nullable();
            $table->string('billing_address_phone')->nullable();
            $table->string('shipping_address_first_name')->nullable();
            $table->string('shipping_address_last_name')->nullable();
            $table->string('shipping_address_email')->nullable();
            $table->string('shipping_address_phone')->nullable();
            $table->string('shipping_address_city')->nullable();
            $table->string('shipping_address_province')->nullable();
            $table->string('shipping_address_country')->nullable();
            $table->string('shipping_address_zip')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_delivery');
    }
};