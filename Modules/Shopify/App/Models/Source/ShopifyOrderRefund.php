<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\Source\ShopifyOrderRefundFactory;

class ShopifyOrderRefund extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $connection = 'mysql_source';
    protected $table = 'newsystem_refunds';
    protected  $primaryKey = 'refund_id';

    protected $fillable = [];

    protected $guarded = [];
    public  $timestamps = false;

}
