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
                if ($mutations == null) continue;
                // if ($mutations == 1) {
                //     $this->productService->updateProduct($product->id, [
                //         'shopifyPendingProcess' => 2,
                //         'lastPushedDate' => date('Y-m-d H:i:s')
                //     ]);
                //     continue;
                // }
                if ($debug == 2) {
                    dd($mutations);
                }

                $response = $this->sendShopifyQueryRequestV2('POST', $mutations, $this->live);
                print_r($response);
                if ($debug == 3) {
                    dd($response);
                }
                $errors = $response->data->productCreate->userErrors[0]->message ?? $response->data->productUpdate->userErrors[0]->message ?? null;

                if ( $errors) {
                    $this->productService->updateProduct($product->id, [
                        'shopifyPendingProcess' => 3, // Errors
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
                    // $variants =  $response->data->productCreate->product->variants->edges ?? $response->data->productUpdate->product->variants->edges ?? [];

                    // foreach ($variants as $variant) {
                    //     $variantId = $variant->node->id;
                    //     $sku = $variant->node->sku;
                    //     $updateVariants = [
                    //         'shopifyVariantId' => $variantId,
                    //         'inventoryItemId' => $variant->node->inventoryItem->id,
                    //     ];
                    //     $this->productService->updateVariants($sku, $updateVariants);
                    // }

                    $isCreated = false;
                    if (isset($response->data->productCreate->product->id)) {
                        $shopifyProductId = $response->data->productCreate->product->id;
                        $isCreated = true;
                    } else {
                        $shopifyProductId = $response->data->productUpdate->product->id;
                    }

                    # update productdata
                    $updateData = [
                        'pricePendingProcess' => 0,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'shopifyProductId' => $shopifyProductId
                    ];
                    if ($product->sohPendingProcess == 3 || $product->sohPendingProcess == 2) {
                        $updateData['sohPendingProcess'] = 1;
                        $updateData['imagePendingProcess'] = 1;
                        $updateData['varinatsAppendPending'] = 1;
                    }

                    if ($isCreated) {
                        $updateData['shopifyPendingProcess'] = 0; // Success
                        $updateData['shopifyVariationProcess'] = 1; // Next step process variants
                    } else {
                        $updateData['shopifyPendingProcess'] = 5; // Updated i.e disable variants update for now
                    }
                
                } else {
                    $updateData = [
                        'shopifyPendingProcess' => 3, # error
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'errorMessage' => !empty($response->errors) ? json_encode($response->errors) : null
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

    public function pushVariants (Request $request) 
    {
        dd('test');
        $debug = $request->input('debug', 0);
        $code = $request->input('code', '');
        $limit = $request->input('limit', 3);
        try {
            $products = $code ? $this->productService->getProduct($code) : $this->productService->getAllVariationPending($limit);
            if ($debug == 1) {
                dd($products);
            }
            if ($products->isEmpty()) {
                return response()->json(['message' => 'No pending product variants found'], 404);
            }

            $responses = [];
            foreach ($products as $product) {

                DB::beginTransaction();
                $mutations = $this->variantsMutation($product, false, false);

                if ($debug == 2) {
                    dd($mutations);
                }

                if (!$mutations) continue;

                $response =  $this->sendShopifyQueryRequestV2('POST', $mutations, $this->live);
                $responses[$product->productID] = $response;

                if ($debug == 3) {
                    dd($response);
                }

                $errors = $response->data->productVariantsBulkCreate->userErrors[0]->message ?? $response->data->productVariantsBulkUpdate->userErrors[0]->message ?? null;
                if ($errors) {
                    $product->update([
                        'shopifyPendingProcess' => 4,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'errorMessage' => json_encode($errors),
                    ]);
                    DB::commit();
                    continue;
                }

                // check for error in creating
                if (isset($response->data->productVariantsBulkCreate) || isset($response->data->productVariantsBulkUpdate)) {
                    $updateData = [
                        // 'shopifyPendingProcess' => 0, // Success
                        'shopifyVariationProcess' => 0, // Success
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        // 'shopifyProductId' => @$response->data->productVariantsBulkCreate->productVariants->id ?? $response->data->productVariantsBulkUpdate->productVariants->id,
                        // 'ShopifyVariationID' => json_encode(@$response->data->productCreate->product->variants->edges ?? $response->data->productUpdate->product->variants),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                    $this->updateSourceVariants(@$response->data->productVariantsBulkCreate->productVariants); // update variantId in source_variants
                } else {
                    $updateData = [
                        'shopifyPendingProcess' => 3, // error
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                }
                $product->update($updateData);
                DB::commit();
            }

            return response()->json([
                'message' => 'Product Variants Synced Successfully.',
                'data' => $responses,
            ]);
        } catch (Exception  $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function updateArchivedVariants(Request $request)
    {
        $debug = $request->input('debug', 0);
        $code = $request->input('code', '');
        $limit = $request->input('limit', 3);
        try {
            $products = $code ? $this->productService->getPreviousDeletedVariantProducts($code, 1) : $this->productService->getPreviousDeletedVariantProducts(null, $limit);
            if ($debug == 1) {
                dd($products);
            }
            if ($products->isEmpty()) {
                // SourceProduct::where('shopifyPendingProcess', 3)->update(['shopifyPendingProcess' => 1]);
                return response()->json(['message' => 'No pending archived product variants found'], 404);
            }

            $responses = [];
            foreach ($products as $product) {

                DB::beginTransaction();
                $mutations = $this->variantsMutation($product, false, true);
                if (!$mutations) return null;

                if ($debug == 2) {
                    dd($mutations);
                }

                $response =  $this->sendShopifyQueryRequestV2('POST', $mutations, $this->live);
                $responses[] = $response;
                if ($debug == 3) {
                    dd($responses);
                }

                $errors = $response->data->productVariantsBulkCreate->userErrors[0]->message ?? $response->data->productVariantsBulkUpdate->userErrors[0]->message ?? null;
                if ($errors) {
                    $product->update([
                        'shopifyPendingProcess' => 4,
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'errorMessage' => json_encode($errors),
                    ]);
                    DB::commit();
                    continue;
                }

                // check for error in creating
                if (isset($response->data->productVariantsBulkCreate) || isset($response->data->productVariantsBulkUpdate)) {
                    $updateData = [
                        'shopifyVariationProcess' => 0, // Success
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                    $this->updateSourceVariants(@$response->data->productVariantsBulkCreate->productVariants); // update variantId in source_variants
                } else {
                    $updateData = [
                        'shopifyPendingProcess' => 3, // Error
                        'lastPushedDate' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                }
                $product->update($updateData);
                DB::commit();
            }

            return response()->json([
                'message' => 'Active Product Variants Synced Successfully.',
                'data' => $responses,
            ]);
        } catch (Exception  $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function updateSourceVariants($variants = null) 
    {
        if ($variants == null) return null;
        foreach($variants as $var) {
            $variant = SourceVariant::where('sku', $var->sku)->first();
            if ($variant) {
                // $variant->shopifyPendingProcess = 1; // Must be 1 so the its soh will be syncing
                $variant->shopifyVariantId = $var->id;
                $variant->inventoryItemId = @$var->inventoryItem->id ?? null;
                $variant->isManualDeleted = 0;
                $variant->save();
            }
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

    public function deleteVariantFromShopify (Request $request) 
    {
        $debug = $request->get('debug') ?? 0;
        $limit = $request->get('limit') ?? 10;

        $sourceVariants = SourceVariant::with('sourceProduct')->where([
            'status' => 'ARCHIVED',
            'isManualDeleted' => 0 
        ])->take($limit)->get();
        if ($debug == 1) dd($sourceVariants);

        if ($sourceVariants->isEmpty()) {
            return response()->json(['message' => 'No ARCHIVED Product Variants Found!'], 404);
        }
        $res = [];
        foreach ($sourceVariants as $variant) {
            DB::beginTransaction();
            if ($variant->shopifyVariantId != null) continue; // SKIP, if NO shopifyVariantId

            $productId = $variant->sourceProduct->shopifyProductId;
            $variantIds = [ $variant->shopifyVariantId ];

            if ($productId == null || $variantIds == []) continue;
            $query = $this->deleteShopifyVariant($productId, $variantIds);
            if ($debug == 2) dd($query);

            $response = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
            $res[] = $response;
            if ($debug == 3) dd($response);

            $variant->isManualDeleted = 1;
            $variant->save();
            // $variant->delete(); // Remove Variant
            DB::commit();
        }
        return response()->json(['message' => 'success', 'response' => $res], 200);
    }

    public function variantsMutation($productData, $isUpdate = false, $processArchived = false)
    {
        $stratege = ($isUpdate) ? '' : ', strategy: REMOVE_STANDALONE_VARIANT';
        $varinats = $this->createVariantsMutationNew($productData, $isUpdate, $processArchived);
        if ($varinats == null) return null;

        $query = '
            mutation {
                productVariantsBulkCreate(
                    productId: "' . $productData->shopifyProductId . '",
                    variants: [ ' . $varinats['variants'] . ']
                    ' . $stratege . '
                ) {
                productVariants {
                    id
                    sku
                    displayName
                    title
                    inventoryItem {
                        id
                    }
                }
                userErrors {
                    code
                    field
                    message
                }
            }
        }';

        return $query;
    }

    private function createVariantsMutationNew($product, $isupdate = false, $processArchived = false)
    {

        $variants = [];
        $str = [];

        $variantDatas = [];
        if ($product->isMatrix == 1) {
            $sql = SourceVariant::where('product_id', $product->id)
                ->where('shopifyPendingProcess', 1);

            if ($processArchived) {
                $sql->where('isManualDeleted', 1);
            } else {
                $sql->where('isManualDeleted', 0);
            }
            $variantDatas = $sql->when($isupdate, fn($query) => $query->whereNotNull('shopifyVariantId'))
                ->get();
        } else {
            $variantDatas[0] = $product;
        }

        if ($variantDatas == []) return null;
        
        foreach ($variantDatas as $variant) {

            $regularPrice = $variant->priceWithTax ?? 0;
            $comparePrice = 0;
            $originalCompareAtPrice = $variant->compareAtPrice ?? 0;
            if ($variant->compareAtPrice < $regularPrice && $originalCompareAtPrice > 0) {
                $regularPrice = $originalCompareAtPrice;
                $comparePrice = $variant->priceWithTax;
            }

            // option values arrange
            $options = '';
            $optionValues = '';
            if ($product->isMatrix == 1) {
                if ($variant->color) {
                    $options = '{
                        optionName: "color",
                        name: "' . $variant->color . '"
                    }';
                }
                if ($variant->size) {
                    $options .= ', {
                        optionName: "size",
                        name: "' . $variant->size . '"
                    }';
                }
                $optionValues = ' optionValues:[' . ltrim($options, ',') . ']';
                $sku = $variant->sku;
            } else {
                $sku = $product->handle;
            }


            //  if (!in_array($optionvalue, $uniqueOptions)) {
            // $VID = $isupdate ? 'id:' . $variant->shopifyVariantID : '';

            $str = ' {
                    barcode: "' . $variant->barcode . '",
                    compareAtPrice: "'. $comparePrice .'",
                    price: "' . $regularPrice . '",
                    inventoryItem: {
                        sku: "' . $sku . '",
                        tracked: true,
                    },
                    inventoryPolicy: DENY,';

            if ($optionValues) {
                $str .= $optionValues;
            }
            $str .= ' }';
            $variants[] = $str;
        }

        echo 'Total No Of Variants ' . count($variants);
        return [
            'variants' => implode(',', $variants),
        ];
    }
}
