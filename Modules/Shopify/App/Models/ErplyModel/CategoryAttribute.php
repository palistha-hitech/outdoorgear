<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class CategoryAttribute extends Model
{
    use HasFactory;
    protected $table = 'attribute_categories';
    protected $connection = 'mysql_source';
    protected $timestamp = false;
    protected $fillable = [];
    protected $guarded = [];

    #has relation with products
    public function productCategories()
    {
        return $this->hasMany(Category::class, 'erplyID', 'productID');
    }
}
