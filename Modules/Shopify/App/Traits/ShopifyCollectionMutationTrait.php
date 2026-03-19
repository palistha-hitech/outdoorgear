<?php

namespace Modules\Shopify\App\Traits;

use Exception;
use GuzzleHttp\Client;
use Modules\Shopify\App\Models\ShopifyCursor;
use PhpParser\Node\Stmt\Return_;
use Illuminate\Support\Str;

trait ShopifyCollectionMutationTrait
{

    public function __construct()
    {
    }
    # this function is used to create or update collection as per the shopifyCollectionId
    public function createOrUpdateCollectionMutation($collectionData)
    {

        $mutation = 'mutation {';

        $isUpdate = false;
        $shopifyCollectionID = null;

        // FIRST CHECK BY ID
        if ($collectionData->shopifyCollectionId != null) {
            $shopify_collection = $this->getShopifyCollection($collectionData->shopifyCollectionId, 'id');
            if ($shopify_collection['exist'] == true) {
                $isUpdate = true;
                $shopifyCollectionID = $shopify_collection['id'];
            }
        }

        // IF NOT FOUND BY ID, THEN CHECK BY HANDLE 
        if ($isUpdate == false && $collectionData->slug) {
            $shopify_collection = $this->getShopifyCollection(\Str::slug($collectionData->slug), 'handle');
            if ($shopify_collection['exist'] == true) {
                $isUpdate = true;
                $shopifyCollectionID = $shopify_collection['id'];
            }
        }

        if ($isUpdate == true) {
            $mutation .= 'collectionUpdate(input:{';
            $mutation .= 'id:"' . $shopifyCollectionID . '",';
        } else {
            $mutation .= 'collectionCreate(input:{';
        }

        $mutation .= 'title:"' . $collectionData->categoryTitle . '",';
        $mutation .= 'descriptionHtml:"' . $collectionData->descriptionHtml . '",';
        // $mutation .= 'handle:"' . ($collectionData->slug ?? Str::slug($collectionData->categoryTitle)) . '",';
        $mutation .= 'handle:"' . \Str::slug($collectionData->slug) . '",';
        $mutation .= 'ruleSet:{';
        $mutation .= 'appliedDisjunctively: false,';
        // $rules = $this->generateCollectionRules($collectionData->sourceChildrenCategory, $collectionData->categoryTags ?? $collectionData->categoryTitle);
        $rules = $this->generateCollectionRulesV1($collectionData);

        $mutation .= 'rules:[' . implode('', $rules) . ']';
        $mutation .= '}';
        $mutation .= '}) {';

        $mutation .= 'collection {';
        $mutation .= 'id';
        $mutation .= '}';
        $mutation .= 'userErrors {';
        $mutation .= 'field ';
        $mutation .= 'message';
        $mutation .= '}';
        $mutation .= '}';
        $mutation .= '}';
        echo $mutation;

        return $mutation;
    }

    public function getShopifyCollection ($identifier, $type)
    {
        $result = [
            'exist' => false,
            'id' => null
        ];
        $mutations = 'query {';

        if ($type == 'id') {
            $mutations .= 'collection( id : "' . $identifier . '")';
        }

        if ($type == 'handle') {
            $mutations .= 'collectionByHandle( handle : "' . $identifier . '")';
        }

        $mutations .= '{
                    id
                    title
                }
            }';

        $response = $this->sendShopifyQueryRequestV2('POST', $mutations,  1);
        if ($type == 'id') {
            if ($response->data->collection->id) {

            }
        }

        if ($type == 'handle') {
            if (isset($response->data->collectionByHandle->id)) {
                $result = [
                    'exist' => true,
                    'id' => $response->data->collectionByHandle->id
                ];
            }
        }

        if ($type == 'id') {
            if (isset($response->data->collection->id)) {
                $result = [
                    'exist' => true,
                    'id' => $response->data->collection->id
                ];
            }
        }
        return $result;
    }

    # this function will generate rules for the collection
    private function generateCollectionRules($ChildrenCategories, $cTag,  $hasStaticRule = false)
    {
        $str = '{
            column : VARIANT_INVENTORY,
            relation : GREATER_THAN,
            condition : "0"
        },';
        $dynamicRules[] = $str;
        foreach ($ChildrenCategories as $rule) {
            // $tag = $rule->categoryTags ?? $rule->categoryTitle;
            $tag = $rule->slug;
            $str = '{';
            $str .= 'column: TAG,';
            $str .= 'relation: EQUALS,';
            $str .= 'condition:"' . $tag . '"';
            $str .= '},';
            $dynamicRules[] = $str;
            echo "<br>Has " . count($rule->sourceChildrenCategory) . " chieldren category inside" . "<br><br>";
            #Check if there are descendants
            if (count($rule->sourceChildrenCategory) > 0) {
                # Call the function recursively for descendants
                $dynamicRules = array_merge($dynamicRules, $this->generateCollectionRules($rule->sourceChildrenCategory, null, true));
            }
        }
        if (!$hasStaticRule) {
            $strLast = '{';
            $strLast .= 'column: TAG,';
            $strLast .= 'relation: EQUALS,';
            $strLast .= 'condition:"' . $cTag . '"';
            $strLast .= '}';
            $dynamicRules[] = $strLast;
            $hasStaticRule = true; // Update the flag to indicate the static rule has been added
        }

        dump('Dynamic Rules : ', $dynamicRules);
        return $dynamicRules;
    }

    private function generateCollectionRulesV1($ChildrenCategories)
    {
        $dynamicRules[] = '{
            column : VARIANT_INVENTORY,
            relation : GREATER_THAN,
            condition : "0"
        },';
        $dynamicRules[] = '{
            column : TAG,
            relation : EQUALS,
            condition:"' . $ChildrenCategories->slug . '"
        }';
        dump('Dynamic Rules : ', $dynamicRules);
        return $dynamicRules;
    }

    # delete collection
    public function deleteCollection($collectionId)
    {
        $mutation = 'mutation {';
        $mutation .= 'collectionDelete(id:"' . $collectionId . '") {';
        $mutation .= 'deletedCollectionId';
        $mutation .= '}';
        $mutation .= '}';
        return $mutation;
    }
    # get all collection from the shopify
    public function getAllCollection()
    {
        $query = 'query {';
        $query .= 'collections(first: 100) {';
        $query .= 'edges {';
        $query .= 'node {';
        $query .= 'id';
        $query .= 'title';
        $query .= '}';
        $query .= '}';
        $query .= '}';
        $query .= '}';

        return $query;
    }
}