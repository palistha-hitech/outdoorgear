<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\Source\SourceRefundLineItemFactory;

class SourceRefundLineItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $connection = 'mysql';
    protected $table = 'refund_line_items';
    #public $timestamps = false;
    protected $fillable = ['shopify_refund_id',
    'shopify_product_string_id',
                'shopify_product_id',
                'product_quantity',
                'product_code',
                'product_price'
];
    
    
}