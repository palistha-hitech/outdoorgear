<?php

namespace Modules\Shopify\App\Http\Controllers\Middleware\Erply;

use App\Http\Controllers\Controller;
use App\Models\Products\Product;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shopify\App\Models\ErplyModel\Product as ErplyModelProduct;
use Modules\Shopify\App\Services\ErplyService\ErplyProductService;
use Modules\Shopify\App\Services\SourceService\SourceProductGetService;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Models\Source\SourceLocation;

class SohController extends Controller
{

    protected $productService;
    protected $sourceProductService;
    public function __construct(ErplyProductService $productService)
    {
        $this->productService = $productService;
        $this->sourceProductService = new SourceProductGetService();
    }

    public function indexBackup(Request $request)
    {
        try {
            $code = $request->code ?? '';
            $limit = $request->limit ?? 50;
            # get product details from erplay
            $products = $this->productService->getProducts(['roadhouseSohStatus' => 1], $limit, $code);
            foreach ($products as $product) {
                $count = ErplyModelProduct::where('code', $product->code)->count();
                if ($count > 1) {
                    echo "duplicate product found for code " . $product->code . " <br>";
                    $product->update(['roadhouseSohStatus' => 4]);
                    $product = $this->getduplicateProducts($product);
                }
                #get images details from the erply
                echo "Product ID : " . $product->productID;
                $Variants =   $this->productService->getAllVariants($product->productID);
                echo "Variants Count : " . count($Variants);
                #get source product details from module
                $sourceProduct = $this->sourceProductService->getSourceProducts(['stockId' => $product->productID]);
                echo "<br>";
                if ($Variants && $sourceProduct) {
                    $flag = 0;
                    foreach ($Variants as $Variant) {
                        echo "Variants = " . $Variant->code;
                        echo "<br>";
                        $sourceVarientId = null;
                        if ($Variant->productID) {
                            #get  variant details from erplay
                            $variationSohs = $this->productService->getVariantSoh($Variant->productID);
                            # dump($variationSohs);
                            if ($variationSohs) {
                                foreach ($variationSohs as $variationSoh) {
                                    # get source variantion details
                                    $sourceVarient = $this->sourceProductService->getSourceVariants(['sku' => $Variant->code, 'variantId' => $Variant->productID]);
                                    #    dump($sourceVarient);
                                    if ($sourceVarient) {

                                        $sourceVarientId = $sourceVarient->id;
                                    } else {
                                        ErplyModelProduct::where('productID', $product->productID)->update([
                                            'roadhouseStatus' => 1
                                        ]);
                                        $this->sourceProductService->updateSourceProduct(['id' => $sourceProduct->id], ['sohPendingProcess' => 0]);
                                        continue;
                                    }

                                    $locationId = $this->sourceProductService->getLocationsById($variationSoh->erplyWarehouseID);
                                    $result =  $this->sourceProductService->insertSoh(
                                        $sourceProduct->id,
                                        $sourceVarientId,
                                        $locationId->id,
                                        $variationSoh->erplyCurrentStockValue
                                    );

                                    # dump($result);
                                    if ($result) {
                                        $this->sourceProductService->updateSourceProduct(['id' => $sourceProduct->id], ['sohPendingProcess' => 1]);

                                        echo "<br>";
                                        echo "Total Stock :" . $result->currentStock;
                                        echo "Soh Inserted Successfully";
                                        echo "<br>";
                                        $flag = 1;
                                    } else {
                                        echo "<br>";
                                        echo "Soh Not Inserted";
                                        echo "<br>";
                                    }
                                }
                            } else {
                                echo
                                "soh noty found";
                            }
                        }
                    }
                    if ($flag == 1) {
                        $sourceProductUpadte = [
                            'sohPendingProcess' => 1,
                            'lastSyncDate' => date('Y-m-d H:i:s')
                        ];
                    } else {
                        $sourceProductUpadte = [
                            'sohPendingProcess' => 0,
                            'lastSyncDate' => date('Y-m-d H:i:s')
                        ];
                    }

                    $this->sourceProductService->updateSourceProduct(['id' => $sourceProduct->id], $sourceProductUpadte);
                    $updateData = [
                        'roadhouseSohStatus' => 0

                    ];
                } else {
                    echo "Product Not Found in Source Module or soh not found";
                    $updateData = [
                        'roadhouseSohStatus' => 3

                    ];
                }


                $this->productService->updateProducts($product->productID, $updateData);

                echo "Procerss Completed for Product ID : " . $product->productID;
            }
            echo "Whole Process Completed";
        } catch (Exception $e) {
            $this->productService->updateProducts($product->productID, ['roadhouseStatus' => 1]);

            echo "Something went wrong";
            #dd($e);
            return $e->getMessage();
        }
    }

    public function index(Request $request)
    {
        try {
            // dd($request->all());
            // die('temporary disabled');
           $code = $request->code ?? '';

            $limit = $request->limit ?? 50;
            
            # Get default location for SOH insertion
            // $defaultLocation = SourceLocation::first();
            // if (!$defaultLocation) {
            //     echo "No locations found in source_locations table. Please setup locations first.";
            //     return;
            // }
            
       $whereCondition = ['outdoorGearSohStatus' => 1];
            $products = SourceProduct::with(['variants'])
                // ->where('outdoorGearSohStatus', 1)
                ->when($code != '', function ($query) use ($code) {
                    $query->where('code', $code);
                })->when($code == '', function ($query) use ($whereCondition) {
                    $query->where($whereCondition);
                })
                // ->orderBy('lastModified', 'desc')
                ->limit($limit)
                ->get();
dump($products);
            foreach ($products as $product) {
                // Check for duplicates (though this shouldn't happen with Source products)
                $count = SourceProduct::where('code', $product->code)->count();
                if ($count > 1) {
                    echo "Duplicate product found for code " . $product->code . " <br>";
                    $product->update(['outdoorGearSohStatus' => 4]);
                    continue; // Skip to next product
                }
                
                echo "Product ID : " . $product->id . "<br><br>";
                $variants = $product->variants; // Use the relationship
                // dd($variants);
                echo "Variants Count : " . count($variants) . "<br>";

                if ($product->isMatrix == 1) {
                    // Matrix product - process each variant
                    if ($variants->isNotEmpty()) {
                        $flag = 0;
                        foreach ($variants as $variant) {
                            // dd($variant);
                            echo "<br><br>Variant SKU = " . $variant->sku . "<br>";
                            
                            # Get quantityOnHand from variant directly
                            $quantityOnHand = $variant->quantityOnHand;
                           
                            // if ($quantityOnHand !== null && $quantityOnHand > 0) {
                            if ($quantityOnHand !== null){
                                $result = $this->sourceProductService->insertSoh(
                                    $product->id,
                                    $variant->id,
                                  null,
                                    $quantityOnHand // Use quantityOnHand from source_variants
                                );

                                if ($result) {
                                    $this->sourceProductService->updateSourceProduct(['id' => $product->id], ['sohPendingProcess' => 1, 'sohResyncTryCount' => 0, 'emailSentCount' => 0]);
                                    echo "Total Stock : " . $result->currentStock . "<br>";
                                    echo "Message : Soh Inserted Successfully";
                                    echo "<br>";
                                    $flag = 1;
                                } else {
                                    echo "Message : Soh Not Inserted";
                                    echo "<br>";
                                }
                            } else {
                                echo "No quantityOnHand found for this variant or quantity is 0!!!";
                            }
                        }
                        
                        if ($flag == 1) {
                            $sourceProductUpdate = [
                                'sohPendingProcess' => 1,
                                'lastSyncDate' => date('Y-m-d H:i:s')
                            ];
                            $updateData = [
                                'outdoorGearSohStatus' => 0
                            ];
                        } else {
                            $sourceProductUpdate = [
                                'sohPendingProcess' => 0,
                                'lastSyncDate' => date('Y-m-d H:i:s')
                            ];
                            $updateData = [
                                'outdoorGearSohStatus' => 3
                            ];
                        }
                       
                        $this->sourceProductService->updateSourceProduct(['id' => $product->id], $sourceProductUpdate);
                    } else {
                        echo "No variants found for product !!!";
                        $updateData = [
                            'outdoorGearSohStatus' => 3
                        ];
                    }
                } else {
                    // Simple product - use product_id directly, variant_id = 0
                    # Get quantityOnHand from product directly
                    $quantityOnHand = $product->quantityOnHand;
                    
                    if ($quantityOnHand !== null && $quantityOnHand > 0) {
                        $result = $this->sourceProductService->insertSoh(
                            $product->id,
                            0, // variant_id = 0 for simple products
                           null,
                            $quantityOnHand // Use quantityOnHand from source_products
                        );

                        if ($result) {
                            $flag = 1;
                            echo "<br>";
                            echo "Total Stock :" . $result->currentStock;
                            echo "Soh Inserted Successfully";
                            echo "<br>";
                        } else {
                            echo "<br>";
                            echo "Soh Not Inserted";
                            echo "<br>";
                        }
                    } else {
                        echo "No quantityOnHand found for this product or quantity is 0!!!";
                        $flag = 0;
                    }
                    
                    if ($flag == 1) {
                        $update_soh_data = [
                            'sohPendingProcess' => 1
                        ];
                        $updateData = [
                            'outdoorGearSohStatus' => 0
                        ];
                    } else {
                        $update_soh_data = [
                            'sohPendingProcess' => 0
                        ];
                        $updateData = [
                            'outdoorGearSohStatus' => 3
                        ];
                    }
                    $this->sourceProductService->updateSourceProduct(['id' => $product->id], $update_soh_data);
                }

                if (isset($updateData) && !empty($updateData)) {
                    $product->update($updateData);
                }

                echo "Process Completed for Product ID : " . $product->id . "<br>";
            }
            echo "Whole Process Completed !!!";
        } catch (Exception $e) {
            echo "Something went wrong: " . $e->getMessage();
            return $e->getMessage();
        }
    }
}
