<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $connection = 'mysql_source';
    protected $timeStamp = false;
    protected $table = 'newsystem_product_categories';
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
