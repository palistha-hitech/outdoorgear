<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\Source\SourceImageFactory;

class SourceImage extends Model
{
    use HasFactory;
    protected $connection = 'mysql';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'pictureID',
        'product_id',
        'variant_id',
        'colorID',
        'name',
        'shopifyMediaId',
        'alt',
        'pendingProcess',
        'isDeleted',
        'order',
        'lastsyncDate',
        'pushedDate'
    ];

    #product relation
    public function product()
    {
        return $this->belongsTo(SourceProduct::class, 'product_id', 'id');
    }

    #relation with variants
    public function variant()
    {
        return $this->belongsTo(SourceVariant::class, 'variant_id', 'id');
    }
}
