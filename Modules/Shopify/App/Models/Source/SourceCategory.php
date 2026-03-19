<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\Source\SourceCategoryFactory;

class SourceCategory extends Model
{
    use HasFactory;
    protected $connection = 'mysql';

    protected $fillable = [
        'categoryID',
        'categoryPatentId',
        'categoryTitle',
        'image',
        'slug',
        'categoryTags',
        'shopifyPendingProcess',
        'lastSyncDate',
        'lastPushedDate',
        'shopifyCollectionId',
        'shopifyParentId',
        'errorMessage',
    ];
    public function sourceProduct()
    {
        return $this->hasMany(SourceProduct::class);
    }

    public function sourceParentCategory()
    {
        return $this->belongsTo(SourceCategory::class, 'categoryPatentId');
    }

    public function sourceChildrenCategory()
    {
        return $this->hasMany(SourceCategory::class, 'categoryPatentId');
    }
}
