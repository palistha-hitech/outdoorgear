<?php
namespace Modules\Myob\App\Http\Controllers\Myob\Services;

use App\Http\Controllers\Controller;
use Modules\Myob\App\Http\Controllers\Auth\AuthService;
use Modules\Myob\App\Models\SourceProduct;
use Modules\Myob\App\Models\SourceVariant;
use Modules\Myob\App\Trait\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $number = $req->number ?? null;

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
            $query = "Inventory/Item?\$filter=startswith(Number,'WHL')";
        }

        $res = $this->api->sendGetRequest($query);
// dd($res);
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

                //matrix product
                if (count($parts) == 2) {

                    $parentProduct = $this->saveSourceProduct($item);
//  dd($parentProduct);
                }
                // product varient
                if (count($parts) == 3 && $parentProduct) {

                    $this->saveSourceVariant($item, $parentProduct);
                }
            }
        }
        // dd($parentProduct);
        return response("Wholesale MYOB products saved to Shopify tables in draft status.");
    }
    private function saveSourceProduct($item)
    {
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
    $parts = explode('-', $item['Number']);
    $color = $parts[2] ?? null;
    $quantityOnHand = $item['QuantityOnHand'] ?? 0;

    // 1. Explicitly clear and Scope locations to THIS specific item UID
    $locations = [];

    // Check if MYOB actually returned LocationDetails for this specific UID
    if (!empty($item['LocationDetails'])) {
        foreach ($item['LocationDetails'] as $locDetail) {
            // Only add if the location data belongs to this item context
            $locations[] = [
                'uid'        => $locDetail['Location']['UID'] ?? null,
                'name'       => $locDetail['Location']['Name'] ?? null,
                'identifier' => $locDetail['Location']['Identifier'] ?? null,
                'quantity'   => $locDetail['QuantityOnHand'] ?? 0,
            ];
        }
    }

    $existing = SourceVariant::where('variantId', $item['UID'])->first();
    $isSohChanged = false;

    if (!$existing || $existing->quantityOnHand != $quantityOnHand) {
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
            // 3. Ensure we cast to array or null so JSON encoding works correctly
            'location'              => !empty($locations) ? $locations : null,
            'status'                => $item['IsActive'] ? 'ACTIVE' : 'ARCHIVED',
        ]
    );

    if ($isSohChanged) {
        $parentProduct->update(['outdoorGearSohStatus' => 1]);
    }

    return $variant;
}
public function handleAccessCode(Request $request)
{
    dd('test');
    $code = $request->code;

    $response = Http::asForm()->post('https://secure.myob.com/oauth2/v1/authorize', [
        'client_id' => env('MYOB_CLIENT_ID'),
        'client_secret' => env('MYOB_CLIENT_SECRET'),
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => env('MYOB_REDIRECT_URI'),
    ]);

    $data = $response->json();

    // Save tokens in DB
    // access_token, refresh_token

    return $data;
}
}
