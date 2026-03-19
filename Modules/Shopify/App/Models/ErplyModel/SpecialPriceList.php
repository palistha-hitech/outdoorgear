<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SpecialPriceList extends Model
{
    use HasFactory;

    protected $table = 'newsystem_pricelist_special';
    protected $connection = 'mysql_source';
    protected $timestamp = false;
    protected $fillable = [];
    protected $guarded = [];
}
