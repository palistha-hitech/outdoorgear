<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Shopify\App\Models\ErplyModel\GiftCard;
use Modules\Shopify\App\Services\ErplyService\GiftCardService;

class GiftCardController extends Controller
{
    protected  GiftCardService  $giftCard;

    public function  __construct(GiftCardService  $giftCard)
    {
        $this->giftCard  = $giftCard;
    }

    public function index(Request $request)
    {
        $condition = [
            'pendingProcess' => 1,
        ];

        $debug = $request->debug ?? 0;

        if ($request->code) $condition['code'] = $request->code;

        // if ($request->customerId) $condition['erplyCustomerID'] = $request->customerId;
        // if ($request->customerId) $condition['redeemingCustomerId'] = $request->customerId;

        $limit = $request->limit ?? 2;
        $sortColumn = $request->sortColumn ?? 'lastModified';
        $sortType = $request->sortType ?? 'DESC';

        $giftCards = $this->giftCard->metadata(
            GiftCard::query()->where('balance', '>', 0)->where('expirationDate', '>=', \Carbon\Carbon::now()),
            $limit,
            $condition,
            $sortColumn,
            $sortType
        );

        // Test
        if ($debug == 1) {
            dd($giftCards);
        }

        // Update Pending Process from 2 to 1(Pending)
        if ($giftCards->isEmpty()) {
            GiftCard::where('pendingProcess', '=', 2)->update(['pendingProcess' => 1]);
        }

        if ($giftCards->isNotEmpty()) {
            foreach ($giftCards as $data) {
                $data->pendingProcess = 2; // Processing
                $isDuplicate = $this->giftCard->checkForDuplication($data);
                if ($isDuplicate == true) {
                    $data->pendingProcess = 4; // Duplicate
                    $data->save();
                    echo "Duplicate Gift Card Code";
                    continue;
                }
                $newGiftCode = $this->giftCard->formatGiftCode($data->code); // get formated gift card code
                $data->code = $newGiftCode;

                $response = $this->giftCard->createOrUpdateGiftCard($data, $debug);
                // dump($response);

                if (is_array($response) && array_key_exists('unprocessable', $response)) {
                    $data->pendingProcess = 4; // Not processable
                    $data->ShopifyErrorLog = $response['message']; // reason
                    $data->save();
                    continue;
                }
                $userErrors = $response->errors[0] ?? $response->userErrors[0] ?? null;
                if ($userErrors) {
                    $data->pendingProcess = 3; // Error
                    $data->ShopifyErrorLog = json_encode($response->errors);
                    $data->save();
                    echo "Error Occurred";
                    continue;
                }
                $res = $response->data->giftCardCreate->giftCard->id ?? $response->data->giftCardUpdate->giftCard->id ?? null;
                if ($res) {
                    $data->pendingProcess = 0; // Sync
                    $data->shopifyGiftCardId = $res;
                }
                $data->save(); // update gift card details
                echo "Gift Card Created / Updated";
            }
        }
    }
}
