<?php
namespace Modules\Myob\App\Http\Controllers\Myob\Services;

use App\Http\Controllers\Controller;
use Modules\Myob\App\Http\Controllers\Auth\AuthService;
use Modules\Myob\App\Models\SourceProduct;
use Modules\Myob\App\Models\SourceVariant;
use Modules\Myob\App\Trait\ResponseTrait;
use Modules\Shopify\App\Models\ShopifyCursor;

class InventoryService extends Controller
{
    use ResponseTrait;
    protected $api;
    public function __construct(AuthService $api)
    {
        $this->api = $api;
    }

    public function getMatrixProducts($req)
    {

        info("Fetching wholesale items from MYOB...");
        $number             = $req->number ?? null;
        $latestLastModified = null;
        if ($number) {
            $parts = explode('-', $number);

            if (count($parts) >= 2) {
                $parentSKU = $parts[0] . '-' . $parts[1];
                $filter    = urlencode("startswith(Number,'$parentSKU')");
            } else {
                $filter = urlencode("Number eq '$number'");
            }

            $query = "Inventory/Item?\$filter={$filter}";
        } else {
            $lastSync = ShopifyCursor::where('cursorName', 'myob_lastmodified')->value('cursor');

            //      $query = "Inventory/Item?\$filter=startswith(Number,'WHL') AND LastModified ge datetime'$lastSync'";
            $sync = $lastSync;

            $filter = "startswith(Number,'WHL') and LastModified ge datetime'$sync'";

            $query = "Inventory/Item?\$filter=" . urlencode($filter);

        }

        $res = $this->api->sendGetRequest($query);

        $products = [];
        if (is_array($res) && isset($res['Items'])) {
            // Filter wholesale items (Number starts with WHL)
            $whlItems = array_filter($res['Items'], function ($item) {
                return isset($item['Number']) && strpos($item['Number'], 'WHL') === 0;
            });
            $items = $res['Items'];

            // Group items by parent SKU (prefix before first dash)
            $matrixProducts = [];

            foreach ($items as $item) {
                // dd($item);
                $parts = explode('-', $item['Number']);

                $parentSKU = $parts[0] . '-' . $parts[1];

                if (! isset($matrixProducts[$parentSKU])) {
                    $matrixProducts[$parentSKU] = [
                        'parent_sku' => $parentSKU,
                        'variants'   => [],
                    ];
                }

                $matrixProducts[$parentSKU]['variants'][] = $item;
                // dump($matrixProducts[$parentSKU]);
            }

            $products = array_values($matrixProducts);

        } elseif (is_array($res) && isset($res['Number'])) {

            if (strpos($res['Number'], 'WHL') === 0) {
                $products[] = [
                    'parent_sku' => $res['Number'],
                    'variants'   => [$res],
                ];
            }
        }
// dump($products);

        foreach ($products as $matrixProduct) {

            $parentProduct = null;
            $currentDate   = $item['LastModified'];
            foreach ($matrixProduct['variants'] as $item) {
// dd($item);
                $parts = explode('-', $item['Number']);
                if (! $latestLastModified || strtotime($currentDate) > strtotime($latestLastModified)) {
                    $latestLastModified = $currentDate;
                }
                if (count($parts) == 2) {

                    $parentProduct = $this->saveSourceProduct($item);
                    $currentDate   = $item['LastModified'];

                    if (! $latestLastModified || strtotime($currentDate) > strtotime($latestLastModified)) {
                        $latestLastModified = $currentDate;
                    }

                }

                // product varient
                if (count($parts) == 3) {
                    $parentProduct = SourceProduct::where('handle', $parts[0] . '-' . $parts[1])->first();
                    // $parentProductId=SourceProduct::where('handle',$parts[0] . '-' . $parts[1])->value('id');

                    $variantProduct = $this->saveSourceVariant($item, $parentProduct);
                    dump($variantProduct->sourceUpdatedDate);

                }
            }
        }
        //  FINAL CURSOR UPDATE (ONLY ONCE)
        if ($latestLastModified) {
            ShopifyCursor::updateOrCreate(
                ['cursorName' => 'myob_lastmodified'],
                ['cursor' => $latestLastModified]
            );

            dump('Final cursor updated: ' . $latestLastModified);
        }

        return response("Wholesale MYOB products saved successfully.");
    }
    private function saveSourceProduct($item)
    {
        dump('sourceproduct' . $item['Number']);
        $product = SourceProduct::updateOrCreate(
            ['stockId' => $item['UID']],
            [
                'handle'                => $item['Number'],
                'code'                  => $item['Number'],
                'title'                 => $item['Name'],
                'descriptionHtml'       => $item['Description'] ?? '',
                'productType'           => 'wholesale',
                'vendor'                => 'MYOB',
                'status'                => $item['IsActive'] ? 1 : 0,
                'isMatrix'              => 1,
                'quantityOnHand'        => $item['QuantityOnHand'] ?? 0,
                'shopifyPendingProcess' => 1,
                'sourceUpdatedDate'     => $item['LastModified'],
                'sourceAddedDate'       => now(),
                'outdoorGearStatus'     => 1,

            ]
        );

        info("Parent stored: " . $item['Number']);

        return $product;
    }

    private function saveSourceVariant($item, $parentProduct)
    {
        dump($item);
        $parts = explode('-', $item['Number']);
        // dd($parts);
        $color          = $parts[2] ?? null;
        $quantityOnHand = $item['QuantityOnHand'] ?? 0;

        // 1. Explicitly clear and Scope locations to THIS specific item UID
        $locations = [];

        $existing     = SourceVariant::where('variantId', $item['UID'])->first();
        $isSohChanged = false;

        if (! $existing || $existing->quantityOnHand != $quantityOnHand) {
            $isSohChanged = true;
        }

        // 2. Update ONLY the record matching this specific variantId (UID)
        $variant = SourceVariant::updateOrCreate(
            ['variantId' => $item['UID']],
            [
                'product_id'            => $parentProduct->id,
                'sku'                   => $item['Number'],
                'price'                 => $item['BuyingDetails']['StandardCost'] ?? 0,
                'priceWithTax'          => $item['BaseSellingPrice'] ?? 0,
                'color'                 => $color,
                'size'                  => null,
                'weight'                => 0,
                'weightUnit'            => 'KILOGRAMS',
                'quantityOnHand'        => $quantityOnHand,
                'shopifyPendingProcess' => 1,
                'sohPendingProcess'     => 1,
                'pricePendingProcess'   => 1,
                'sourceUpdatedDate'     => $item['LastModified'],
                // 3. Ensure we cast to array or null so JSON encoding works correctly
                // 'location'              => !empty($locations) ? $locations : null,
                'status'                => $item['IsActive'] ? 'ACTIVE' : 'ARCHIVED',
            ]
        );
// dd($variant);
        if ($isSohChanged) {
            $parentProduct->update(['outdoorGearSohStatus' => 1]);
        }

        return $variant;
    }

}
