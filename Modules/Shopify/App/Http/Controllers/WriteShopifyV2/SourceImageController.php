<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopifyV2;

use App\Http\Controllers\Controller;
use App\Models\Products\Product;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Shopify\App\Models\Source\SourceImage;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Services\SourceProductService;
use Modules\Shopify\App\Traits\ShopifyImageMutationTrait;
use Modules\Shopify\App\Traits\ShopifyTrait;
use Retailcare\ShopifyApi\Http\Controllers\ShopifyRequest;

class SourceImageController extends Controller
{
    use ShopifyTrait, ShopifyImageMutationTrait;
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
        try {

            $debug = $request->input('debug', 0);
            $code = $request->input('code', '');
            $limit = $request->input('limit', 4);
            if ($code) {
                $products = $this->productService->getImagePendingProduct([
                    'handle' => $code
                ], 1);
            } else {
                $products = $this->productService->getImagePendingProduct([
                    'imagePendingProcess' => 1,
                    'shopifyPendingProcess' => 0
                ], $limit);
            }

            if ($debug == 1) {
                dd($products);
            }
            if (count($products) <= 0) {
                echo 'no products found';
                exit;
            }
            $payload = [];
            foreach ($products as $product) {
                echo "Product Title : " . $product->title . "<br>";
                echo "Product Handle : " . $product->handle . "<br>";

                $payload['debug'] = $debug;
                $payload['shopifyProductID'] = $product->shopifyProductId;
                $groupedImages = [];
                foreach ($product->images as $image) {
                    $payload['medias'][] = [
                        'alt' => $image->name,
                        'filename' => $image->name,
                        'originalSource' => "https://cdn.erply.com/images/603965/" . $image->name
                    ];
                    $groupedImages[$image->colorID][] = $image;
                }

                if ($debug == 3) {
                    dd($payload);
                }

                $request = new ShopifyRequest();
                $shopifyresponse =  $request->sendRequest('mediaCreate', $payload);
                $updateProduct = [];
                if (@$shopifyresponse->data->productSet->product->id) {
                    $mediaIDS = @$shopifyresponse->data->productSet->product->media->edges ?? [];
                    foreach ($mediaIDS as $media) {
                        $this->productService->updateImage([
                            'name' => $media->node->alt,
                            'product_id' => $product->id
                        ], [
                            'shopifyMediaId' => $media->node->id,
                            'pushedDate' => date('Y-m-d H:i:s')
                        ]);
                    }

                    $updateProduct['imagePendingProcess'] = 0;
                    $updateProduct['varinatsAppendPending'] = 1;
                } else {
                    $updateProduct['imagePendingProcess'] = 2;
                    $updateProduct['errorMessage'] = json_encode($shopifyresponse);
                }
                $updateProduct['lastPushedDate'] = date('Y-m-d H:i:s');
                $this->productService->updateProduct($product->id, $updateProduct);
            }
            echo "Process Completed";
        } catch (Exception  $th) {
            return $th->getMessage();
        }
    }

    public function addvariantsToMedia(Request $request)
    {
        try {

            $debug = $request->input('debug', 0);
            $code = $request->input('code', '');
            $limit = $request->input('limit', 3);

            if ($code) {
                $products = $this->productService->getPendingProductAppend(['handle' => $code], 1);
            } else {
                $products = $this->productService->getPendingProductAppend([
                    'varinatsAppendPending' => 1,
                    'imagePendingProcess' => 0
                ], $limit);
            }

            if ($debug == 1) {
                dd($products);
            }
            if (count($products) <= 0) {
                echo 'no products found';
                SourceProduct::where('status', 1)
                    ->whereIn('varinatsAppendPending', [4])
                    ->update([
                        'imagePendingProcess' => 1,
                        'varinatsAppendPending' => 0
                    ]);
                exit;
            }
            $payload = [];
            $responses = [];
            $updateData = [];
            foreach ($products as $product) {
                echo "Product ID : " . $product->id . "=>" . $product->handle . "<br>";

                // $this->productService->updateProduct($product->id, [
                //     'lastSyncDate' => date('Y-m-d H:i:s')
                // ]);

                if ($product->variants) {
                    $payload['debug'] = $debug;
                    $payload['shopifyProductID'] = $product->shopifyProductId;

                    foreach ($product->variants as $variant) {

                        if (isset($variant->images) && count($variant->images) > 0) {

                            $payload['variants'][] = [
                                'shopifyVariantID' => $variant->shopifyVariantId,
                                'mediaId' => $variant->images->first()->shopifyMediaId,
                            ];

                        } else {

                            $mainImage = SourceImage::where([
                                'product_id' => $variant->product_id,
                                'isDeleted' => 0,
                                'colorID' => $variant->colorID
                            ])->whereNotNull('shopifyMediaId')->first();
                            
                            if (!$mainImage) {
                                $mainImage = SourceImage::where([
                                    'product_id' => $variant->product_id,
                                    'isDeleted' => 0
                                ])->whereNotNull('shopifyMediaId')->first();
                            }

                            if ($mainImage) {
                                $payload['variants'][] = [
                                    'shopifyVariantID' => $variant->shopifyVariantId,
                                    'mediaId' => $mainImage->shopifyMediaId,
                                ];
                            }
                        }

                    }

                    if ($debug == 2) {
                        dd($payload);
                    }

                    $request = new ShopifyRequest();
                    $shopifyresponse =  $request->sendRequest('appendMediasToVariants', $payload);

                    $responses[] = $shopifyresponse;

                    if (@$shopifyresponse->data->productVariantsBulkUpdate->product->id) {
                        $updateData['varinatsAppendPending'] =  0;
                        $updateData['errorMessage'] = '';
                    } else {
                        $updateData['varinatsAppendPending'] = 3;
                        $updateData['errorMessage'] = json_encode($shopifyresponse);
                    }

                    $updateData['lastPushedDate'] = date('Y-m-d H:i:s');

                    $this->productService->updateProduct($product->id, $updateData);
                } else {
                    echo "Product Variants Not Found <br>";
                    $this->productService->updateProduct($product->id, [
                        'varinatsAppendPending' => 3,
                        'errorMessage' => 'no active varinats found',
                    ]);
                }
            }

            return response()->json(['message' => 'process completed', 'data' => $responses]);
        } catch (Exception $e) {
            $this->productService->updateProduct($product->id, [
                'varinatsAppendPending' => 2,
                'lastPushedDate' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
