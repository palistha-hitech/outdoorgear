<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WareHouseLocation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'newsystem_warehouse_locations';
    protected $connection = 'mysql_source';
    protected $timestamp = false;
}
