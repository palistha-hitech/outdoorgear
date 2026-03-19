<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;
    protected $table = 'newsystem_product_categories';
    protected $connection = 'mysql_source';
    protected $timestamp = false;
    protected $fillable = ['roadhouseStatus'];

    # relation with product
    public function products()
    {
        return $this->hasMany(Product::class, 'categoryID', 'parentCategoryID');
    }

    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parentCategoryID', 'productCategoryID');
    }
    public function childrenCategory()
    {
        return $this->hasMany(Category::class,  'parentCategoryID', 'productCategoryID');
    }

    public function getFullSlug()
    {
        // Build slug for current item
        // $slug = \Str::slug($this->productCategoryName);
        $slug = $this->productCategoryName;

        // If there is a parent, prepend parent's slug
        if ($this->parentCategory) {
            return $this->parentCategory->getFullSlug() . '-' . $slug;
        }

        return $slug;
    }
}
