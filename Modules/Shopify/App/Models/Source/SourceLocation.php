<?php

namespace Modules\Shopify\App\Models\Source;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\Source\SourceLocationFactory;

class SourceLocation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];
    protected $guarded = [];
    protected $connection = 'mysql';
    protected $table = 'source_locations';
}
