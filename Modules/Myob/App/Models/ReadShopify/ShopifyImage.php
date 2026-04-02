<?php

namespace Modules\Shopify\App\Models\ReadShopify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\ReadShopify\ShopifyImageFactory;

class ShopifyImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['imageId', 'productId', 'name', 'shopifyMediaId', 'url', 'product_string_id'];
}
