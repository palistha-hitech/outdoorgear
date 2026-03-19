<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopifyV2;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Shopify\App\Models\Source\SourceImage;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Models\Source\SourceSoh;
use Modules\Shopify\App\Models\Source\SourceVariant;
use Modules\Shopify\App\Services\SourceProductService;
use Retailcare\ShopifyApi\Http\Controllers\ShopifyRequest;
use Retailcare\ShopifyApi\Traits\v2025_01\ShopifyTrait as V2025_01ShopifyTrait;

class SourceVariationController extends Controller
{
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
                $products = $this->productService->getProductVariation($code);
            } else {

                $products = $this->productService->getAllVariationPending(3);
            }
            if ($debug == 1) {
                dd($products);
            }
            if (count($products) <= 0) {
                echo 'no products found';
                SourceProduct::where('shopifyVariationProcess', 2)->update(['shopifyVariationProcess' => 1]);
                exit;
            }
            $updateData = [];
            $data = [];
            foreach ($products as $product) {

                $data['shopifyProductID'] = $product->shopifyProductId;
                $data['debug'] = $debug;
                $variayionsforCreateOrUpdate = [];
                $uniqueOptions = [];
                foreach ($product->variants as $variation) {
                    $colorSize = $variation->color . '-' . $variation->size;

                    if (!in_array($colorSize, $uniqueOptions)) {
                        $variayionsforCreateOrUpdate[] = $this->generateVariation($variation, $product);
                    }
                    $uniqueOptions[] = $colorSize;
                }

                $data['variants'] = array_filter($variayionsforCreateOrUpdate, function ($item) {
                    return !empty($item['shopifyVariantID']);
                });
                $data['newVariation'] = array_filter($variayionsforCreateOrUpdate, function ($item) {
                    return empty($item['shopifyVariantID']);
                });

                if ($debug == 2) {
                    dd($data);
                }

                $shopifyRequest = new ShopifyRequest();
                $response = $shopifyRequest->sendRequest('createAndUpdateProductVariation', $data);
                $responses[] = $response;

                $updateData = [];
                if (@$response->errors) {

                    $UpdateData['shopifyVariationProcess'] = 2;
                }
                if (@$response->data->productVariantsBulkCreate && !empty($response->data->productVariantsBulkCreate)) {
                    $shopifyResponse = @$response->data->productVariantsBulkCreate;

                    if (@$shopifyResponse->userErrors[0]->message) {

                        $UpdateData['shopifyVariationProcess'] = 2;
                    }

                    if (@$shopifyResponse->productVariants[0]->id) {
                        $shopifyVariants = $shopifyResponse->productVariants;
                        foreach ($shopifyVariants as $shopifyVariant) {

                            SourceVariant::where('sku', $shopifyVariant->sku)->update([
                                'shopifyVariantId' => $shopifyVariant->id,
                                'inventoryItemId' => $shopifyVariant->inventoryItem->id
                            ]);
                        }
                        $updateData['shopifyVariationProcess'] = 0;
                    }
                }

                if (@$response->data->productVariantsBulkUpdate && !empty($response->data->productVariantsBulkUpdate)) {
                    $shopifyResponse = @$response->data->productVariantsBulkUpdate;

                    if (@$shopifyResponse->userErrors[0]->message) {
                        $updateData['shopify_item_error'] = json_encode($shopifyResponse->userErrors);
                        $UpdateData['shopifyVariationProcess'] = 2;
                    }

                    if (@$shopifyResponse->productVariants[0]->id) {
                        $shopifyVariants = $shopifyResponse->productVariants;
                        foreach ($shopifyVariants as $shopifyVariant) {

                            SourceVariant::where('sku', $shopifyVariant->sku)->update([
                                'shopifyVariantId' => $shopifyVariant->id,
                                'inventoryItemId' => $shopifyVariant->inventoryItem->id
                            ]);
                        }
                        $updateData['shopifyVariationProcess'] = 0;
                    }
                }
                $this->productService->updateProduct($product->id, $updateData);
            }


            echo "Process Completed";
        } catch (Exception  $th) {
            dd($th);
        }
    }


    public function generateVariation($variant)
    {

        $regularPrice = $variant->priceWithTax;
        $comparePrice = 0;
        $originalCompareAtPrice = $variant->compareAtPrice ?? 0;
        if ($variant->compareAtPrice < $regularPrice && $originalCompareAtPrice > 0) {
            $regularPrice = $originalCompareAtPrice;
            $comparePrice = $variant->priceWithTax;
        }
        $data = [
            'shopifyVariantID' => $variant->shopifyVariantId ?? null,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => $regularPrice,
            'compareAtPrice' => $comparePrice,
            'weightUnit' => 'KILOGRAMS'

        ];
        if ($variant->ciColourID != 0) {
            $data['optionValues']['Colour'] = $variant->color;
        }
        if ($variant->ciSizeID != 0) {
            $data['optionValues']['Size'] =  $variant->size;
        }


        return $data;
    }
}
