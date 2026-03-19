<?php

namespace Modules\Shopify\App\Services\ErplyService;

use App\Models\Shopify\ShopifyCustomer;
use GuzzleHttp\Client;
use Modules\Shopify\App\Models\ErplyModel\GiftCard;
use Modules\Shopify\App\Traits\ShopifyTrait;
use App\Models\PAEI\GiftCard as NewSystemGiftCard;

class GiftCardService
{
    protected $live = 1;
    use ShopifyTrait;
    public  function  metadata($model, $limit = 2, $condition = null, $sortColumn  = null, $sortOrder = 'DESC')
    {
        return $model
            ->when($condition != null, function ($query) use ($condition) {
                $query->where($condition);
            })

            ->when($sortColumn != null, function ($query) use ($sortColumn, $sortOrder) {
                $query->orderBy($sortColumn, $sortOrder);
            })

            ->take($limit)
            ->get();
    }

    public function createOrUpdateGiftCard($giftCard, $debug = 0)
    {
        if (!$giftCard->code || !$giftCard->value) return ['unprocessable' => true, 'message' => 'Code/Balance is 0/NULL'];

        if ($giftCard->shopifyGiftCardId == null) {
            $res = $this->searchGiftCard($giftCard->code);
            if ($debug == 3) dd($res);
            $shopifyId = $res->data->giftCards->edges[0]->node->id ?? null;
            $giftCard->shopifyGiftCardId = $shopifyId;
        }
        
        if (isset($giftCard->shopifyGiftCardId) && $giftCard->shopifyGiftCardId) {
            $gid = $giftCard->shopifyGiftCardId;
            $query = 'mutation { giftCardUpdate( id:"' . $gid . '", input:{';
        } else {
            if ($giftCard->value != $giftCard->balance) {
                return [
                    'unprocessable' => true,
                    'message' => 'Remaining balance and initial balance not same, while creating for FIRST time'
                ];
            }
            $query = 'mutation {
                giftCardCreate(input: {
                    code: "' . $giftCard->code . '",
                    initialValue: "' . $giftCard->value . '",';
        }

        if ($giftCard->expirationDate && ($giftCard->expirationDate != '0000-00-00')) $query .= 'expiresOn: "' . $giftCard->expirationDate . '",';

        if ($giftCard->redeemingCustomerId) {

            $customer = ShopifyCustomer::where('erplyCustomerID', $giftCard->redeemingCustomerId)->first();
            if (!$customer) {
                return ['unprocessable' => true, 'message' => 'Might be ERPLY Created Customers/ Shopify Customers not synced into ERPLY']; // Might be ERPLY Created Customers/ Shopify Customers not synced into ERPLY 
            } 
            if ($customer->newSystemMemberID == null) return ['unprocessable' => true, 'message' => 'Redeeming Customer might be ERPLY Created Customer/ Shopify Customer which is not synced into ERPLY'];

            // Check if already redeemed by any customer
            $is_redeemed = $this->checkGiftcard($giftCard);
            if ($is_redeemed == false) {
                if ($customer->newSystemMemberID) $query .= 'customerId: "' . $customer->newSystemMemberID . '",';
            }
        }

        if ($giftCard->information) $query .= 'note: "' . $giftCard->information . '",';

        $query .= '}) {
                    giftCard {
                        id
                        expiresOn
                        note
                    }
                    userErrors {
                        message
                        field
                    }
                }}';
                
        if ($debug == 2) dd($query);
        return $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
    }

    public function checkGiftcard($card) 
    {
        $gift = NewSystemGiftCard::where('code', $card->code)->first();;
        if ($gift) {
            $giftCardId = $gift->shopifyGiftCardId;
        }

        if ($giftCardId == null) return false;

        $giftcard_query = 'query {
            giftCard(id: "' . $giftCardId . '") {
                initialValue
                balance {
                    amount
                    currencyCode
                }
                customer {
                    id
                }
                note
                expiresOn
                createdAt
            }
        }';

        $response = $this->sendShopifyQueryRequestV2("POST", $giftcard_query, $this->live);
        if (isset($response->data->giftCard->customer->id)) {
            return true;
        }
        return false;
    }

    public  function  getShopifyCustomer($limit)
    {
        $clientCode = $this->getClientCode();
        $cursor = $this->getCursor($clientCode, 'customerCursor', $this->live);

        $after = $cursor ? ', after: "' . $cursor . '"' : '';
        $query = '{
               customers(first: ' . $limit . ', sortKey: CREATED_AT' . $after . ') {
                edges {
                    node {
                        id
                        firstName
                        lastName
                        state
                        createdAt
                        email
                        updatedAt
                        defaultAddress {
                        address1
                        address2
                        phone
                        province
                        provinceCode
                        zip
                        country
                        city
                        }
                    }
                    cursor
                    }
            }
            }';
        dump($query);
        return $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
    }

    public  function getShopifySingleCustomer($gid)
    {
        $query = '{
                  customer(id: "' . $gid . '") {
                    id
                    firstName
                    lastName
                    email
                    phone
                  }
                }';

        return $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
    }

    public function createGiftCard()
    {
        $accessToken = config('shopify.live.secret');
        $apiUrl = 'https://roadhouseerply.myshopify.com/admin/api/2024-01/gift_cards.json';

        $data = [
            'gift_card' => [
                'initial_value' => '100.00',
                'note' => 'Gift card created by Erply',
                'code' => '123456789',
                'customer_id' => '6943830868249',
                'expires_on' => '2024-07-16',
            ]
        ];

        $client = new Client();

        try {
            $response = $client->post($apiUrl, [
                'headers' => [
                    'X-Shopify-Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            $responseBody = $response->getBody()->getContents();
            return response()->json(json_decode($responseBody), $response->getStatusCode());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function searchGiftCard ($code)
    {
        $query = '{
                    giftCards(first: 1, query: "default:'. $code .'") {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }';
        return $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
    }

    public function formatGiftCode ($code)
    {
        $giftCode = (string) $code;
        if (strlen($giftCode) < 8) {
            $formattedGiftCode = str_pad($giftCode, 8, '0', STR_PAD_LEFT);
            return $formattedGiftCode; 
        }
        return $giftCode;
    }

    public function checkForDuplication ($data) 
    {
        $giftCard = GiftCard::where('code', '=',$data->code)->where('shopifyGiftCardId', '!=', null)->where('giftCardId', '!=', $data->giftCardId)->count();
        if ($giftCard > 1) return true;
        return false;
    }

}
