<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopify;

use App\Http\Controllers\Controller;
use App\Models\Products\Product;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Services\SourceProductService;
use Modules\Shopify\App\Traits\ShopifyImageMutationTrait;
use Modules\Shopify\App\Traits\ShopifyTrait;

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
                    'shopifyPendingProcess' => 0,
                    // 'shopifyVariationProcess' => 0
                ], $limit);
            }

            if ($debug == 1) {
                dd($products);
            }
            if (count($products) <= 0) {
                echo 'no products found';
                exit;
            }

            foreach ($products as $product) {
                echo "Product Title : " . $product->title . "<br>";
                echo "Product Handle : " . $product->handle . "<br>";

                //get previous Medias by handle
                $mediasDatas = $this->getMediaByHandle($product->shopifyProductId);
                if ($mediasDatas['status'] == 0) {
                    echo "Product Medias Not Found <br>";
                    $updateProduct = [
                        'imagePendingProcess' => 2,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'errorMessage' => 'No image available !!!'
                    ];
                    $this->productService->updateProduct($product->id, $updateProduct);
                    continue; // skip
                }

                # dump($mediasDatas);
                $shopifyProductId = $mediasDatas['pid'];
                if ($debug == 2) {
                    dd($mediasDatas);
                }
                if ($mediasDatas['status'] == 1 && count($mediasDatas['mediaIds']) > 0) {
                    echo "Product Medias Found : " . count($mediasDatas['mediaIds']) . "<br>";

                    //delete the medias by ids
                    $deleteMedias = $this->deleteImageMutation($mediasDatas['mediaIds'], $shopifyProductId);
                    #  print_r($deleteMedias);
                    if ($deleteMedias['status'] == 1) {
                        # dd($deleteMedias);
                        echo "Product Medias Deleted <br>";
                        print_r($deleteMedias);
                    } else {
                        echo "Product Medias Not Deleted <br>";
                        print_r($deleteMedias);
                    }
                } else {
                    echo "Product Medias Not Found <br>";
                    print_r($mediasDatas);
                }

                //image/media creation
                $groupedImages = [];
                foreach ($product->images as $image) {
                    // Group images by colorID
                    $groupedImages[$image->colorID][] = $image;
                }
                # dump($groupedImages);
                if ($debug == 3) {
                    dd($groupedImages);
                }
                $createMedias = $this->productCreateMedia($groupedImages, $shopifyProductId);
                # dump($createMedias);
                if ($createMedias['status'] == 1) {
                    echo "Product Medias Created <br>";
                    $count = 0;
                    foreach ($groupedImages as $key => $image) {
                        if ($key != 0) {
                            $this->productService->updateImage([
                                'colorID' => $key,
                                'product_id' => $image[0]->product_id
                            ], [
                                'shopifyMediaId' => $createMedias['mediaCreated'][$count]->id,
                                'pushedDate' => date('Y-m-d H:i:s')
                            ]);
                            $count++;
                        } else {
                            foreach ($image as $img) {
                                $this->productService->updateImage([
                                    'name' => $img->name,
                                    'colorID' => $key,
                                    'product_id' => $img->product_id
                                ], [
                                    'shopifyMediaId' => $createMedias['mediaCreated'][$count]->id,
                                    'pushedDate' => date('Y-m-d H:i:s')
                                ]);
                                $count++;
                            }
                        }
                    }


                    // foreach ($createMedias['mediaCreated'] as $media) {
                    //     echo "Media Id : " . $media->id . "=>" . $media->alt . "<br>";
                    //     //update media ids

                    // }
                    $updateProduct = [
                        'imagePendingProcess' => 0,
                        'varinatsAppendPending' => 1,
                        'errorMessage' => null,
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ];
                } else {
                    echo "Product Medias Not Created <br>";
                    print_r($createMedias);
                    $updateProduct = [
                        'imagePendingProcess' => ($createMedias['status'] == 3) ? 3 : 2, // 3 = image not found, 2 = error
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'errorMessage' => $createMedias['error']
                    ];
                }
                $this->productService->updateProduct($product->id, $updateProduct);
            }
            echo "Process Completed";
        } catch (Exception  $th) {
            info($th->getMessage());
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
                    'shopifyPendingProcess' => 0,
                    'shopifyVariationProcess' => 0,
                    // 'imagePendingProcess' => 0,
                    'varinatsAppendPending' => 1
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
                        'varinatsAppendPending' => 0,
                        'errorMessage' => ''
                    ]);
                exit;
            }

            foreach ($products as $product) {
                echo "Product ID : " . $product->id . "=>" . $product->handle . "<br>";

                $this->productService->updateProduct($product->id, [
                    'lastSyncDate' => date('Y-m-d H:i:s')
                ]);
                $productExists = $this->productExistsByShopifyId($product->shopifyProductId);
                if ($productExists['exist'] != 1) {
                    $productExists = $this->productExists($product->handle);
                }
                print_r($productExists);
                if ($productExists['exist'] == 1) {
                    $shopifyProductId = $productExists['handleID'];
                } else {
                    $this->productService->updateProduct($product->id, [
                        'varinatsAppendPending' => 2,
                        'errorMessage' => 'Product Not Found',
                    ]);
                    continue;
                }
                if ($product->variants) {

                    $mutation =   $this->VariantAppendMedia($product, $shopifyProductId);
                } else {
                    echo "Product Variants Not Found <br>";
                    $this->productService->updateProduct($product->id, [
                        'varinatsAppendPending' => 3,
                        'errorMessage' => 'no active varinats found',
                    ]);
                    continue;
                }

                if ($debug == 2) {
                    dd($mutation);
                }

                $response = $this->sendShopifyQueryRequestV2('POST', $mutation, $this->live);
                dump($response);
                if ($debug == 3) {
                    dd($response);
                }
                if (@$response->data->productVariantAppendMedia->userErrors) {
                    $this->productService->updateProduct($product->id, [
                        'varinatsAppendPending' => 2,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'errorMessage' => $response->data->productVariantAppendMedia->userErrors[0]->message
                    ]);
                    continue;
                }

                if (@$response->data->productVariantAppendMedia->productVariants) {
                    $this->productService->updateProduct($product->id, [
                        'varinatsAppendPending' => 0,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'errorMessage' => ''
                    ]);
                } else {
                    print_r($response);
                    $this->productService->updateProduct($product->id, [
                        'varinatsAppendPending' => 2,
                        'lastPushedDate' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            echo "Process Completed";
        } catch (Exception $e) {
            $this->productService->updateProduct($product->id, [
                'varinatsAppendPending' => 2,
                'lastPushedDate' => date('Y-m-d H:i:s')
            ]);
            dd($e);

            //throw $th;
        }
    }
}
