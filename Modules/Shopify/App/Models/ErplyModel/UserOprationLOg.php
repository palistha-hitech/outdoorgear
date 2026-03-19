<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserOprationLOg extends Model
{
    use HasFactory;
    protected $table = 'newsystem_user_operation_logs';
    protected $connection = 'mysql_source';
    protected $timestamp = false;
    protected $guarded = [];
}
