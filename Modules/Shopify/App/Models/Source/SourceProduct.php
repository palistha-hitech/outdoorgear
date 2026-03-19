<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shopify\App\Models\Source\SourceCategorie;
use Modules\Shopify\App\Models\Source\SourceCategory;
use Modules\Shopify\App\Models\Source\SourceVariant;

class SourceProduct extends Model
{
    use HasFactory;
    protected $table = 'source_products';
    // protected $connection = 'mysql';
    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];
    // public function sourceCategory(): BelongsTo
    // {
    //     return $this->belongsTo(SourceCategory::class);
    // }
    public function variants(): HasMany
    {
        return $this->hasMany(SourceVariant::class, 'product_id', 'id');
    }

    public function sohs()
    {
        return $this->hasMany(SourceSoh::class, 'product_id', 'id');
    }

    #image relation
    // public function images(): HasMany
    // {
    //     return $this->hasMany(SourceImage::class, 'product_id', 'id');
    // }

    // public function sohs()
    // {
    //     return $this->hasMany(SourceSoh::class, 'product_id', 'stockId');
    // }
}
