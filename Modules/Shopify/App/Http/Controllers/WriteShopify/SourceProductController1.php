<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopify;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Shopify\App\Models\Source\SourceImage;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Models\Source\SourceSoh;
use Modules\Shopify\App\Models\Source\SourceVariant;
use Modules\Shopify\App\Services\SourceProductService;
use Modules\Shopify\App\Traits\ShopifyProductMutationTrait;
use Modules\Shopify\App\Traits\ShopifyTrait;

class SourceProductController extends Controller
{
    use ShopifyTrait, ShopifyProductMutationTrait;
    /**
     * Display a listing of the resource.
     */
    protected $live = 1;
    protected $productService;
    public function __construct(SourceProductService $productService)
    {
        $this->productService = $productService;
    }
    public function index(Request $request)
    {
        $debug = $request->input('debug', 0);
        $code = $request->input('code', '');
        // $isManual = $request->input('isManual', 0);
        // if ($isManual == 0) {
        //     dd("is manual");
        // }
        $dev = $request->input('dev', 0);
        if ($dev == 0) {
            die('dev mode');
        }
        try {

            if ($code) {

                $products = $this->productService->getProduct($code);
            } else {

                $products = $this->productService->getAllProduct(3);
            }
            if ($debug == 1) {
                dd($products);
            }
            if (count($products) <= 0) {
                echo 'no products found';
                SourceProduct::where('shopifyPendingProcess', 2)->update(['shopifyPendingProcess' => 1]);
                exit;
            }

            foreach ($products as $product) {
                $this->productService->updateProduct($product->id, [

                    'lastPushedDate' => date('Y-m-d H:i:s')
                ]);
                echo "Product Title : " . $product->title . "<br>";
                echo "Product Handle : " . $product->handle . "<br>";

                $mutations =   $this->createOrUpdateProductMutation($product);
                if ($mutations == 1) {
                    $this->productService->updateProduct($product->id, [
                        'shopifyPendingProcess' => 2,
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ]);

                    continue;
                }
                if ($debug == 2) {
                    dd($mutations);
                }

                $response = $this->sendShopifyQueryRequestV2('POST', $mutations, $this->live);
                print_r($response);
                if ($debug == 3) {
                    dd($response);
                }
                $errors = $response->data->productCreate->userErrors[0]->message ??
                    $response->data->productUpdate->userErrors[0]->message ?? null;
                if (
                    $errors
                ) {
                    $this->productService->updateProduct($product->id, [
                        'shopifyPendingProcess' => 3,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'errorMessage' => json_encode($errors),
                    ]);
                    continue;
                }


                # check for error in creating

                if (isset($response->data->productCreate) || isset($response->data->productUpdate)) {

                    # get product id
                    $shopifyProductId = $response->data->productCreate->product->id
                        ?? $response->data->productUpdate->product->id;
                    $this->linkChannel($shopifyProductId);

                    echo "product id " . $shopifyProductId;
                    echo "<br>";
                    echo "product create or updated";

                    # get productvariants
                    $variants =  $response->data->productCreate->product->variants->edges
                        ?? $response->data->productUpdate->product->variants->edges ?? [];


                    foreach ($variants as $variant) {

                        $variantId = $variant->node->id;

                        $sku = $variant->node->sku;


                        $updateVariants = [
                            'shopifyVariantId' => $variantId,
                            'inventoryItemId' => $variant->node->inventoryItem->id,
                        ];

                        $this->productService->updateVariants($sku, $updateVariants);
                    }
                    # update productdata
                    $updateData = [
                        'shopifyPendingProcess' => 0, # success
                        'pricePendingProcess' => 0,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'shopifyProductId' => $shopifyProductId
                    ];
                    if ($product->sohPendingProcess == 3 || $product->sohPendingProcess == 2) {
                        $updateData['sohPendingProcess'] = 1;
                        $updateData['imagePendingProcess'] = 1;
                        $updateData['varinatsAppendPending'] = 1;
                    }
                } else {
                    $updateData = [
                        'shopifyPendingProcess' => 3, # success
                        'lastPushedDate' => date('Y-m-d H:i:s'),

                    ];
                }
                dump($updateData, $product->id);
                $res = $this->productService->updateProduct($product->id, $updateData);
                dump($res);
            }


            echo "Process Completed";
        } catch (Exception  $th) {
            dd($th);
        }
    }


    public function deletefromShopify(Request $request)
    {
        $debug = $request->get('debug') ?? 0;
        $limit = $request->get('limit') ?? 10;
        $sourceProducts = SourceProduct::where('isDeleted', 1)->take($limit)->get();
        if ($debug == 1) {
            dd($sourceProducts);
        }
        if ($sourceProducts->isEmpty()) {
            return response()->json(['message' => 'No data found'], 404);
        }
        $res = [];
        foreach ($sourceProducts as $sourceProduct) {
            DB::beginTransaction();
            $query = $this->deleteShopifyProduct($sourceProduct->shopifyProductId);
            if ($debug == 2) {
                dd($query);
            }
            $response = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
            $res[] = $response;
            if ($debug == 3) {
                dd($response);
            }
            if (isset($response->data->productDelete->deletedProductId)) {
                SourceSoh::where('product_id', $sourceProduct->id)->delete();
                SourceImage::where('product_id', $sourceProduct->id)->delete();
                SourceVariant::where('product_id', $sourceProduct->id)->delete();
                $sourceProduct->delete();
            }
            DB::commit();
        }
        return response()->json(['message' => 'success', 'response' => $res], 200);
    }
}
