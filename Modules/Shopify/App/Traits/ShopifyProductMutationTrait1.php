<?php

namespace Modules\Shopify\App\Traits;

use Exception;
use GuzzleHttp\Client;
use Modules\Shopify\App\Models\ShopifyCursor;
use Illuminate\Support\Str;
use Modules\Shopify\App\Http\Controllers\Middleware\Erply\LocationController;
use Modules\Shopify\App\Models\Source\SourceLocation;
use Modules\Shopify\App\Models\Source\SourceSoh;
use Modules\Shopify\App\Models\Source\SourceVariant;

use function Laravel\Prompts\error;

trait ShopifyProductMutationTrait
{
    protected $live = 1;
    use ShopifyTrait;

    public function __construct() {}

    # this function is used to create or update product as per the shopifyProductId
    public function createOrUpdateProductMutation($productData)
    {
        // dd($productData);
        $mutation = ' mutation {';

        if ($productData->shopifyProductId) {
            $productExists = $this->productExistsByShopifyId($productData->shopifyProductId);
            if ($productExists['exist'] != 1) {
                $productExists = $this->productExists($productData->handle);
            }
        } else {
            $productExists = $this->productExists($productData->handle);
        }

        print_r($productExists);
        if ($productExists['exist'] == 1) {
            $mutation .= 'productUpdate(input:{';
            $mutation .= 'id:"' . $productExists['handleID'] . '",';
        } else {
            $mutation .= 'productCreate(input:{';
            # return 1;
        }

        $mutation .= 'title:"' . $this->escapeDoubleQuotes($productData->title) . '",';
        $mutation .= 'descriptionHtml:"' . $this->sanitizeString($productData->descriptionHtml) . '",';
        $mutation .= 'vendor:"' . $productData->vendor . '",';
        $mutation .= 'handle:"' . $this->filterHandle($productData->handle) . '",';
        #  $mutation .= 'productType:"' . $productData->productType . '",';
        $status = ($productData->status == 1 ? 'ACTIVE' : 'ARCHIVED');
        if (!empty($productData->productTags)) {
            $mutation .= 'tags:[' . $productData->productTags . '],';
        }

        $variations = $this->createVariantsMutation($productData->variants, $productData->id);
        $checkSoh = $this->getSumOfSoh($productData->variants);
        if ($checkSoh <= 0) {
            $status = 'ARCHIVED';
        }
        if (empty($variations['variants']) && $productData->isMatrix == 1) {
            $status = 'ARCHIVED';
        }
    
      
        $mutation .= 'status:' . $status . ',';
        // check soh for all varinats


        // if ($productData->isMatrix == 1 && !empty($variations['optionName'])) {

        //     $mutation .= 'options:[' . $variations['optionName'] . '],';
        // }
        // #  dd($variations, $variations['optionName'], $variations['variants']);
        // if (!empty($variations['variants'])) {

        //     $mutation .= 'variants:[' . $variations['variants'] . ']'; // need to call createVariantsMutation function
        // }


        $mutation .= '}) {
                product {
                    id
                    handle
                    variants(first:100) {
                        edges {
                            node {
                                id
                                sku
                                title
                                inventoryItem{
                                    id
                                }

                            }
                        }
                    }
                }
             userErrors {
                field
                message
             }
            }
        }';
// dd($mutation);
        return $mutation;
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
    public function productExistsByShopifyId($shopifyProductId)
    {

        $checkquery = '{
            product(id:"' . $shopifyProductId . '")
                {
                    id
                    title
                }
            }';

        $response = $this->sendShopifyQueryRequestV2("POST", $checkquery, $this->live);
        print_r($response);
        $handle['exist'] = 0;
        $handle['handleID'] = '';
        if ($response->data->product != null) {
            $handle['handleID'] = $response->data->product->id;
            $handle['exist'] = 1;
        }
        return $handle;
    }
    public function productExists($handel)
    {
        $code = $this->filterHandle($handel);

        $checkquery = '{
            productByHandle(handle: "' . $code . '")
                {
                    id
                    title
                    hasOnlyDefaultVariant
                    totalVariants
                    variants(first:1){
                    edges{
                        node{
                        id
                        }
                    }
                    }
                }
            }';

        $response = $this->sendShopifyQueryRequestV2("POST", $checkquery, $this->live);

        $handle['exist'] = 0;
        $handle['handleID'] = '';
        if ($response->data->productByHandle != null) {
            $handle['handleID'] = $response->data->productByHandle->id;
            $handle['exist'] = 1;
            // $handle['hasOnlyDefaultVariant'] = $response->data->productByHandle->hasOnlyDefaultVariant;
            // if ($response->data->productByHandle->hasOnlyDefaultVariant == true) {
            //     $handle["defaultVariantID"] = $response->data->productByHandle->variants->edges[0]->node->id;
            // }
        }
        return $handle;
    }
    private function filterHandle($handle)
    {
        $result = Str::lower($handle);
        $result  = str_replace(' ', '', $result);
        $result  = str_replace('.', '-', $result);
        $result  = str_replace('+', '-', $result);

        return $result;
    }
    private function escapeDoubleQuotes(string $input): string
    {

        $input = str_replace("\\", "\\\\", $input);
        $input = str_replace('"', '\"', $input);
        // $input = str_replace("'", "\'", $input);

        return $input;
    }

    private function sanitizeString($string)
    {

        $sanitizedString = preg_replace('/\\\\(?![0-7]{3})/', '', $string);
        $productDescription = mb_convert_encoding($sanitizedString, 'ASCII', 'UTF-8');
        $productDescription = str_replace('?', "", $productDescription);
        // Ensure UTF-8 encoding
        $productDescription = $this->escapeDoubleQuotes($productDescription);
        return $productDescription;
    }
    # this function will prepare the variants in structured way
    private function createVariantsMutation($variantDatas, $productId)
    {
        $variants = [];
        $str = [];
        $uniqueOptions = [];
        $optionName = [];

        if (count($variantDatas) <= 0) {

            $variantDatas = SourceVariant::where('product_id', $productId)->get();
        }

        foreach ($variantDatas as $variant) {
            $options = [];


            if (isset($variant->color)) {

                $options[] = '"' . $variant->color . '"';
                if (!in_array('"Color"', $optionName)) {
                    $optionName[] = '"Color"';
                }
            }
            if (isset($variant->size)) {

                $options[] = '"' . $variant->size . '"';
                if (!in_array('"Size"', $optionName)) {
                    $optionName[] = '"Size"';
                }
            }

            $outputArray = collect($options)->map(function ($item) {

                return strval($item);
            })->toArray();



            $optionvalue = implode(',', $outputArray);

            if (!in_array($optionvalue, $uniqueOptions)) {
                $regularPrice = $variant->priceWithTax;
                $comparePrice = 0;
                $originalCompareAtPrice = $variant->compareAtPrice ?? 0;
                if ($variant->compareAtPrice < $regularPrice && $originalCompareAtPrice > 0) {
                    $regularPrice = $originalCompareAtPrice;
                    $comparePrice = $variant->priceWithTax;
                }

                $str = '{';

                $str .= 'sku: "' . $variant->sku . '",';

                $str .= 'price: "' . $regularPrice . '",';

                $str .= 'compareAtPrice: "' . $comparePrice . '",';

                $str .= 'inventoryPolicy:DENY,';

                #  $str .= 'weight:' . $variant->weight . ',';

                #   $str .= 'weightUnit:' . $variant->weightUnit . ',';

                $str .= 'barcode:"' . $variant->barcode . '",';

                $str .= 'inventoryItem:{';

                $str .= 'tracked:true';

                $str .= '},';

                if (!empty($optionvalue)) {

                    $str .= 'options:[' . $optionvalue . ']';
                }

                $str .= '}';

                $variants[] = $str;

                $uniqueOptions[] = $optionvalue;
            }
        }
        $optionName = implode(',', $optionName);
        echo "Total No Of Variants " . count($variants);
        return [
            'variants' => implode(',', $variants),
            'optionName' => $optionName
        ];
    }

    # this fucntion will upadte the soh of the variants in Shopify using inventoryItemId
    public function updateProductSohMutation($sourceSohs, $inventoryItemId, $variantId, $sourceVarinatId)
    {
        $sohQuery = $this->checkSohQuery($variantId);
        #dd($sohQuery);
        $response = $this->sendShopifyQueryRequestV2('POST', $sohQuery, $this->live);
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
        dd($totalActivatedLocation);
        if ($totalActivatedLocation == 8) {
            $mutation = '
            mutation {
                inventoryAdjustQuantities(
                    input: {
                        changes: [';
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
                $mutation .= '{
                                delta: ' . $sohDiff  . ',
                                locationId: "' . $locationId . '",
                                inventoryItemId: "' . $inventoryItemId . '"
                            },';
            }

            $mutation .= '         ],
                        reason:"correction",
                        name:"available",
                    }
                ) {
                    inventoryAdjustmentGroup {
                        id

                    }
                    userErrors {
                        field
                        message
                    }
                }
            }';
            return [
                'status' => 2,
                'mutation' => $mutation,
                'sumOfSoh' => $sum,
                'locationActivated' => 0
            ];
        } else {

            # dd(" Need to activate locations");
            $acticatedLocation = [];
            foreach ($res as $al) {
                $acticatedLocation[] = $al->node->location->id;
            }
            $needtoActivates = SourceLocation::whereNotIn('shopifyLocationId', $acticatedLocation)->get();
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

    private function activateInventoryLocation($inventoryItemId, $shopifyLocationId, $stock)
    {
        $stock = $stock < 0 ? 0 : $stock;

        $mutation = 'mutation {
            inventoryActivate(
                inventoryItemId: "' . $inventoryItemId . '",
                locationId: "' . $shopifyLocationId . '",
                available :' . $stock . '

            ) {
              inventoryLevel {
                id
                quantities(names:"available") {
                  id
                  quantity
                }
                item {
                  id
                }
                location {
                  id
                }
              }
            }
          }';

        $response = $this->sendShopifyQueryRequestV2('POST', $mutation, $this->live);
        dump($response);
    }

    private function returnSohDiff($sohinDB, $locationId, $resArray, $inventoryItemId)
    {
        $countLocation = count($resArray);
        $sohDiff = 0;
        if ($countLocation == 8) {
            foreach ($resArray as $res) {

                if ($res->node->location->id === $locationId) {
                    $sohinDB = $sohinDB < 0 ? 0 : $sohinDB;


                    $sohinShopify = $res->node->quantities[0]->quantity; // kailash
                    if ($sohinShopify > $sohinDB) {

                        $sohDiff = 0 - ($sohinShopify - $sohinDB);
                    }

                    if ($sohinShopify < $sohinDB) {

                        $sohDiff =  $sohinDB - $sohinShopify;
                    }

                    return $sohDiff;
                }
            }
            return $sohDiff;
        }
        //  else {
        //     $acticatedLocation = [];
        //     foreach ($resArray as $res) {
        //         $acticatedLocation[] = $res->node->location->id;
        //     }
        //     $needtoActivates = SourceLocation::whereNotIn('shopifyLocationId', $acticatedLocation)->get();
        //     foreach ($needtoActivates as $needtoActivate) {
        //         $this->activateInventoryLocation($inventoryItemId, $needtoActivate->shopifyLocationId, $sohinDB);
        //     }
        // }
    }

    # create and update product media this function need to improve more
    public function createOrUpdateMediaMutation($productImages)
    {
        $mutation = 'mutation{';

        // if ($productImages[0]->shopifyMediaId) {

        //     $mutation .= 'productUpdateMedia(';
        // } else {
        // }

        $mutation .= 'productCreateMedia(';
        $mutation .= ' media: [';
        foreach ($productImages as $item) {

            $mutation .= '   {';
            $mutation .= ' alt:"' . $item->title ?? '' . '",';
            if ($item->shopifyMediaId) {

                $mutation .= ' id:"' . $item->shopifyMediaId . '",';
                $mutation .= ' previewImageSource:"' . $item->image . '",';
            } else {

                $mutation .= ' mediaContentType:IMAGE,';
                $mutation .= ' originalSource:"' . $item->image . '",';
            }

            $mutation .= ' },';
        }
        $mutation .= '],
                productId:"' . $productImages->shopifyProductId . '"
            ) {
                media {
                    id
                }
            userErrors {
                field
                message
            }
            }
        }';

        return $mutation;
    }

    # attach product variants to the medias
    public function attachMediaMutation($ShopifyProductId, $mediaIds, $variantId)
    {
        $mutation = 'mutation {';

        $mutation .= 'productVariantAppendMedia(';
        $mutation .= 'productId: "' . $ShopifyProductId . '"';
        $mutation .= 'variantMedia: [';
        foreach ($mediaIds as $mediaId) {

            $mutation .= '   {';
            $mutation .= 'mediaId:"' . $mediaId . '",';
            $mutation .= 'productMediaIds:[' . $variantId . ']';
            $mutation .= '   },';
        }
        $mutation .= ']';
        $mutation .= '}) {
                media {
                    id
                }
            userErrors {
                field
                message
            }
            }
        }';

        return $mutation;
    }

    # this function will change the status of the product in Shopify
    public function changeStatusMutation($shopifyProductId, $status)
    {
        $mutation = 'mutation {';
        $mutation .= 'productUpdate(input:{';
        $mutation .= 'id:"' . $shopifyProductId . '",';
        
        $mutation .= 'status:' . $status . '';
        $mutation .= '}) {
                product {
                    id
                    status
                }
            userErrors {
                field
                message
            }
            }
        }';
dd($mutation);
        return $mutation;
    }

    # this function will delete the product from  Shopify
    public function deleteProductMutation($shopifyProductId)
    {
        $mutation = 'mutation {';
        $mutation .= 'productDelete(input:{';
        $mutation .= 'id:"' . $shopifyProductId . '"';
        $mutation .= '}) {
                product {
                    id
                }
            userErrors {
                field
                message
            }
            }
        }';

        return $mutation;
    }
    # this function will delete the product's variants from  Shopify
    public function deleteVariantMutation($variantId)
    {
        $mutation = 'mutation {';
        $mutation .= 'variantDelete(input:{';
        $mutation .= 'id:"' . $variantId . '"';
        $mutation .= '}) {
                variant {
                    id
                }
            userErrors {
                field
                message
            }
            }
        }';

        return $mutation;
    }
    public function updatePriceMutation($productId, $variants)
    {
        $mutation = '
        mutation {
               productVariantsBulkUpdate(productId:"' . $productId . '", variants:[';
        foreach ($variants as $variant) {
            // Check if the variant has a shopifyVariantId
            if ($variant->shopifyVariantId || isset($variant->shopifyVariantId)) {
                $regularPrice = $variant->priceWithTax;
                $comparePrice = 0;
                if ($variant->compareAtPrice < $regularPrice) {
                    $regularPrice = $variant->compareAtPrice;
                    $comparePrice = $variant->priceWithTax;
                }

                $mutation .= '{';
                $mutation .= 'id:"' . $variant->shopifyVariantId . '",';
                $mutation .= 'price:"' . $regularPrice . '",';
                $mutation .= 'compareAtPrice:"' . $comparePrice . '"';
                $mutation .= '},';
            }
        }
        // Remove the last comma if there's at least one variant
        if (!empty($variants)) {
            $mutation = rtrim($mutation, ',');
        }
        // Complete the mutation string
        $mutation .= ']) {
            product {
                id
            }
            productVariants {
                id
                price
                compareAtPrice
            }
            userErrors {
                field
                message
            }
        }
    }';

        return $mutation;
    }

    public function linkChannel($shopifyProductID)
    {

        $livePublication = "gid://shopify/Publication/42605838405";
        $stagingPublication = "gid://shopify/Publication/76882575499";



        // $shopifyProductID = "gid://shopify/ProductVariant/41855293882507";
        $linkQuery = 'mutation {
            pub' . rand() . ': publishablePublish(
            id: "' . $shopifyProductID . '",
            input:
                [{
                    publicationId: "gid://shopify/Publication/118975562009",
                },
                {
                    publicationId: "gid://shopify/Publication/153784877337",
                },
                {
                    publicationId: "gid://shopify/Publication/153794773273",
                }]

            ){
            publishable {
                publicationCount
            }}}';

        $channelRes = $this->sendShopifyQueryRequestV2("POST", $linkQuery, $this->live);
        print_r($channelRes);
    }
}
