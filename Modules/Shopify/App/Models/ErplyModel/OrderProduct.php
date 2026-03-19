<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\ErplyModel\OrderProductFactory;

class OrderProduct extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $connection = 'mysql_source';
    protected $table = 'newsystem_orders_product';
    protected $primaryKey = 'orderproductID';
     
    public $timestamps = false;
    protected $fillable = [
        'newSystemOrderID',
        'newSystemProductId',
        'stockCode',
        'actualCIStockCode',
        'colour',
        'size',
        'quantity',
        'unitPrice',
        'discountAmount', 'pabitra',];

}