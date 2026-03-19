<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Myob\Services\InventoryService;
use Illuminate\Http\Request;

class MyobController extends Controller
{
      protected $service;

    public function __construct(InventoryService $inventoryService)
    {
        $this->service = $inventoryService;
    }
    public function getInventoryItems(Request $req){

        return $this->service->getItems($req);
    }

    // public function getProducts()
    // {
    //     $companyUID = "2b0003ff-142f-4e59-9f72-04f4a040c04f";
        
    //     return $this->inventoryService->getProducts($companyUID);
    // }
}
