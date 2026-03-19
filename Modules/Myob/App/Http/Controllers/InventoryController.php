<?php
namespace Modules\Myob\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Myob\App\Http\Controllers\Myob\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{

    protected $service;

    public function __construct(InventoryService $inventoryService)
    {
        $this->service = $inventoryService;
    }

    public function getMatrixProducts(Request $req)
    {
        // dd(" hello ");
        return $this->service->getMatrixProducts($req);
    }

}
