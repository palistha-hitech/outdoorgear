<?php
namespace Modules\Myob\App\Models;

use Modules\Myob\App\Models\SourceProduct;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceVariant extends Model
{
    use HasFactory;
    protected $connection = 'mysql';
    protected $table      = 'source_variants';
    protected $primaryKey = 'id';

    protected $guarded = [];

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(SourceProduct::class, 'product_id', 'id');
    }

    # has many source images
    // public function images()
    // {
    //     return $this->hasMany(SourceImage::class, 'variant_id', 'id')->where('isDeleted', 0);
    // }

    // public function sourceSoh()
    // {
    //     return $this->hasMany(SourceSoh::class, 'variant_id', 'id');
    // }
}
