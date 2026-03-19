<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopifyV2;

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
    public function  index(Request $request)
    {
        $response = $this->giftCard->createOrUpdateGiftCard(null);
        dd($response);

        $customerId = $request->customerId ?? null;
        $limit = $request->limit ?? 2;
        $condition = [
            'pendingProcess' => 1
        ];
        $sortColumn = $request->sortColumn ?? 'lastModified';
        $sortType = $request->sortType ?? 'DESC';

        if ($customerId) {
            $condition['erplyCustomerID'] = $customerId;
        }

        $giftCards = $this->giftCard->metadata(
            GiftCard::query(),
            $limit,
            $condition,
            $sortColumn,
            $sortType
        );


        foreach ($giftCards as $data) {

            $response = $this->giftCard->createOrUpdateGiftCard($data);
            dd($response);

            $res = $response->data->giftCardCreate->giftCard->id ?? $response->data->giftCardUpdate->giftCard->id ?? null;

            if ($res) {
                echo "gift card created / Updated";
            } else {
                echo "gift card not created / Not Updated";
            }
        }
    }
}
