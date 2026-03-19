<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopify;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Services\SourceProductService;
use Modules\Shopify\App\Traits\ShopifyProductMutationTrait;
use Modules\Shopify\App\Traits\ShopifyTrait;

class SourcePriceController extends Controller
{
    use ShopifyTrait, ShopifyProductMutationTrait;
    protected $clientCode;
    protected  $productService;
    public function __construct(SourceProductService $productService)
    {
        $this->productService = $productService;
        $this->clientCode = $this->getClientCode();
    }

    public function indexBackup(Request $request)
    {
        die("cron down");
        $code = $request->input('code', '');
        $debug = $request->input('debug', 0);

        try {

            if ($code) {

                $products = $this->productService->getPricePendingProduct(['handle' => $code], 1);
            } else {

                $products = $this->productService->getPricePendingProduct(['pricePendingProcess' => 1,], 2);
            }

            if ($debug == 1) {
                dd($products);
            }

            if (count($products) <= 0) {
                echo 'no products found with pendingproces = 0';
                SourceProduct::where('pricePendingProcess', 2)->update(['pricePendingProcess' => 1]);
                exit;
            }

            foreach ($products as $product) {
                echo "handel =>" . $product->handle;
                echo "<br>";
                echo "erplyID =>" . $product->stockId;
                echo "<br>";
                $productExists = $this->productExists($product->handle);
                if ($productExists['exist'] != 1) {
                    $this->productService->updateProduct($product->id, [
                        'pricePendingProcess' => 2,
                        'errorMessage' => 'Product Not Found in shopify',
                    ]);
                    continue;
                }
                $shopifyProductId = $productExists['handleID'];
                $variants = $product->variants;
                $mutation = $this->updatePriceMutation($shopifyProductId, $variants);
                if ($debug == 2) {
                    dd($mutation);
                }
                $response = $this->sendShopifyQueryRequestV2('POST', $mutation,  $this->live);
                echo "<pre>";
                print_r($response);
                echo "</pre>";
                $res = $response->data->productVariantsBulkUpdate;
                # check for error in creating
                if ($res->userErrors) {
                    $this->productService->updateProduct($product->id, [
                        'pricePendingProcess' => 2,
                        'shopifyPendingProcess' => 1,
                        'errorMessage' => $res->userErrors[0]->message ?? "Something went wrong",
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ]);
                    continue;
                }
                if ($res->product->id || isset($res->product->id)) {
                    echo ' price updated';
                    $updateData = [
                        'pricePendingProcess' => 0,
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ];
                } else {
                    echo 'price not updated';
                    $updateData = [
                        'pricePendingProcess' => 2,
                        'errorMessage' => $res->userErrors[0]->message ?? "Something went wrong",
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ];
                }
                $this->productService->updateProduct($product->id, $updateData);
            }
        } catch (Exception $e) {
            dd($e);
            return $e->getMessage();
        }
    }

    public function index(Request $request)
    {
        die("cron down");
        $code = $request->input('code', '');
        $debug = $request->input('debug', 0);

        try {

            if ($code) {

                $products = $this->productService->getPricePendingProduct(['handle' => $code], 1);
            } else {

                $products = $this->productService->getPricePendingProduct(['pricePendingProcess' => 1,], 2);
            }

            if ($debug == 1) {
                dd($products);
            }

            if (count($products) <= 0) {
                echo 'no products found with pendingproces = 0';
                SourceProduct::where('pricePendingProcess', 2)->update(['pricePendingProcess' => 1]);
                exit;
            }

            foreach ($products as $product) {
                echo "handel =>" . $product->handle;
                echo "<br>";
                echo "erplyID =>" . $product->stockId;
                echo "<br>";
                $productExists = $this->productExists($product->handle);
                if ($productExists['exist'] == 0) {
                    $this->productService->updateProduct($product->id, [
                        'pricePendingProcess' => 2,
                        'errorMessage' => 'Product Not Found in shopify',
                    ]);
                    continue;
                }
                $shopifyProductId = $productExists['handleID'];
                if ($product->isMatrix == 1) {
                    $variants = $product->variants;
                } else {
                    // Get default Variant 
                    $query = $this->productService->getDefaultVariant($shopifyProductId);
                    $response = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
                    $shopify_variant_id = null;
                    if (isset($response->data->product->variants->edges)) {
                        $shopify_variant_id = $$response->data->product->variants->edges->node[0]->id;
                    }
                    if ($shopify_variant_id == null) continue;
                    $product->shopifyVariantId = $shopify_variant_id;
                    $variants[0] = $product;
                }
                $mutation = $this->updatePriceMutation($shopifyProductId, $variants);

                if ($debug == 2) {
                    dd($mutation);
                }
                $response = $this->sendShopifyQueryRequestV2('POST', $mutation,  $this->live);
                echo "<pre>";
                print_r($response);
                echo "</pre>";
                $res = $response->data->productVariantsBulkUpdate;
                # check for error in creating
                if ($res->userErrors) {
                    $this->productService->updateProduct($product->id, [
                        'pricePendingProcess' => 2,
                        'shopifyPendingProcess' => 1,
                        'errorMessage' => $res->userErrors[0]->message ?? "Something went wrong",
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ]);
                    continue;
                }
                if ($res->product->id || isset($res->product->id)) {
                    echo ' price updated';
                    $updateData = [
                        'pricePendingProcess' => 0,
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ];
                } else {
                    echo 'price not updated';
                    $updateData = [
                        'pricePendingProcess' => 2,
                        'errorMessage' => $res->userErrors[0]->message ?? "Something went wrong",
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ];
                }
                $this->productService->updateProduct($product->id, $updateData);
            }
        } catch (Exception $e) {
            dd($e);
            return $e->getMessage();
        }
    }
}
