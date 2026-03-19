<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceRefund extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $table = 'refunds';

    protected $connection = 'mysql';

    protected $fillable = [
        'shopify_order_id',
        'shopify_product_id',
        'shopify_refund_string_id',
        'shopify_refund_id',
        'erply_id',
        'order_number',
        'refund_type',
        'order-type',
        'refund_shipping_amount',
        'restock_flag',
        'order_updated_date',
        'pending_process',
        'credit_invoice_id',
        'shopify_retrived_time',
        'refund_created_exact',
        'currency',
        'refund_reason',
        'total_refunded',
        'Shopify_cursor',

    ];
}
