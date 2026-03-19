<?php

namespace Modules\Shopify\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\ShopifyCursorFactory;

class ShopifyCursor extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
   
    protected  $guarded = [];
    protected $connection = 'mysql';
   # public $timestamps = false;
    protected $table = 'shopify_cursors';
    protected $fillable = ['clientCode','cursorName','cursor','isLive'];
}