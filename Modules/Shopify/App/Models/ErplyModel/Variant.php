<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Variant extends Model
{
    use HasFactory;
    protected $table = 'newsystem_product_variations';
    protected $connection = 'mysql_source';
    protected $timestamp = false;
    protected $fillable = [];

    # make relation for products
    public function product()
    {
        return $this->belongsTo(Product::class, 'parentProductID', 'productID');
    }
}
