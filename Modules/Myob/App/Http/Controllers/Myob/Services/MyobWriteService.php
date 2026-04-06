<?php
namespace Modules\Myob\App\Http\Controllers\Myob\Services;

use Illuminate\Support\Facades\Log;
use Modules\Myob\App\Http\Controllers\Auth\AuthService;
use Modules\Myob\App\Models\SourceProduct;
use Modules\Myob\App\Trait\ResponseTrait;

class MyobWriteService
{
    use ResponseTrait;
    protected $api;

    public function __construct(AuthService $api)
    {
        $this->api = $api;
    }

    /**
     * Sync products from local database to MYOB
     */
    public function syncProductsToMyob($limit = 10)
    {
        try {
            // Get products that need to be synced to MYOB
            $products = SourceProduct::where('myob_pending_process', 1)
                ->orWhere('myob_updated', 1)
                ->limit($limit)
                ->get();

            foreach ($products as $product) {
                $this->syncProductToMyob($product);
            }

            return $this->successWithMessage("Products synced to MYOB successfully");
        } catch (\Exception $e) {
            Log::error('Error syncing products to MYOB: ' . $e->getMessage());
            return $this->errorWithMessage($e->getMessage());
        }
    }

    /**
     * Sync individual product to MYOB
     */
    private function syncProductToMyob($product)
    {
        try {
            // Check if product exists in MYOB
            $existingItem = $this->checkItemExists($product->code);

            if ($existingItem) {
                // Update existing item
                $response = $this->updateItemInMyob($product, $existingItem['UID']);
            } else {
                // Create new item
                $response = $this->createItemInMyob($product);
            }

            if ($response && ! isset($response['Errors'])) {
                // Update local product status
                $product->update([
                    'myob_pending_process' => 0,
                    'myob_updated'         => 0,
                    'myob_id'              => $response['UID'] ?? null,
                    'myob_last_sync'       => now(),
                ]);

                // Sync variants
                $this->syncVariantsToMyob($product);
            }

        } catch (\Exception $e) {
            Log::error("Error syncing product {$product->code} to MYOB: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync variants to MYOB
     */
    private function syncVariantsToMyob($product)
    {
        $variants = $product->variants()->where('myob_pending_process', 1)
            ->orWhere('myob_updated', 1)
            ->get();

        foreach ($variants as $variant) {
            $this->syncVariantToMyob($variant);
        }
    }

    /**
     * Sync individual variant to MYOB
     */
    private function syncVariantToMyob($variant)
    {
        try {
            // For MYOB, variants are typically handled as separate items with different codes
            $existingItem = $this->checkItemExists($variant->sku);

            if ($existingItem) {
                $response = $this->updateVariantInMyob($variant, $existingItem['UID']);
            } else {
                $response = $this->createVariantInMyob($variant);
            }

            if ($response && ! isset($response['Errors'])) {
                $variant->update([
                    'myob_pending_process' => 0,
                    'myob_updated'         => 0,
                    'myob_id'              => $response['UID'] ?? null,
                    'myob_last_sync'       => now(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Error syncing variant {$variant->sku} to MYOB: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if item exists in MYOB
     */
    private function checkItemExists($itemCode)
    {
        $query    = "Inventory/Item?\$filter=Number eq '$itemCode'";
        $response = $this->api->sendGetRequest($query);

        if (isset($response['Items']) && count($response['Items']) > 0) {
            return $response['Items'][0];
        }

        return null;
    }

    /**
     * Create new item in MYOB
     */
    private function createItemInMyob($product)
    {
        $data = [
            'Number'           => $product->code,
            'Name'             => $product->title,
            'Description'      => $product->descriptionHtml ?? '',
            'IsActive'         => $product->status == 1,
            'IsSold'           => true,
            'IsPurchased'      => true,
            'QuantityOnHand'   => $product->quantityOnHand ?? 0,
            'BaseSellingPrice' => $this->getProductPrice($product),
            'BuyingDetails'    => [
                'StandardCost' => $this->getProductCost($product),
            ],
        ];

        return $this->api->sendPostRequest('Inventory/Item', $data);
    }

    /**
     * Create variant in MYOB
     */
    private function createVariantInMyob($variant)
    {
        $data = [
            'Number'           => $variant->sku,
            'Name'             => $variant->title ?? $variant->sku,
            'Description'      => $variant->description ?? '',
            'IsActive'         => $variant->status == 'ACTIVE',
            'IsSold'           => true,
            'IsPurchased'      => true,
            'QuantityOnHand'   => $variant->quantityOnHand ?? 0,
            'BaseSellingPrice' => $variant->priceWithTax ?? $variant->price ?? 0,
            'BuyingDetails'    => [
                'StandardCost' => $variant->price ?? 0,
            ],
        ];

        return $this->api->sendPostRequest('Inventory/Item', $data);
    }

    /**
     * Update existing item in MYOB
     */
    private function updateItemInMyob($product, $myobId)
    {
        $data = [
            'UID'              => $myobId,
            'Number'           => $product->code,
            'Name'             => $product->title,
            'Description'      => $product->descriptionHtml ?? '',
            'IsActive'         => $product->status == 1,
            'QuantityOnHand'   => $product->quantityOnHand ?? 0,
            'BaseSellingPrice' => $this->getProductPrice($product),
            'BuyingDetails'    => [
                'StandardCost' => $this->getProductCost($product),
            ],
        ];

        return $this->api->sendPutRequest("Inventory/Item/$myobId", $data);
    }

    /**
     * Update variant in MYOB
     */
    private function updateVariantInMyob($variant, $myobId)
    {
        $data = [
            'UID'              => $myobId,
            'Number'           => $variant->sku,
            'Name'             => $variant->title ?? $variant->sku,
            'Description'      => $variant->description ?? '',
            'IsActive'         => $variant->status == 'ACTIVE',
            'QuantityOnHand'   => $variant->quantityOnHand ?? 0,
            'BaseSellingPrice' => $variant->priceWithTax ?? $variant->price ?? 0,
            'BuyingDetails'    => [
                'StandardCost' => $variant->price ?? 0,
            ],
        ];

        return $this->api->sendPutRequest("Inventory/Item/$myobId", $data);
    }

    /**
     * Sync SOH from local to MYOB
     */
    public function syncSOHToMyob($limit = 10)
    {
        try {
            // Get products/variants with SOH changes
            $products = SourceProduct::where('soh_pending_process', 1)
                ->limit($limit)
                ->get();

            foreach ($products as $product) {
                $this->syncProductSOHToMyob($product);
            }

            return $this->successWithMessage("SOH synced to MYOB successfully");
        } catch (\Exception $e) {
            Log::error('Error syncing SOH to MYOB: ' . $e->getMessage());
            return $this->errorWithMessage($e->getMessage());
        }
    }

    /**
     * Sync product SOH to MYOB
     */
    private function syncProductSOHToMyob($product)
    {
        try {
            $existingItem = $this->checkItemExists($product->code);

            if ($existingItem) {
                $data = [
                    'UID'            => $existingItem['UID'],
                    'QuantityOnHand' => $product->quantityOnHand ?? 0,
                ];

                $response = $this->api->sendPutRequest("Inventory/Item/{$existingItem['UID']}", $data);

                if ($response && ! isset($response['Errors'])) {
                    $product->update([
                        'soh_pending_process' => 0,
                        'myob_last_sync'      => now(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error("Error syncing SOH for product {$product->code}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get product price
     */
    private function getProductPrice($product)
    {
        // Get price from variants or product
        if ($product->variants()->count() > 0) {
            return $product->variants()->min('priceWithTax') ?? 0;
        }

        return $product->price ?? 0;
    }

    /**
     * Get product cost
     */
    private function getProductCost($product)
    {
        // Get cost from variants or product
        if ($product->variants()->count() > 0) {
            return $product->variants()->min('price') ?? 0;
        }

        return $product->cost ?? 0;
    }

    /**
     * Update individual item SOH in MYOB
     */
    public function updateItemSOH($payload, $clientCode = 'default')
    {
        // dump($payload);

        try {
            $itemUid  = $payload['Lines'][0]['Item']['UID'] ?? null;
            $quantity = $payload['Lines'][0]['Quantity'] ?? 0;
            dump($itemUid, $quantity, $payload);
            $response = $this->api->sendPostRequestForInventoryAdjustment(
                "/Inventory/Item/{$itemUid}",
                $payload,
                // $clientCode
            );
dd($response);
            if ($response && ! isset($response['Errors'])) {
                Log::info("MYOB item SOH updated successfully for UID: {$itemUid}, Quantity: {$quantity}");
                return ['success' => true, 'message' => 'SOH updated successfully'];
            } else {
                $error = $response['Errors'][0]['Message'] ?? 'Unknown error';
                Log::error("MYOB SOH update failed: " . $error);
                return ['success' => false, 'message' => $error];
            }

        } catch (\Exception $e) {
            Log::error("Error updating MYOB item SOH: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Sync individual MYOB product to local database
     */
    public function syncProductToLocal($myobProduct)
    {
        try {
            // Check if product already exists in local database
            $existingProduct = SourceProduct::where('code', $myobProduct->Code)->first();
            
            if ($existingProduct) {
                // Update existing product
                $existingProduct->update([
                    'title' => $myobProduct->Name,
                    'descriptionHtml' => $myobProduct->Description ?? '',
                    'quantityOnHand' => $myobProduct->QuantityOnHand ?? 0,
                    'myob_updated' => now(),
                    'myob_last_sync' => now(),
                ]);
                
                Log::info("Updated existing product: {$myobProduct->Code}");
                return ['success' => true, 'message' => 'Product updated'];
            } else {
                // Create new product
                $newProduct = SourceProduct::create([
                    'code' => $myobProduct->Code,
                    'title' => $myobProduct->Name,
                    'descriptionHtml' => $myobProduct->Description ?? '',
                    'quantityOnHand' => $myobProduct->QuantityOnHand ?? 0,
                    'status' => 1,
                    'outdoorGearStatus' => 1,
                    'myob_updated' => 1,
                    'myob_last_sync' => now(),
                    'sourceUpdatedDate' => now(),
                ]);
                
                Log::info("Created new product: {$myobProduct->Code}");
                return ['success' => true, 'message' => 'Product created'];
            }
            
        } catch (\Exception $e) {
            Log::error("Error syncing MYOB product to local: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
