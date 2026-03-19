<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\ErplyModel\OrderFactory;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $connection = 'mysql_source';
    protected $table = 'newsystem_orders';
    public $timestamps = false;
    protected $primaryKey = 'orderID';
    protected $fillable = [  
       'newSystemOrderID',
       'newSystemOrderNumber',
       'newSystemCustomerID',
       'order_created' ,
       'newSystemCustomerEmail',
       'order_completed',
       'currency',
       'order_total' ,
       'order_subtotal',
       'total_items',
       'shipping_methods',
       'total_shipping',
       'payment_detail_title',
       'payment_detail_status',
       'note',
       'taxAmount',
       'coupon_code',
       'coupon_amount',
       'internalPaymentType',
       'fullfillment_status',
       'shopifyCursor',
       'risk_level',
       'pabitra',
       'invoiceSyncCount',
       'orderExported',
       'cacOrderID',
       'authorizationCode',
       'updatedAuthorizationCode',
       'shippitTrackingCode',
       'shippit_synced_dateTime'
    ];
}