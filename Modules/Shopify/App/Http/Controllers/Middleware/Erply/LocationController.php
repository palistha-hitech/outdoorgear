<?php

namespace Modules\Shopify\App\Http\Controllers\Middleware\Erply;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shopify\App\Services\ErplyService\ErplyProductService;
use Modules\Shopify\App\Services\SourceService\SourceProductGetService;

class LocationController extends Controller
{



    protected $productService;
    protected $sourceProductService;
    public function __construct(ErplyProductService $productService)
    {
        $this->productService = $productService;
        $this->sourceProductService = new SourceProductGetService();
    }
    public function getLocation()
    {

        $locations = $this->productService->getLocations();

        foreach ($locations as $location) {

            $locationResult = $this->sourceProductService->insertLocation($location);
            echo "<br>";
            if ($locationResult) {

                echo "inserted";
            } else {

                echo "not inserted";
            }
            
        }
    }
}
