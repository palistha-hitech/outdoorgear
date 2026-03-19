<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopify;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shopify\App\Models\Source\SourceLocation;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Services\SourceProductService;
use Modules\Shopify\App\Traits\ShopifyTrait;
use Modules\Shopify\App\Traits\ShopifyProductMutationTrait;
use Modules\Shopify\App\Http\Controllers\WriteShopify\SourceProductController;
use Modules\Shopify\App\Models\Source\SourceVariant;

class SourceSohController extends Controller
{

    use ShopifyTrait, ShopifyProductMutationTrait;


    protected $productService;
    protected $prodObj;

    public function __construct(SourceProductService $productService, SourceProductController $prodObj)
    {
        $this->productService = $productService;
        $this->prodObj = $prodObj;
    }

    public function devModeenable(Request $request)
    {
        $dev = $request->input('dev', 0);
        if ($dev == 0) {
            die('dev mode');
        }
    }

    public function indexBackup(Request $request)
    {
        $code = $request->input('code', '');
        $debug = $request->input('debug', 0);
        // $this->devModeenable($request);
        try {

            if ($code) {

                $products = $this->productService->getSohPendingProduct(['handle' => $code], 1);
            } else {

                $products = $this->productService->getSohPendingProduct(['sohPendingProcess' => 1, 'shopifyPendingProcess' => 0], 2);
            }
            if ($debug == 1) {
                dd($products);
            }
            if (count($products) <= 0) {
                echo 'no products found';
                SourceProduct::where('sohPendingProcess', 2)->update(['sohPendingProcess' => 1]);
                exit;
            }

            foreach ($products as $product) {
                echo "Product Title : " . $product->title . "<br>";
                echo "Product Handle : " . $product->handle . "<br>";

                # $productExists = $this->productExistsByShopifyId($product->shopifyProductId);
                // if ($product->shopifyProductId) {
                //     if ($productExists['exist'] != 1) {
                //         $productExists = $this->productExists($product->handle);
                //     }
                // } else {
                //     $productExists = $this->productExists($product->handle);
                // }
                // if ($productExists['exist'] != 1) {
                //     $this->productService->updateProduct($product->id, [
                //         'sohPendingProcess' => 2,
                //         'lastPushedDate' => date('Y-m-d H:i:s'),
                //         'errorMessage' => 'product not found'
                //     ]);
                //     continue;
                // }
                # $shopifyProductId = $productExists['handleID'];
                $shopifyProductId = $product->shopifyProductId;
                echo "Shopify Product Id : " . $shopifyProductId . "<br>";
                $variants = $product->variants;
                $totalSoh = 0;
                $flag = 0;
                if (count($variants) <= 0) {
                    $this->productService->updateProduct($product->id, [

                        'sohPendingProcess' => 3,
                        // 'shopifyPendingProcess' => 3,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'errorMessage' => 'no active variants found'
                    ]);
                    $statusMutation =  $this->changeStatusMutation($shopifyProductId, 'ARCHIVED');
                    $statusResponse = $this->sendShopifyQueryRequestV2('POST', $statusMutation, $this->live);
                    print_r($statusResponse);
                    continue;
                    # code...
                }
                foreach ($variants as $variant) {
                    echo 'variant id = ' . $variant->shopifyVariantId . '<br>';
                    echo "sku = " . $variant->sku . " soh = " . $variant->sourceSoh()->sum('currentStock') . "<br>";
                    if ($variant->shopifyVariantId == null || $variant->shopifyVariantId == '') {
                        continue;
                    }
                    $sourceSohs = $variant->sourceSoh()->get();

                    $mutations =  $this->updateProductSohMutation($sourceSohs, $variant->inventoryItemId, $variant->shopifyVariantId, $variant->id, $product);
                    if (isset($mutations['status']) && (int)$mutations['status'] == 0 && $mutations['error']) {
                        dump("have some error");
                        $this->productService->updateProduct($product->id, [
                            'sohPendingProcess' => 2,
                            'lastPushedDate' => date('Y-m-d H:i:s'),
                            'errorMessage' => $mutations['error']
                        ]);
                        exit;
                    }
                    if (isset($mutations['status']) && (int)$mutations['status'] == 1 && $mutations['locationActivated'] == 1) {
                        dump("location activated");
                        $mutations =  $this->updateProductSohMutation($sourceSohs, $variant->inventoryItemId, $variant->shopifyVariantId, $variant->id, $product);
                        if (isset($mutations['status']) && (int)$mutations['status'] == 0 && $mutations['error']) {

                            $this->productService->updateProduct($product->id, [
                                'sohPendingProcess' => 2,
                                'lastPushedDate' => date('Y-m-d H:i:s'),
                                'errorMessage' => $mutations['error']
                            ]);
                            exit;
                        }
                    }

                    dump('no error');
                    $totalSoh += $mutations['sumOfSoh'] ?? 0;
                    if ($debug == 2) {
                        dd($mutations);
                    }
                    if ($mutations['status'] == 2) {
                        $response = $this->sendShopifyQueryRequestV2('POST', $mutations['mutation'], $this->live);
                        echo "<pre>";
                        print_r($response);
                        echo "</pre>";

                        if ($debug == 3) {
                            dd($response);
                        }

                        if (!empty($response->data->updateInventoryItem->userErrors)) {
                            echo "have some error";
                            $flag = 0;
                        } else {
                            $flag = 1;
                        }
                    }
                }

                if ($flag == 1) {
                    echo "product Soh = " . $totalSoh . " for product " . $product->id . "<br>";
                    $updateData = [
                        'sohPendingProcess' => 0,
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ];
                    if (
                        $product->varinatsAppendPending != 0
                        || $product->varinatsAppendPending != 1
                        || $product->imagePendingProcess != 0
                    ) {
                        $updateData['imagePendingProcess'] = 1;
                    }
                } else {
                    $updateData = [
                        'sohPendingProcess' => 2,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'errorMessage' => "soh failed to update"
                    ];
                }


                $this->productService->updateProduct($product->id, $updateData);
                echo "Total Soh = " . $totalSoh . " for product " . $product->id . "<br>";

                // if ($totalSoh <= 0) {
                //     echo "product Soh = " . $totalSoh . " for product " . $product->id;

                //     $statusMutation =  $this->changeStatusMutation($shopifyProductId, 'ARCHIVED');

                //     echo "Product " . $product->id . " archived successfully";
                // } else {
                //     $statusMutation = $this->changeStatusMutation($shopifyProductId, 'ACTIVE');
                // }

                // No need to change status i.e leave status as it is
                // $newProductStatus = $totalSoh <= 0 ? 'ARCHIVED' : ($totalSoh > 0 && $product->status == 1 ? 'ACTIVE' : 'ARCHIVED');
                // $statusMutation = $this->changeStatusMutation($shopifyProductId, $newProductStatus);
                // $statusResponse = $this->sendShopifyQueryRequestV2('POST', $statusMutation, $this->live);
                // print_r($statusResponse);
            }
            echo "Process Completed  ";
        } catch (Exception $e) {

            dd($e);

            return $e->getMessage();

            //throw $th;
        }
    }

    public function index(Request $request)
    {

        ini_set('max_execution_time', 10000);
        ini_set('memory_limit', -1);

        $code = $request->input('code', '');
        $debug = $request->input('debug', 0);
        $limit = $request->input('limit', 3);


        $soh_tries = $request->input('soh-tries', 3);
        // if ($limit > 3) {
        //     die('More than 3 limit cannot be set !!!');
        // }
        // $this->devModeenable($request);
        // die('temporary blocked');

        try {

            if ($code) {

                $products = $this->productService->getSohPendingProduct(['handle' => $code], 1);
                // dd($products);
            } else {



                $products = $this->productService->getSohPendingProduct(['sohPendingProcess' => 1, 'shopifyPendingProcess' => 0, 'shopifyVariationProcess' => 0, 'status' => 1], $limit);
            }

            if ($debug == 1) {
                dd($products);
            }
            if (count($products) <= 0) {
                // echo 'no products found';
                SourceProduct::where(['shopifyPendingProcess' => 0, 'shopifyVariationProcess' => 0, 'status' => 1, 'sohPendingProcess' => 2])->where('sohResyncTryCount', '<', $soh_tries)->update(['sohPendingProcess' => 1]);
                exit;
            }

            foreach ($products as $product) {
                // echo "Product Title : " . $product->title . "<br><br>";
                // echo "Product Handle : " . $product->handle . "<br><br>";
                // $shopifyProductId = $product->shopifyProductId;
                // echo "Shopify Product Id : " . $shopifyProductId . "<br>";
                $variants = $product->variants;
                dump('variant count : ', $variants->count());
                $totalSoh = 0;
                $flag = 0;

                if ($product->isMatrix == 0) {
                    $sohs = $product->sohs()->get();

                    $skip = false;
                    if ($sohs->isEmpty()) $skip = true;
                    // if ($sohs->isNotEmpty()) {
                    //     $sumOfStock = $sohs->sum('currentStock');
                    //     if ($sumOfStock <= 0) {
                    //         $skip = true;
                    //     }
                    // }

                    if ($skip == true) {
                        // if (\request()->query('check-soh') == 'true') {
                        //     $product->shopifyVariationProcess = 1;
                        //     $product->status = 0;
                        //     $product->save();
                        // }
                        $product->sohPendingProcess = 2;
                        $product->save();
                        continue;
                    }

                    // echo "Total SOH Count : " . count($sohs) . "<br><br>";
                    // print_r($sohs);
                    $query = $this->productService->getDefaultVariant($product->shopifyProductId);

                    if ($debug == 2) {
                        dd($query);
                    }
                    $response = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);

                    if ($debug == 3) {
                        dd($response->data->product->variants->edges);
                    }



                    if (isset($response->data->product->variants->edges)) {
                        // dump('Default Variant : ', $response);
                        if ($debug == 'defaultVariant') dd($response);
                        $shopify_variant_id = $response->data->product->variants->edges[0]->node->id;
                        $inventory_item_id = $response->data->product->variants->edges[0]->node->inventoryItem->id;

                        $mutations =  $this->updateProductSohMutation($sohs, $inventory_item_id, $shopify_variant_id, 0, $product);

                        if ($debug == 4) {
                            dd($mutations);
                        }
                        // dump('Mutation Array : ', $mutations);
                        if (isset($mutations['status']) && (int)$mutations['status'] == 0 && $mutations['error']) {
                            dump("have some soh error");
                            $soh_resync_count = $product->sohResyncTryCount + 1;
                            $sohPendingProcess = 2;
                            if ($soh_resync_count > $soh_tries) {
                                $sohPendingProcess = 4;
                            }

                            $dataArray = [
                                'sohPendingProcess' => $sohPendingProcess,
                                'lastPushedDate' => date('Y-m-d H:i:s'),
                                'errorMessage' => $mutations['error']
                            ];
                            if ($soh_resync_count <= $soh_tries) {
                                $dataArray['sohResyncTryCount'] = $soh_resync_count;
                            }
                            $this->productService->updateProduct($product->id, $dataArray);
                            exit;
                        }

                        if (isset($mutations['status']) && (int)$mutations['status'] == 1 && $mutations['locationActivated'] == 1) {
                            // dump("location activated");
                            $mutations =  $this->updateProductSohMutation($sohs, $inventory_item_id, $shopify_variant_id, 0, $product);

                            if ($debug == 5) {
                                dd($mutations);
                            }
                            if (isset($mutations['status']) && (int)$mutations['status'] == 0 && $mutations['error']) {
                                dd('error');
                                $soh_resync_count = $product->sohResyncTryCount + 1;
                                $sohPendingProcess = 2;
                                if ($soh_resync_count > $soh_tries) {
                                    $sohPendingProcess = 4;
                                }

                                $dataArray = [
                                    'sohPendingProcess' => $sohPendingProcess,
                                    'lastPushedDate' => date('Y-m-d H:i:s'),
                                    'errorMessage' => $mutations['error']
                                ];
                                if ($soh_resync_count <= $soh_tries) {
                                    $dataArray['sohResyncTryCount'] = $soh_resync_count;
                                }
                                $this->productService->updateProduct($product->id, $dataArray);
                                exit;
                            }
                        }

                        $this->productService->updateProduct($product->id, [
                            'sohPendingProcess' => 0,
                            'lastPushedDate' => date('Y-m-d H:i:s')
                        ]);
                        dump('no error');
                        $current_variant_soh = $mutations['sumOfSoh'] ?? 0;

                        $totalSoh += $current_variant_soh;
                        if ($debug == 2) {
                            dd($mutations);
                        }
                        if ($mutations['status'] == 2) {
                            $response = $this->sendShopifyQueryRequestV2('POST', $mutations['mutation'], $this->live);
                            // echo "<pre>";
                            // print_r($response);
                            // echo "</pre>";



                            if (!empty($response->data->updateInventoryItem->userErrors)) {
                                echo "have some error";
                                $flag = 0;
                            } else {
                                $flag = 1;
                            }
                        }
                    }
                } else {
                    if ($product->isMatrix == 1) {
                        foreach ($variants as $variant) {

                            $variant_sohs = $variant->sourceSoh()->get();
                            dump($variant_sohs);
                            $skip = false;
                            if ($variant_sohs->isEmpty()) $skip = true;
                            // if ($variant_sohs->isNotEmpty()) {
                            //     $sumOfStock = $variant_sohs->sum('currentStock');
                            //     if ($sumOfStock <= 0) {
                            //         $skip = true;
                            //     }
                            // }

                            if ($skip == true) {
                            
                                // if (\request()->query('check-soh') == 'true') {
                                //     $product->shopifyVariationProcess = 1;
                                //     $product->save();

                                //     $variant->status = 'ARCHIVED';
                                //     $variant->displayInWebshop = 0;
                                //     $variant->save();
                                // }
                                continue;
                            }

                            if ( $variant->status == 'ARCHIVED') continue;
// dd('test');
                            // echo '<br><br>Variant ID = ' . $variant->shopifyVariantId . '<br>';
                            // echo "SKU = " . $variant->sku . " <br> Total SOH = " . $variant->sourceSoh()->sum('currentStock') . "<br>";
                            if ($variant->shopifyVariantId == null || $variant->shopifyVariantId == '') {
                                continue;
                            }

                            $mutations =  $this->updateProductSohMutation($variant_sohs, $variant->inventoryItemId, $variant->shopifyVariantId, $variant->id, $product);



                            dump($mutations);

                            if (isset($mutations['status']) && (int)$mutations['status'] == 0 && $mutations['error']) {
                                dump("have some error");
                                $soh_resync_count = $product->sohResyncTryCount + 1;
                                $sohPendingProcess = 2;
                                if ($soh_resync_count > $soh_tries) {
                                    $sohPendingProcess = 4;
                                }

                                $dataArray = [
                                    'sohPendingProcess' => $sohPendingProcess,
                                    'lastPushedDate' => date('Y-m-d H:i:s'),
                                    'errorMessage' => $mutations['error']
                                ];
                                if ($soh_resync_count <= $soh_tries) {
                                    $dataArray['sohResyncTryCount'] = $soh_resync_count;
                                }
                                $this->productService->updateProduct($product->id, $dataArray);
                                exit;
                            }

                            if (isset($mutations['status']) && (int)$mutations['status'] == 1 && $mutations['locationActivated'] == 1) {


                                // dump("location activated");
                                $mutations =  $this->updateProductSohMutation($variant_sohs, $variant->inventoryItemId, $variant->shopifyVariantId, $variant->id, $product);
                     

                                if (isset($mutations['status']) && (int)$mutations['status'] == 0 && $mutations['error']) {

                                    $soh_resync_count = $product->sohResyncTryCount + 1;
                                    $sohPendingProcess = 2;
                                    if ($soh_resync_count > $soh_tries) {
                                        $sohPendingProcess = 4;
                                    }

                                    $dataArray = [
                                        'sohPendingProcess' => $sohPendingProcess,
                                        'lastPushedDate' => date('Y-m-d H:i:s'),
                                        'errorMessage' => $mutations['error']
                                    ];
                                    if ($soh_resync_count <= $soh_tries) {
                                        $dataArray['sohResyncTryCount'] = $soh_resync_count;
                                    }
                                    $this->productService->updateProduct($product->id, $dataArray);
                                    exit;
                                }
                            }



                            echo "<br>Successfully SOH updated for the product !!!<br><br>";
                            $current_variant_soh = $mutations['sumOfSoh'] ?? 0;
// dd($current_variant_soh);
                            $totalSoh += $current_variant_soh;
                            if ($debug == 2) {
                                dd('mutation',$mutations);
                            }

                            if ($mutations['status'] == 2) {
                                $response = $this->sendShopifyQueryRequestV2('POST', $mutations['mutation'], $this->live);
                                //echo "<pre>";
                                // print_r($response);
                                //echo "</pre>";





                                if ($debug == 3) {
                                    dd($response, 'here');
                                }
                                $flag = 1;
                                if (!empty($response->data->updateInventoryItem->userErrors)) {
                                    echo "<br>have some error";
                                    $flag = 0;
                                } else {
                                    $flag = 1;
                                }
                            }
                        }
                    }
                }



                if ($flag == 1) {
                    // echo "product Soh = " . $totalSoh . " for product " . $product->id . "<br>";
                    $updateData = [
                        'sohPendingProcess' => 0,
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ];
                } else {

                    $soh_resync_count = $product->sohResyncTryCount + 1;
                    $sohPendingProcess = 2;
                    if ($soh_resync_count > $soh_tries) {
                        $sohPendingProcess = 4;
                    }

                    $updateData = [
                        'sohPendingProcess' => $sohPendingProcess,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'errorMessage' => $mutations['error'] ?? null
                    ];
                    if ($soh_resync_count <= $soh_tries) {
                        $updateData['sohResyncTryCount'] = $soh_resync_count;
                    }
                }



                $this->productService->updateProduct($product->id, $updateData);

                // Now process the variants with zero stock 
                // if (\request()->query('check-stock') == 'true' & $product->isMatrix == 1) {
                //     dump('Check SOH Called!!!');
                //     // Get all shopify variants list
                //     $shopify_variation_ids = [];
                //     $shopify_product_id = $product->shopifyProductId ? str_replace('gid://shopify/Product/', '', $product->shopifyProductId) : null;
                //     if ($shopify_product_id != null) {
                //         $shopify_variation_ids = $this->prodObj->checkProductVariantByProductID($shopify_product_id);
                //     }

                //     // Simple i.e make those product as archived
                //     // Rest is handled by below function
                //     if ($product->isMatrix == 1 && count($shopify_variation_ids) > 0) {
                //         $this->prodObj->removeArchivedVariants($product->shopifyProductId, $product->id, $debug, $shopify_variation_ids);
                //     }
                // }

                // echo "<br>Total SOH = " . $totalSoh . " for product : " . $product->id . "<br>";


                // if ($totalSoh <= 0) {
                //     echo "product Soh = " . $totalSoh . " for product " . $product->id;
                //     $statusMutation =  $this->changeStatusMutation($shopifyProductId, 'ARCHIVED');
                //     echo "Product " . $product->id . " archived successfully";
                // } else {
                //     $statusMutation = $this->changeStatusMutation($shopifyProductId, 'ACTIVE');
                // }

                // No need to change status i.e leave status as it is
                // $newProductStatus = $totalSoh <= 0 ? 'ARCHIVED' : ($totalSoh > 0 && $product->status == 1 ? 'ACTIVE' : 'ARCHIVED');
                // $statusMutation = $this->changeStatusMutation($shopifyProductId, $newProductStatus);
                // $statusResponse = $this->sendShopifyQueryRequestV2('POST', $statusMutation, $this->live);
                // print_r($statusResponse);
            }
            echo "<b>!!! ============== Process Completed ============== !!!<b>";
        } catch (Exception $e) {

            dd($e);

            return $e->getMessage();

            //throw $th;
        }
    }
}
