<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Shopify\Database\factories\GiftCardFactory;

class GiftCard extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $table = 'newsystem_giftcards';
    protected $connection = 'mysql_source';
    protected bool $timestamp = false;
}
