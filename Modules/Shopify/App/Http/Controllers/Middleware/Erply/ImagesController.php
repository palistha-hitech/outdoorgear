<?php

namespace Modules\Shopify\App\Http\Controllers\Middleware\Erply;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shopify\App\Models\ErplyModel\Product;
use Modules\Shopify\App\Services\ErplyService\ErplyProductService;
use Modules\Shopify\App\Services\SourceService\SourceProductGetService;

class ImagesController extends Controller
{

    protected $productService;
    protected $sourceProductService;
    public function __construct(ErplyProductService $productService)
    {
        $this->productService = $productService;
        $this->sourceProductService = new SourceProductGetService();
    }

    public function index(Request $request)
    {
        try {
            $code = $request->code ?? '';
            $debug = $request->debug ?? 0;
            $limit = $request->limit ?? 20;
            # get product details from erplay
            if ($code) {
                $products = $this->productService->getImagesProducts(['code' => $code], 1);
            } else {

                $products = $this->productService->getImagesProducts(['roadhouseImageStatus' => 1, 'roadhouseStatus' => 0], $limit);
            }
            if ($debug == 1) {
                dd($products);
            }

            if (count($products) <= 0) {
                info('All images synced successfully !!!');
                // Product::where('roadhouseImageStatus', 3)->update(['roadhouseImageStatus' => 1]);
                exit;
            }

            foreach ($products as $product) {

                $count = Product::where('code', $product->code)->count();
                if ($count > 1) {
                    echo "duplicate product found for code " . $product->code . " <br>";
                    $product->update(['roadhouseImageStatus' => 4]);
                    $product = $this->getduplicateProducts($product);
                }
                #get images details from the erply
                echo "Product ID : " . $product->productID;
                $images =   $product->images()->get();
                $groupedImages = [];
                foreach ($images as $image) {
                    // Group images by colorID
                    $groupedImages[$image->colourID][] = $image;
                }
                #  echo "Images Count : " . count($images);
                if ($debug == 2) {
                    dd($groupedImages);
                }
                #get source product details from module
                $sourceProduct = $this->sourceProductService->getSourceProducts(['stockId' => $product->productID]);
                echo "<br>";



                if ($images && $sourceProduct) {
                    foreach ($images as $image) {
                        $sourceVarientId = null;

                        if ($image->productID) {
                            #get  variant details from erplay
                            $variation = $this->productService->getVariants($image->productID);
                            if ($variation) {
                                # get source variantion details
                                $sourceVarient = $this->sourceProductService->getSourceVariants(['sku' => $variation->code]);

                                if ($sourceVarient) {
                                    $sourceVarientId = $sourceVarient->id;
                                }
                            }
                        }
                        $result =  $this->sourceProductService->insertImages($image, $sourceProduct->id, $sourceVarientId);

                        if ($result) {
                            $this->sourceProductService->updateSourceProduct(['id' => $sourceProduct->id], ['imagePendingProcess' => 1]);
                            echo "<br>";
                            echo $result->name;
                            echo "Image Inserted Successfully";
                            echo "<br>";
                        } else {
                            echo "<br>";
                            echo "Image Not Inserted";
                            echo "<br>";
                        }
                    }

                    $updateData = [
                        'roadhouseImageStatus' => 0

                    ];
                } else {
                    echo "Product Not Found in Source Module or image not found";
                    $updateData = [
                        'roadhouseImageStatus' => 3

                    ];
                }


                $this->productService->updateProducts($product->productID, $updateData);

                echo "Procerss Completed for Product ID : " . $product->productID;
            }
            echo "Whole Process Completed";
        } catch (Exception  $e) {
            dd($e);
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
