<?php

namespace Modules\Myob\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyFile extends Model
{
    use HasFactory;

    protected $table = 'company_files';
    protected $guarded = [];
}
