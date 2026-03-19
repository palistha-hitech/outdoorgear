<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shopify\App\Models\Source\SourceProduct;

class SourceVariant extends Model
{
    use HasFactory;
    protected $connection = 'mysql';
    protected $table = 'source_variants';
    protected $primaryKey = 'id';

    protected $fillable = [
        'product_id',
        'variantId',
        'sku',
        'barcode',
        'image',
        'weight',
        'weightUnit',
        'price',
        'priceWithTax', 
        'displayInWebshop',//add
        'status',//add
        'compareAtPrice',
        'color',
        'colorID',
        'size',
        'colorOrder',
        'sizeOrder',
        'shopifyPendingProcess',
        'sohPendingProcess',
        'pricePendingProcess',
        'shopifyVariantId',
        'inventoryItemId',
    ];

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(SourceProduct::class, 'product_id', 'id');
    }

    # has many source images
    public function images()
    {
        return $this->hasMany(SourceImage::class, 'variant_id', 'id')->where('isDeleted', 0);
    }

    public function sourceSoh()
    {
        return $this->hasMany(SourceSoh::class, 'variant_id', 'id');
    }
}
