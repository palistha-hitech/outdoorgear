<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $timeStamp = false;
    protected $table = 'current_product_des';

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
