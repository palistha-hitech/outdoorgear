<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopifyV2;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Models\Source\SourceSoh;
use Modules\Shopify\App\Services\SourceProductService;
use Modules\Shopify\App\Traits\ShopifyTrait;
use Modules\Shopify\App\Traits\ShopifyProductMutationTrait;
use Retailcare\ShopifyApi\Http\Controllers\ShopifyRequest;
use Retailcare\ShopifyApi\Traits\v2025_01\ShopifyTrait as V2025_01ShopifyTrait;

class SourceSohController extends Controller
{

    // use ShopifyProductMutationTrait;
    use V2025_01ShopifyTrait;
    protected $productService;

    public function __construct(SourceProductService $productService)
    {
        $this->productService = $productService;
    }

    public function devModeenable(Request $request)
    {
        $dev = $request->input('dev', 0);
        if ($dev == 0) {
            die('dev mode');
        }
    }

    public function index(Request $request)
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
                    $statusResponse =  $this->updateShopidyProductStatus($shopifyProductId, 'ARCHIVED');
                    print_r($statusResponse);
                    continue;
                }
                foreach ($variants as $variant) {
                    echo 'variant id = ' . $variant->shopifyVariantId . '<br>';
                    echo "sku = " . $variant->sku . " soh = " . $variant->sourceSoh()->sum('currentStock') . "<br>";
                    if ($variant->shopifyVariantId == null || $variant->shopifyVariantId == '') {
                        continue;
                    }
                    $sourceSohs = $variant->sourceSoh()->get();

                    $mutations =  $this->updateProductSohMutationV2($sourceSohs, $variant->inventoryItemId, $variant->shopifyVariantId, $variant->id);
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
                        $mutations =  $this->updateProductSohMutationV2($sourceSohs, $variant->inventoryItemId, $variant->shopifyVariantId, $variant->id);
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
                        dd($mutations);
                        $shopifyRequest = new ShopifyRequest();
                        $response =   $shopifyRequest->sendRequest('inventoryAdjustQuantities', $payload);
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
                $newProductStatus = $totalSoh <= 0 ? 'ARCHIVED' : ($totalSoh > 0 && $product->status == 1 ? 'ACTIVE' : 'ARCHIVED');
                $statusMutation = $this->updateShopidyProductStatus($shopifyProductId, $newProductStatus);
                print_r($statusMutation);
            }
            echo "Process Completed  ";
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function updateProductSohMutationV2($sourceSohs, $inventoryItemId, $variantId, $sourceVarinatId)
    {
        $response = $this->getInventoryLevensWithLocation($variantId);

        // dd($response);
        if (isset($response->errors)) {
            return [
                'status' => 0,
                'locationActivated' => 0,
                'error' => json_encode($response->errors)
            ];
        }
        $res = [];
        if (isset($response->data->productVariant->inventoryItem)) {

            $res = $response->data->productVariant->inventoryItem->inventoryLevels->edges;
        }
        $totalActivatedLocation = count($res);
        if ($totalActivatedLocation == 8) {
            $payload = [];
            $sum = 0;
            foreach ($sourceSohs as $sourceSoh) {
                $locationId = $sourceSoh->location->shopifyLocationId;
                echo $locationId . "=>" . $sourceSoh->location->name . "=>" . $sourceSoh->currentStock . "<br>";
                if ($locationId === "gid://shopify/Location/83408388377") {
                    $sohDiff = 0;
                } else {
                    $sum += $sourceSoh->currentStock;
                    $sohDiff = $this->returnSohDiff($sourceSoh->currentStock, $locationId, $res, $inventoryItemId);
                    # dump($sohDiff);
                }
                $payload['changes'][] = [
                    'quantity' => (int) $sohDiff,
                    'inventoryItemId' => $inventoryItemId,
                    'locationId' => $locationId
                ];
            }
            return [
                'status' => 2,
                'mutation' => $payload,
                'sumOfSoh' => $sum,
                'locationActivated' => 0
            ];
        } else {
            $acticatedLocation = [];
            foreach ($res as $al) {
                $acticatedLocation[] = $al->node->location->id;
            }
            $needtoActivates = SourceSoh::whereNotIn('shopifyLocationId', $acticatedLocation)->get();
            $total = 0;
            foreach ($needtoActivates as $needtoActivate) {
                $soh = SourceSoh::where("location_id", $needtoActivate->id)->where("variant_id", $sourceVarinatId)->first();
                $availableSoh = $soh->currentStock ?? 0;
                $total += $availableSoh;
                if ($needtoActivate->shopifyLocationId == "gid://shopify/Location/83408388377") {
                    $availableSoh = 0;
                }
                if ($availableSoh < 0) {
                    $availableSoh = 0;
                }
                dump('location active');
                $this->activateInventoryLocation($inventoryItemId, $needtoActivate->shopifyLocationId, $availableSoh);
            }
            return [
                'status' => 1,
                'locationActivated' => 1,
                'mutation' => 'Activated Location',
                'sumOfSoh' => $total
            ];
        }
    }
}
