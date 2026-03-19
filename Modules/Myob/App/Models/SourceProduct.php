<?php
namespace Modules\Myob\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceProduct extends Model
{
    use HasFactory;

    protected $table   = 'source_products';
    protected $guarded = [];
//    public function variants(): HasMany
//     {
//         return $this->hasMany(SourceVariant::class, 'product_id', 'id');
//     }

}
