<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\Source\SourceOrderProductFactory;

class SourceOrderProduct extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $connection = 'mysql';
    protected $table = 'order_products';
    protected $fillable = [
      'shopify_product_string_id',
        'shopify_product_id',
        'shopify_order_id',
        'product_sku',
        'variant_sku',
        'variant_title',
        'variant_price',
        'quantity',
        'discount_amount',
        'total_discount',
        'color',
        'size',
  ];
    
  
}