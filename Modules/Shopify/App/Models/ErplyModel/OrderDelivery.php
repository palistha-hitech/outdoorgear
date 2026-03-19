<?php

namespace Modules\Shopify\App\Models\ErplyModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDelivery extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $connection = 'mysql_source';

    protected $table = 'newsystem_order_delivery';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'newSystemMemberID',
        'newSystemOrderID',
        'deliveryFirstName',
        'deliveryLastName',
        'deliveryPhone',
        'deliveryStreet',
        'deliverySuburb',
        'deliveryPostCode',
        'deliveryCountry',
        'deliveryCity',
        'deliveryState',
        'erplyPending',
        'erolyDeliveryID', 'pabitra'];
}
