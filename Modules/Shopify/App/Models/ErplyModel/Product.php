<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\ErplyModel\ProductFactory;


class Product extends Model
{
    use HasFactory;
    // protected $table = 'newsystem_product_matrix';
    protected $table = 'newsystem_product_matrix';
    protected $connection = 'mysql_source';
    protected $timestamp = false;
    protected $fillable = [];
    protected $guarded = [];

    #make relation for variants
    public function variants()
    {
        return $this->hasMany(Variant::class, 'parentProductID', 'productID');
    }

    # relation with category
    public function category()
    {
        return $this->belongsTo(Category::class, 'categoryID', 'parentCategoryID');
    }

    # relation with images
    public function images()
    {
        return $this->hasMany(Images::class, 'parentProductID', 'productID');
    }
    # relation with attribute_categories
    public function attributeCategories()
    {
        return $this->hasOne(CategoryAttribute::class, 'productID', 'erplyID');
    }
}
