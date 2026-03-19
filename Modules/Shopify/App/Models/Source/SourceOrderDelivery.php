<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\Source\SourceOrderDeliveryFactory;

class SourceOrderDelivery extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $connection = 'mysql';
    protected $table = 'order_delivery';
    protected $fillable = [
    'shopify_customer_string_id',
    'shopify_customer_id',
    'shopify_order_id',
    'first_name',
    'last_name',
    'email',
    'phone',
    'address1',
    'address2',
    'city',
    'province',
    'country',
    'zip',
    'defalut_phone',
    'billing_address_first_name',
    'billing_address_last_name',
    'billing_address_email',
    'billing_address_city',
    'billing_address_province',
    'billing_address_country',
    'billing_address_zip',
    'billing_address_phone',
    'shipping_address_first_name',
    'shipping_address_last_name',
    'shipping_address_email',
    'shipping_address_phone',
    'shipping_address_city',
    'shipping_address_province',
    'shipping_address_country',
    'shipping_address_zip',
    'shipping_address_phone',];
}