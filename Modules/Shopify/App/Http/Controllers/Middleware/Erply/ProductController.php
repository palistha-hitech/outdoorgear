<?php

namespace Modules\Shopify\App\Http\Controllers\Middleware\Erply;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Shopify\App\Models\ErplyModel\Category;
use Modules\Shopify\App\Models\ErplyModel\CategoryAttribute;
use Modules\Shopify\App\Models\ErplyModel\Product;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Services\ErplyService\ErplyProductService;
use Modules\Shopify\App\Services\SourceService\SourceProductGetService;

class ProductController extends Controller
{
    protected $productService;
    protected $sourceProductService;
    public function __construct(ErplyProductService $productService)
    {
        $this->productService = $productService;
        $this->sourceProductService = new SourceProductGetService();
    }
    public function getProducts(Request $request)
    {
        try {
            $limit = $request->limit ?? 20;
            $code = $request->code ?? '';
            $debug = $request->debug ?? 0;
            $productsDatas = $this->productService->getProducts(['roadhouseStatus' => 1], $limit, $code);


            if (count($productsDatas) <= 0) {
                Product::where('roadhouseStatus', 2)->update(['roadhouseStatus' => 1]);
                return response()->json([
                    'message' => 'no products found',
                ]);
            }
            if ($debug == 1) {
                dd($productsDatas);
            }
            foreach ($productsDatas as $productData) {
                #check if product is duplicate
                $count = Product::where('code', $productData->code)->count();
                if ($count > 1) {
                    echo "duplicate product found for code " . $productData->code . " <br>";
                    $productData->update(['roadhouseStatus' => 4]);
                    $productData = $this->getduplicateProducts($productData);
                }
                # end check if product is duplicate
                $this->productService->updateProducts($productData->productID, ['roadhouseStatus' => 2]);
                # if product is active and displayed in webshop and not deleted from erply but status = 0 on source
                if ($productData->active == 1 && $productData->displayedInWebshop == 1 && $productData->erplyDeleted == 0) {
                    $updateFlag = SourceProduct::where('stockId', $productData->productID)->first();
                    if (@$updateFlag && $updateFlag->status == 0) {
                        $result =  $this->productService->updateProducts($productData->productID, ['roadhouseImageStatus' => 1, 'roadhouseSohStatus' => 1]);
                    }
                }

                #
                $product =   $this->sourceProductService->insertProducts($productData);


                if ($product) {
                    echo "<br/>";

                    echo "product created updated successfully" . "<br>";
                    echo "Product code  = " . $product->handle . "<br>";
                    echo "Product name = " . $product->title;
                }

                #variants
                foreach ($productData->variants as $variant) {

                    $variantResult =   $this->sourceProductService->insertVariants($variant, $product->id);


                    if ($variantResult) {
                        // check soh for all locations create 0 soh for locations whose lovation not created
                        // $allLocations = $this->sourceProductService->getAllLocations();
                        // foreach ($allLocations as $location) {
                        //     $checkLocationSoh = $this->sourceProductService->checkLocationSoh($product->id, $variantResult->id, $location->id);
                        //     if (!$checkLocationSoh) {
                        //         $res1 = $this->sourceProductService->insertSoh($product->id, $variantResult->id, $location->id, 0);
                        //     }
                        // }
                        echo "<br/>";

                        echo "variant created updated successfully";
                        echo "Variant sku = " . $variantResult->sku;
                        echo "Variant barcode = " . $variantResult->barcode;
                    } else {

                        echo "failed to insert the variants";
                        echo "Variant code = " . $variant->code;
                        echo "Variant barcode = " . $variant->code2;
                    }
                }

                #update Erply ptoduct
                $result =  $this->productService->updateProducts($productData->productID, ['roadhouseStatus' => 0, 'roadhouseSohStatus' => 1]);

                echo "<br/>";

                if ($result) {
                    echo "ERply product updated successfully";
                } else {
                    print_r($result);
                    echo "failed to update the ERply product";
                }
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getduplicateProducts($product)
    {

        return Product::query()
            ->where('code', $product->code)
            ->orderBy('lastModified', 'desc')
            ->first();
    }
}
