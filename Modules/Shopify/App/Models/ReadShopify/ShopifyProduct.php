<?php
namespace Modules\Shopify\App\Models\ReadShopify;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyProduct extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];
    protected $table   = 'shopify_products';
    protected $fillable = [
        'shopify_products_string_id', 'shopify_products_id',
        'title', 'totalInventory', 'totalVariants', 'status', 'vendor', 'productType',
        'description', 'tags', 'handle', 'Shopify_added_date', 'Shopify_updated_date',

    ];
}
