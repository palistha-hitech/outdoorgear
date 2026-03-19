<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\ErplyModel\RefundFactory;

class Refund extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $connection = 'mysql_source';
    protected $table = 'newsystem_refunds';
    public $timestamps = false;
    protected $primaryKey = 'refund_id';
    protected $fillable = [];
    protected $guarded = [];
//      [
//     'shopifyRefundString',
//     'restockFlag' , 
//     'orderUpdateDate',
//     'orderUpdateZtime',
//     'refund_amount' ,
//     'refund_type' , 
//     'refund_product_id',
//     'refund_product_code', 
//     'refund_product_price', 
//     'refund_shipping_amount', 
//     'refund_product_qty', 
//     'refund_reason', 
//     'shopifyRetrievedTime', 
//     'branchCode',
//     'newsystemRefundId', 
//     'newSystemOrderNumber', 
//     'newsystemOrderId' ,
//     'pabitra',
// ];
    
}