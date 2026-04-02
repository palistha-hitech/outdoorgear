<?php

namespace Modules\Shopify\App\Models\ReadShopify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\ReadShopify\ShopifyProductFactory;

class ShopifyProduct extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'shopify_products_string_id', 'shopify_products_id',
        'title', 'totalInventory', 'totalVariants', 'status', 'vendor', 'productType',
        'description', 'tags', 'handle', 'Shopify_added_date', 'Shopify_updated_date',

    ];
}
