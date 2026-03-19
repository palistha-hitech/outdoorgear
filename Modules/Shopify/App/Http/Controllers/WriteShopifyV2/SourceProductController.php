<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopifyV2;

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
use Modules\Shopify\App\Traits\ShopifyTrait;
use Retailcare\ShopifyApi\Http\Controllers\ShopifyRequest;
use Retailcare\ShopifyApi\Traits\v2025_01\ShopifyTrait as V2025_01ShopifyTrait;

class SourceProductController extends Controller
{
    // use ShopifyTrait;
    use V2025_01ShopifyTrait;
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
        $isManual = $request->input('isManual', 0);
        if ($isManual == 0) {
            dd("is manual");
        }
        $dev = $request->input('dev', 1);
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

                $mutations = $this->createOrUpdateProductMutation($product, $request);

                if ($debug == 2) {
                    dd($mutations);
                }

                $request = new ShopifyRequest();
                $response = $request->sendRequest('createOrUpdateProductByID', $mutations);

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
                    $shopifyProductId = @$response->data->productCreate->product->id
                        ?? @$response->data->productUpdate->product->id;
                    $newPublishIDs = [
                        "gid://shopify/Publication/118975562009",
                        "gid://shopify/Publication/153784877337",
                        "gid://shopify/Publication/153794773273"
                    ];

                    $this->publishablePublish($newPublishIDs, $shopifyProductId);

                    echo "product id " . $shopifyProductId;
                    echo "<br>";
                    echo "product create or updated";
                    # update productdata
                    $updateData = [
                        'shopifyPendingProcess' => 0, # success
                        'pricePendingProcess' => 0,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'shopifyProductId' => $shopifyProductId,
                        'shopifyVariationProcess' => 1
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

                $this->productService->updateProduct($product->id, $updateData);
            }


            echo "Process Completed";
        } catch (Exception  $th) {
            info($th->getMessage());

            return $th->getMessage();
            //throw $th;
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

    public function createOrUpdateProductMutation($productData, $request = null)
    {
        $exists = $this->checkproductOnShopify($productData);
        if (@$exists['status'] == 2) {
            dd($exists);
        }
        $payload = [];
        $payload['debug'] = $request->debug ?? 0;
        $payload['shopifyID'] = @$exists['shopifyProductId'] ?? null;
        $payload['title'] = $this->escapeDoubleQuotesV2($productData->title);
        $payload['description'] = $this->sanitizeStringV2($productData->descriptionHtml);
        $payload['vendor'] = $productData->vendor;
        $payload['handle'] = $this->filterHandleV2($productData->handle);
        $payload['productType'] = '';
        $checkSoh = $this->getSumOfSoh($productData->variants);
        $status = ($productData->status == 1 ? 'ACTIVE' : 'ARCHIVED');
        if ($checkSoh <= 0) {
            $status = 'ARCHIVED';
        }
        if (empty($productData->variants) && $productData->isMatrix == 1) {
            $status = 'ARCHIVED';
        }
        $payload['status'] =  $status;
        $payload['tags'] =str_replace('"', '', $productData->productTags);

        if ($productData->isMatrix == 1 && !empty($productData->variants)) {
            $payload['productOptions'] = $this->buildOptions($productData->variants, $productData->id);
        }
        $payload['seo']['title'] = $this->escapeDoubleQuotesV2($productData->title);
        $payload['seo']['description'] =  $this->sanitizeStringV2($productData->descriptionHtml);

        return $payload;
    }

    public function buildOptions($variations, $productId = null)
    {
        if (count($variations) <= 0) {
            $variations = SourceVariant::where('product_id', $productId)->get();
        }

        $optionValues = [];
        foreach ($variations as $variation) {
            $optionName = '';
            if (isset($variation->color)) {
                $optionName = 'Color';
                $optionValues[$optionName][] = '{name: "' . $variation->color . '"}';
            }
            if (isset($variation->size)) {
                $optionName = 'Size';
                $optionValues[$optionName][] = '{name: "' .  $variation->size . '"}';
            }
        }
        return $optionValues;
    }

    public function checkproductOnShopify($product)
    {
        $exists = [];
        $shopifyProductID = null;
        if ($product->shopifyProductId) {
            $productExists = $this->productCheckById($product->shopifyProductId);
            if (!isset($productExists->data->product->id)) {
                $productExists = $this->productCheckBYHandle($this->filterHandleV2($product->handle));
            }
        } else {
            $productExists = $this->productCheckBYHandle($this->filterHandleV2($product->handle));
        }

        $shopifyProductID = @$productExists->data->product->id ? @$productExists->data->product->id : @$productExists->data->productByHandle->id;


        if (@$shopifyProductID) {
            $exists['status'] = 1;
            $exists['shopifyProductId'] = @$shopifyProductID;
        } else {
            $exists['status'] = 0;
            $exists['message'] = "Product not found on shopify so create";
        }

        if (@$productExists->errors) {
            $exists['status'] = 2;
            $exists['message'] = json_encode($productExists->errors);
        }

        return $exists;
    }

    private function filterHandleV2($handle)
    {
        $result = \Str::lower($handle);
        $result  = str_replace(' ', '', $result);
        $result  = str_replace('.', '-', $result);
        $result  = str_replace('+', '-', $result);

        return $result;
    }
    private function escapeDoubleQuotesV2(string $input): string
    {

        $input = str_replace("\\", "\\\\", $input);
        $input = str_replace('"', '\"', $input);
        // $input = str_replace("'", "\'", $input);

        return $input;
    }

    private function sanitizeStringV2($string)
    {

        $sanitizedString = preg_replace('/\\\\(?![0-7]{3})/', '', $string);
        $productDescription = mb_convert_encoding($sanitizedString, 'ASCII', 'UTF-8');
        $productDescription = str_replace('?', "", $productDescription);
        // Ensure UTF-8 encoding
        $productDescription = $this->escapeDoubleQuotesV2($productDescription);
        return $productDescription;
    }
    public function getSumOfSoh($variants)
    {
        $variantIds = [];
        foreach ($variants as $variant) {
            $variantIds[] = $variant->id;
        }
        $totalStock = SourceSoh::whereIn('variant_id', $variantIds)->where("currentStock", '>', 0)->sum('currentStock');

        return $totalStock;
    }
}
