<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Shopify\App\Models\ReadShopify\ShopifyCustomer;
use Modules\Shopify\App\Models\ShopifyCursor;
use Modules\Shopify\App\Services\ErplyService\GiftCardService;

class ShopifyCustomerController extends Controller
{
    protected  GiftCardService  $giftCard;
    protected $live = 1;

    public function  __construct(GiftCardService  $giftCard)
    {
        $this->giftCard  = $giftCard;
    }

    public function index(Request $request)
    {
        $debug = $request->get('debug') ?? 0;
        $limit = $request->get('limit') ?? 5;
        try {
            $response = $this->giftCard->getShopifyCustomer($limit);

            if ($debug == 1) {
                dd($response);
            }

            $customers = $response->data->customers->edges ?? null;

            if ($customers == null) {
                return response()->json(['message' => 'No data found'], 404);
            }
            $lastKey = array_key_last($customers);

            foreach ($customers as $key => $customer) {

                DB::beginTransaction();
                $cursor = $customer->cursor;
                // check if it is last item
                $customer = $customer->node;
                if ($key === $lastKey) {
                    ShopifyCursor::where([
                        'clientCode' => $this->giftCard->getClientCode(),
                        'cursorName' => 'customerCursor'
                    ])->update(
                        ['cursor' => $cursor]
                    );
                    echo $cursor;
                    echo " cursor updated successfully";
                }
                // update

                $this->createorupdateCustomer($customer, $cursor);


                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
        }
    }

    public function createorupdateCustomer($customer, $cursor = null)
    {

        $customerData = [
            'shopify_customer_id' => $customer->id,
            'first_name' => $customer->firstName ?? null,
            'last_name' => $customer->lastName ?? null,
            'email' => $customer->email ?? null,
            'phone' => $customer->defaultAddress->phone ?? null,
            'address1' => $customer->defaultAddress->address1 ?? null,
            'address2' => $customer->defaultAddress->address2 ?? null,
            'city' => $customer->defaultAddress->city ?? null,
            'province' => $customer->defaultAddress->province ?? null,
            'country' => $customer->defaultAddress->country ?? null,
            'zip' => $customer->defaultAddress->zip ?? null,
            'cursor' => $cursor ?? null,
            'added_at' => $customer->createdAt ?? null,
        ];


        return  ShopifyCustomer::updateOrCreate(
            ['shopify_customer_id' => $customer->id],
            $customerData
        );
    }
}
