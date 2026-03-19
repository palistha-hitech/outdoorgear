<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shopify\Database\factories\ErplyModel\CustomerFactory;

class Customer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */

    protected $table = 'newsystem_customers';
    protected $connection = 'mysql_source';
    protected $primaryKey = 'middleCustomerID';
    public $timestamps = false;

    protected $fillable = ['middleCustomerID','newSystemMemberID','ciMemberID','ciCustomerCode','emailAddress','firstName','lastName',
    'tradingName','	homePhone','mobile','fax','	street','city','postCode','country','state','pending','middleEntryDate','ciEntryDate',
'erplyCustomerID','erplyPending','erplyAddressPending','erplyAddressID','pabitra'];


}
