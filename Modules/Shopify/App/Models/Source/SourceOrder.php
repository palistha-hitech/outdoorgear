<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\Source\SourceOrderFactory;

class SourceOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $connection = 'mysql';
    protected $table = 'orders';

    protected $fillable = [
        'shopify_order_string_id',
        'shopify_order_id',
        'invoice_sync_count',
        'invoice_sync_date',
        'invoice_failed_reason',
        'invoice_pending',
        'is_invoice_fulfilled',
        'shpify_order_number',
        'total_order',
        'subtotal_order',
        'total_items',
        'order_created',
        'order_completed',
        'order_file',
        'order_sync_date',
        'order_type',
        'tax_amount',
        'shipping_method',
        'risk_level',
        'risk_level_log',
        'shippit_tracking_code',
        'shippit_synced_dateTime',
        'fullfillment_status',
        'currency',
        'fullfillment_status',
        'shippit_status_retrieval_count',
        'order_ignore',
        'box_ready_for_shippit',
        'order_status',
        'total_shipping',
        'note',
        'view_order_url',
        'coupon_code',
        'coupon_amount',
        'pending_order_process_time',
        'payment_detail_status',
        'payment_method',
        'shiopifyCursor',
    ];
}
