<?php

namespace Modules\Shopify\App\Traits;

use GuzzleHttp\Client;
use Modules\Shopify\App\Models\ShopifyCursor;

trait ShopifyTrait
{
    public function __construct() {}

    public function getShopifyCredentails($isLive = 0)
    {
        if ($isLive == 1) {
          
            return ['url' => config('shopify.live.url'), 'secret' => config('shopify.live.secret')];
        }

        return ['url' => config('shopify.staging.url'), 'secret' => config('shopify.staging.secret')];
    }

    public function getClientCode()
    {

        return config('shopify.clientCode');
    }

    public function sendShopifyQueryRequest($url, $method, $secret, $query)
    {
        $headers = [
            'X-Shopify-Access-Token' => $secret,
            'Content-Type' => 'application/json',
        ];

        $client = new Client([
            'headers' => $headers,
        ]);

        $request = $client->request($method, $url, [
            'json' => [
                'query' => $query,
            ],
        ]);

        $products = json_decode($request->getBody()->getContents());

        return $products;
    }

    public function sendShopifyQueryRequestV2($method, $query, $isLive = 0, $lastModified = null)
    {
// dd($query);
        $shopifyDetails = $this->getShopifyCredentails($isLive);
   
    $headers = [
            'X-Shopify-Access-Token' => $shopifyDetails['secret'],
            'Content-Type' => 'application/json',
        ];

        $client = new Client([
            'headers' => $headers,
        ]);

        $request = $client->request($method, $shopifyDetails['url'], [
            'json' => [
                'query' => $query,
            ],
        ]);
      

        $products = json_decode($request->getBody()->getContents());
    //  dd($products);
        return $products;
    }

    public function getCursor($clientCode, $name, $isLive = 0)
    {
        $cursor = ShopifyCursor::where('clientCode', $clientCode)->where('isLive', $isLive)->where('cursorName', $name)->first();
        if ($cursor) {
            return [
                'cursor' => $cursor->cursor,
                'cursorName' => $cursor->cursorName
            ];
        }
        return null;
    }

    public function getMatrixProductQuery($clientCode)
    {
        $cursor = $this->getCursor($clientCode, 'matrixProduct', 1);
        $magic = 'products(first:3,sortKey:ID) {';
        if ($cursor != '') {
            # $cursor = "updated_at:>='$cursor'";
            $magic = 'products(first:5,sortKey:ID, after:"' . $cursor . '") {';
        }
        $query = <<<GQL
            query {
                $magic
                edges {
                    cursor
                    node {
                      id
                      title
                      totalInventory
                      updatedAt
                      createdAt
                      hasOnlyDefaultVariant
                      totalVariants
                      images(first: 100) {
                        edges {
                          node {
                            url
                          }
                        }
                      }

                      status
                      defaultCursor
                      handle
                      vendor
                      productType
                      hasOnlyDefaultVariant
                      description
                      descriptionHtml
                      tags
                    }
                  }
                  pageInfo {
                    hasNextPage
                    hasPreviousPage
                    startCursor
                    endCursor
                  }
                }
              }
          GQL;

        return $query;
    }

    public function getMatrixProductQueryByLastmodified($clientCode)
    {
        $cursor = $this->getCursor($clientCode, 'matrixProductV2', 1);
        $magic = 'products(first:3,sortKey:UPDATED_AT) {';
        if ($cursor != '') {
            $cursor = "updated_at:>='$cursor'";
            $magic = "products(first:10, query:\"$cursor\", sortKey:UPDATED_AT) {";
        }
        $query = <<<GQL
            query {
                $magic
                edges {
                    cursor
                    node {
                      id
                      title
                      totalInventory
                      updatedAt
                      createdAt
                      hasOnlyDefaultVariant
                      totalVariants
                      images(first: 100) {
                        edges {
                          node {
                            url
                          }
                        }
                      }

                      status
                      defaultCursor
                      handle
                      vendor
                      productType
                      hasOnlyDefaultVariant
                      description
                      descriptionHtml
                      tags
                    }
                  }
                  pageInfo {
                    hasNextPage
                    hasPreviousPage
                    startCursor
                    endCursor
                  }
                }
              }
          GQL;

        return $query;
    }

    public function getVariationProductQuery($clientCode)
    {
        $cursor = $this->getCursor($clientCode, 'variationProduct', 1);
        $magic = 'productVariants(first:3,sortKey:ID) {';
        if ($cursor != '') {
            $magic = 'productVariants(first:3,sortKey:ID, after:"' . $cursor . '") {';
        }
        $query = <<<GQL
            query {
                $magic
                edges {
                  cursor
                  node {
                    id
                    title

                    product {
                      id
                      status
                    }
                    price
                    selectedOptions {
                      name
                      value
                    }
                    defaultCursor
                    inventoryItem {
                      id
                    }
                    inventoryQuantity
                    availableForSale
                    compareAtPrice
                    createdAt
                    displayName
                    weight
                    weightUnit
                    sku
                    barcode
                    image {
                      url
                    }
                  }
                }
                pageInfo {
                  hasNextPage
                  hasPreviousPage
                  startCursor
                  endCursor
                }
              }
            }
          GQL;

        return $query;
    }

    public function getSohQuery($clientCode, $isLive)
    {
        if ($isLive == 1) {
            $cursor = $this->getCursor($clientCode, 'soh');
        } else {
            $cursor = $this->getCursor($clientCode, 'soh_stageing');
        }
        $magic = 'productVariants(first:3,sortKey:ID) {';
        if ($cursor != '') {
            $magic = 'productVariants(first:3,sortKey:ID, after:"' . $cursor . '") {';
        }
        $query = <<<GQL
            query {
                $magic
                edges {
                  cursor
                  node {
                    id
                    title
                    sku
                    inventoryItem {
                      id
                      inventoryLevels(first: 100) {
                        edges {
                          node {
                            available
                            location {
                              id
                              name
                            }
                            updatedAt
                          }
                        }
                      }
                    }
                  }
                }
                pageInfo {
                  hasNextPage
                }
              }
            }
          GQL;

        return $query;
    }

    public function getCustomerQuery($clientCode)
    {
        $cursor = $this->getCursor($clientCode, 'customer');
        $magic = 'customers(first:3,sortKey:ID) {';
        if ($cursor != '') {
            $magic = 'customers(first:3,sortKey:ID, after:"' . $cursor . '") {';
        }
        $query = <<<GQL
            query {
                $magic
                edges {
                  cursor
                  node {
                    id
                    email
                    firstName
                    lastName
                    phone
                    state
                    updatedAt
                    createdAt
                    note
                    defaultAddress{
                      id
                      city
                      company
                      country
                      address1
                      address2
                      zip
                      province
                      countryCodeV2
                      provinceCode
                  }
                    smsMarketingConsent{
                      marketingState
                    }
                    emailMarketingConsent{
                        marketingState
                    }
                    numberOfOrders
                    amountSpent{
                        amount
                    }
                    metafields(first: 5) {
                      edges {
                        node {
                          id
                          key
                          namespace
                          description
                          value
                        }
                      }
                    }

                  }

                }
              }
            }
          GQL;

        return $query;
    }

    public function getOrdersQuery($clientCode, $isLive = 0, $cursorType = 'order', $limit = 3)
    {

        $result = $this->getCursor($clientCode, $cursorType, $isLive);
        $cursor = $result['cursor'] ?? '';
        $cursorName = $result['cursorName'] ?? '';


        $after = '';
        if ($cursor) {
            if ($cursorName === 'order') {
                $after = ', after: "' . $cursor . '"';
            } elseif ($cursorName === 'orderBYLMD') {
                $cursor = "'" . $cursor . "'";
                $after = ', query:"updated_at:>=' . $cursor . '", sortKey:UPDATED_AT';
            }
        } else {
            $after = ', sortKey:UPDATED_AT';
        }

        $magic = '';
        if (!empty($cursor)) {
            $magic = 'orders(first:' . $limit . '' . $after . ') {';
        } else {
            $magic = 'orders(first:' . $limit . '' . $after . ') {';
        }
        $query = <<<GQL
            query {
                $magic
                edges {
                  cursor
                    node {
                    id
                        name
                  createdAt
                  updatedAt
                  processedAt
                  displayFulfillmentStatus
                    displayFinancialStatus
                    totalPriceSet {
                        shopMoney {
                          amount
                        }
                      }
                  tags
                  note
                  shippingLine {
                        source
                        deliveryCategory
                        code
                        originalPriceSet {
                          shopMoney
                          {
                            amount
                          }
                        }
                      }
                    discountCodes

                  totalDiscountsSet {

                    shopMoney {
                      amount
                    }
                  }
                  currencyCode
                  subtotalPriceSet {
                          shopMoney {
                          amount
                        }
                      }
                  totalTaxSet {
                        shopMoney {
                          amount
                        }
                      }
                  discountApplications(first: 5) {
                    edges {
                      node {
                    targetType
                        value {

                          ... on PricingPercentageValue {
                            percentage


                          }
                          ... on MoneyV2 {
                            amount
                            currencyCode

                          }
                        }
                      }
                    }
                  }
                    risks {
                            display
                            level
                            message
                          }
                paymentGatewayNames
                  customer {
                  id
                    firstName
                    lastName
                    email
                    phone

                    defaultAddress {
                    address1
                    address2
                    city
                    province
                    country
                    zip
                    phone
                        }
                  }
                    billingAddress {
                    firstName
                    lastName
                    address1
                    address2
                    city
                    province
                    country
                    zip
                    phone
                  }
                    shippingAddress {
                    firstName
                    lastName
                    address1
                    address2
                    city
                    province
                    country
                    zip
                    phone
                  }
                  lineItems(first: 50) {
                    edges {
                      node {

                        sku
                        id
                        quantity
                        discountAllocations{
                          allocatedAmountSet{
                            shopMoney {
                              amount
                            }
                          }
                        }
                        totalDiscountSet {shopMoney {amount}}

                        variant {
                          title
                          price
                          sku
                          id
                            selectedOptions {
                                name
                                value
                              }
                        }
                      }
                    }
                  }
                    }
                  }
                  pageInfo {
                    hasNextPage
                    hasPreviousPage
                    startCursor
                    endCursor
                  }
                }
            }
          GQL;

        return $query;
    }

    public function getSingleOrdersQuery($shopifyID = '')
    {

        // $magic = 'orders(first:3) {';
        $magic = 'order( id:"' . $shopifyID . '") {';
        // if ($cursor != '') {
        //   $magic = 'orders(first:3, after:"' . $cursor . '") {';
        // }
        $query = <<<GQL
            query {
                $magic

                  id
                  name
                  createdAt
                  updatedAt
                  processedAt
                  displayFulfillmentStatus
                    displayFinancialStatus
                    totalPriceSet {
                        shopMoney {
                          amount
                        }
                      }
                  tags
                  note
                  shippingLine {
                        source
                        deliveryCategory
                        code
                        title
                        originalPriceSet {
                          shopMoney
                          {
                            amount
                          }
                        }
                      }
                    discountCodes

                  totalDiscountsSet {

                    shopMoney {
                      amount
                    }
                  }
                  currencyCode
                  subtotalPriceSet {
                          shopMoney {
                          amount
                        }
                      }
                  totalTaxSet {
                        shopMoney {
                          amount
                        }
                      }
                  discountApplications(first: 5) {
                    edges {
                      node {
                    targetType
                        value {

                          ... on PricingPercentageValue {
                            percentage


                          }
                          ... on MoneyV2 {
                            amount
                            currencyCode

                          }
                        }
                      }
                    }
                  }
                  risks {
                          display
                          level
                          message
                        }
                paymentGatewayNames
                customer {
                    id
                    firstName
                    lastName
                    email
                    phone

                    defaultAddress {
                      address1
                      address2
                      city
                      province
                      country
                      zip
                      phone
                    }
                  }
                  billingAddress {
                    firstName
                    lastName
                    address1
                    address2
                    city
                    province
                    country
                    zip
                    phone
                  }
                  shippingAddress {
                    firstName
                    lastName
                    address1
                    address2
                    city
                    province
                    country
                    zip
                    phone
                  }
                  lineItems(first: 50) {
                    edges {
                      node {
                        sku
                        id
                        quantity
                        totalDiscountSet {
                          shopMoney {
                            amount
                          }
                        }
                        discountAllocations{
                          allocatedAmountSet{
                            shopMoney {
                              amount
                            }
                          }
                        }
                        originalUnitPriceSet{
                          shopMoney {
                            amount
                          }
                        }
                        unfulfilledDiscountedTotalSet{
                          shopMoney {
                            amount
                          }
                        }
                        variant {
                          title
                          price
                          sku
                          id

                          selectedOptions {
                            name
                            value
                          }
                        }
                      }
                    }
                  }
                }
          }
          GQL;

        return $query;
    }

    public function getRefundQuery($clientCode, $isLive = 0)
    {
        // $cursor = $lastModified ? "updated_at:>=$lastModified" : '';
        $cursor = $cursor = $this->getCursor($clientCode, 'refundDate', $isLive);
        dump($cursor);
        $cursor = $cursor['cursor'];
        dump($cursor);
        if ($cursor !== '') {
            $cursor = "updated_at:>='$cursor'";

            $magic = "orders(first:3, query:\"(financial_status:PARTIALLY_REFUNDED OR financial_status:REFUNDED OR status:cancelled) AND $cursor\", sortKey:UPDATED_AT) {";
            // $magic = 'orders(first:1,after:"'.$cursor.'", query:"(financial_status:PARTIALLY_REFUNDED OR financial_status:REFUNDED)") {';
        } else {
            $magic = 'orders(first:7, query:"financial_status:PARTIALLY_REFUNDED OR financial_status:REFUNDED") {';
        }

        $query = <<<GQL
      query {
        $magic
        pageInfo {
          endCursor
        }
        edges {
          cursor
          node {
            name
            displayFinancialStatus
            id
            createdAt
            updatedAt
            shippingLine {
              originalPriceSet {
                shopMoney {
                  amount
                }
              }
            }
            totalDiscountsSet {
              shopMoney {
                amount
              }
            }
            refunds {
              id
              note
              createdAt
              updatedAt
              totalRefundedSet {
                shopMoney {
                  amount
                }
              }
              order {
                totalRefundedShippingSet {
                  shopMoney {
                    amount
                  }
                }
              }
              refundLineItems(first:50) {
                edges {
                  node {
                    location {
                      id 
                      name 
                    }
                    lineItem {
                      id
                      sku
                    }
                    quantity
                    priceSet {
                      shopMoney {
                        amount
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    GQL;

        return $query;
    }

    public function getSingleRedundQuery($refundId = '')
    {
        $magic = 'order( id:"' . $refundId . '") {';
        $query = <<<GQL
            query {
                $magic
                  id
                  name
                  createdAt
                  updatedAt
                  processedAt
                  displayFulfillmentStatus
                    displayFinancialStatus
                    totalPriceSet {
                        shopMoney {
                          amount
                        }
                      }
                  tags
                  note
                  shippingLine {
                        source
                        deliveryCategory
                        code
                        originalPriceSet {
                          shopMoney
                          {
                            amount
                          }
                        }
                      }
                    discountCodes

                  totalDiscountsSet {

                    shopMoney {
                      amount
                    }
                  }
                  refunds{
                      id
                      note
                      createdAt
                      updatedAt
                      totalRefundedSet{
                        shopMoney{
                          amount
                        }
                      }
                      refundLineItems(first:50){
                        edges{
                          node{
                            lineItem{
                              id
                              sku
                            }
                            quantity
                            priceSet{
                              shopMoney{
                                amount
                              }
                            }
                          }
                        }
                      }

                    }
                  currencyCode
                  subtotalPriceSet {
                          shopMoney {
                          amount
                        }
                      }
                  totalTaxSet {
                        shopMoney {
                          amount
                        }
                      }
                  discountApplications(first: 5) {
                    edges {
                      node {
                    targetType
                        value {

                          ... on PricingPercentageValue {
                            percentage


                          }
                          ... on MoneyV2 {
                            amount
                            currencyCode

                          }
                        }
                      }
                    }
                  }
                  risks {
                          display
                          level
                          message
                        }
                paymentGatewayNames
                customer {
                    id
                    firstName
                    lastName
                    email
                    phone

                    defaultAddress {
                      address1
                      address2
                      city
                      province
                      country
                      zip
                      phone
                    }
                  }
                  billingAddress {
                    firstName
                    lastName
                    address1
                    address2
                    city
                    province
                    country
                    zip
                    phone
                  }
                  shippingAddress {
                    firstName
                    lastName
                    address1
                    address2
                    city
                    province
                    country
                    zip
                    phone
                  }
                  lineItems(first: 50) {
                    edges {
                      node {
                        sku
                        id
                        quantity
                        totalDiscountSet {
                          shopMoney {
                            amount
                          }
                        }
                        discountAllocations{
                          allocatedAmountSet{
                            shopMoney {
                              amount
                            }
                          }
                        }
                        originalUnitPriceSet{
                          shopMoney {
                            amount
                          }
                        }
                        unfulfilledDiscountedTotalSet{
                          shopMoney {
                            amount
                          }
                        }
                        variant {
                          title
                          price
                          sku
                          id

                          selectedOptions {
                            name
                            value
                          }
                        }
                      }
                    }
                  }
                }
          }
          GQL;

        return $query;
    }

    public function getRefundsForOrders()
    {
        $orders = $this->getOrders();
        $allRefunds = [];
        foreach ($orders['orders'] as $order) {
            $orderId = $order['id'];
            $result = $this->getRefundsByOrderId($orderId);
            if (!empty($result['refunds'])) {
                $allRefunds[] = $result;
            }
        }

        return $allRefunds;
    }

    private function getRefundsByOrderId($orderId)
    {
        // $response = $this->client->get("/admin/orders/{$orderId}/refunds.json", [
        $response = $this->client->get("/admin/orders/{$orderId}/refunds.json", [
            'query' => ['order_id' => $orderId],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function checkSohQuery($variantId)
    {

        $query = '
            {
            productVariant(id: "' . $variantId . '") {
                id
                inventoryItem {
                id

                inventoryLevels(first: 100) {
                    edges {
                    node {
                        id
                        quantities(names:"available"){
                            id
                            quantity
                        }
                        location{id}
                    }
                    }
                }
                }
            }
            }';

        return $query;
    }

    public function deleteShopifyProduct($productId)
    {
        $query = '
            mutation {
                productDelete(input: {id: "' . $productId . '"}) {
                    deletedProductId
                }
                }
        ';
        return $query;
    }

    public function deleteShopifyVariant($productId, $variantIds)
    {
      $query = '
        mutation {
          productVariantsBulkDelete(productId: ' . $productId . ', variantsIds: ' . $variantIds . ') {
            product {
              id
              title
            }
            userErrors {
              field
              message
            }
          }
        }';
      return $query;
    }

    public function getUnfulfilledOrdeQuery($orderStringId)
    {
        $query = '{
                order(id: "' . $orderStringId . '") {
                    id
                    fulfillmentOrders(first: 100) {
                    edges {
                        node {
                        id
                        lineItems(first: 100) {
                            edges {
                            node {
                                id
                                lineItem {
                                id
                                quantity
                                fulfillableQuantity
                                }
                            }
                            }
                        }
                        }
                    }
                    }
                }
                }';

        return $query;
    }

    public function fulfillmentCreateV2Mutation($fulfillmentOrderId, $lineItems)
    {

        $query = '
            mutation {
            fulfillmentCreateV2(
                fulfillment: {
                lineItemsByFulfillmentOrder: {
                    fulfillmentOrderId: "' . $fulfillmentOrderId . '",
                    fulfillmentOrderLineItems: [';
        foreach ($lineItems as $item) {

            $query .= '{
                    id: "' . $item->node->id . '",
                    quantity: ' . $item->node->lineItem->quantity . '
                    },';
        }
        $query .= ']
                },
                notifyCustomer: false
                }
            ) {
                fulfillment {
                createdAt
                id
                name
                }
            }
        }';

        return $query;
    }
}
