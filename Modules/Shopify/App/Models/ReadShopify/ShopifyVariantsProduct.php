<?php

namespace Modules\Shopify\App\Models\ReadShopify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\ReadShopify\ShopifyVariantsProductFactory;

class ShopifyVariantsProduct extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $table = 'shopify_variants_products';
    protected $fillable = [
        'shopify_products_string_id', 'shopify_products_id', 'shiopifyVariantId',
        'inventoryitemId', 'title', 'totalInventory', 'totalVariants', 'status',
        'vendor', 'productType', 'description', 'tags', 'sku', 'barcode', 'weightUnit', 'displayName',
        'price', 'color', 'size', 'weight'

    ];
}
