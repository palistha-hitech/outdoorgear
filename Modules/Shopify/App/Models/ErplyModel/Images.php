<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Images extends Model
{
    use HasFactory;
    protected $table = 'product_picture_cdn';
    protected $connection = 'mysql_source';
    protected $timestamp = false;
    protected $fillable = [];
    protected $guarded = [];


    public function product()
    {
        return $this->belongsTo(Product::class, 'productID', 'productID');
    }
}
