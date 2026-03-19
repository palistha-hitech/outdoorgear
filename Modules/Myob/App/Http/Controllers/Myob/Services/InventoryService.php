<?php
namespace Modules\Myob\App\Http\Controllers\Myob\Services;

use Modules\Myob\App\Http\Controllers\Auth\AuthService;
use App\Http\Controllers\Controller;
use Modules\Myob\App\Models\SourceProduct;
use Modules\Myob\App\Models\SourceVariant;
use Modules\Myob\App\Trait\ResponseTrait;

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

        // Fetch MYOB items
        if (isset($req->code)) {
            // Single item fetch
            $res = $this->api->sendGetRequest("Inventory/Item/" . $req->code);
        } else {
            // Apply filter directly in API request
            $query = "Inventory/Item?\$filter=startswith(Number,'WHL')";
            $res   = $this->api->sendGetRequest($query);
        }
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
                $parts     = explode('-', $item['Number']);
                $parentSKU = $parts[0] . '-' . $parts[1];

                if (! isset($matrixProducts[$parentSKU])) {
                    $matrixProducts[$parentSKU] = [
                        'parent_sku' => $parentSKU,
                        'variants'   => [],
                    ];
                }

                $matrixProducts[$parentSKU]['variants'][] = $item;
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

        foreach ($products as $matrixProduct) {

            $parentProduct = null;

            foreach ($matrixProduct['variants'] as $item) {

                $parts = explode('-', $item['Number']);

                /*
        ---------------------------------
        Parent Product
        ---------------------------------
        */
                if (count($parts) == 2) {

                    $parentProduct = SourceProduct::updateOrCreate(
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
                }

                /*
        ---------------------------------
        Variant Product
        ---------------------------------
        */
                if (count($parts) == 3 && $parentProduct) {

                    $color = $parts[2];
                    dump($item);
                    SourceVariant::updateOrCreate(
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
                            'quantityOnHand'        => $item['QuantityOnHand'] ?? 0,
                            'shopifyPendingProcess' => 1,
                            'sohPendingProcess'     => 1,
                            'pricePendingProcess'   => 1,
                            'status'                => $item['IsActive'] ? 'ACTIVE' : 'ARCHIVED',
                        ]
                    );

                    info("Variant stored: " . $item['Number']);
                }
            }
        }
        // dd($parentProduct);
        return response("Wholesale MYOB products saved to Shopify tables in draft status.");
    }

}
