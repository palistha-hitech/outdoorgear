<?php

namespace App\Models\Erply;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErplyProductVariations extends Model
{
    use HasFactory;
    protected $connection = 'mysql_source';
    protected $table = 'newsystem_product_variations';
    protected $primaryKey = 'id';
}