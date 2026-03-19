<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopifyV2;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Shopify\App\Services\SourceCategoryService;
use Modules\Shopify\App\Traits\ShopifyCollectionMutationTrait;
use Modules\Shopify\App\Traits\ShopifyTrait;

class SourceCollectionController extends Controller
{
    use ShopifyTrait, ShopifyCollectionMutationTrait;

    /**
     * Display a listing of the resource.
     */
    protected $live = 0;
    protected $clientCode;
    protected  $categoryService;
    public function __construct(SourceCategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
        $this->clientCode = $this->getClientCode();
    }
    public function index(Request $request)
    {
        $debug = $request->input('debug', 0);
        $id = $request->input('id', '');
        try {
            if ($id) {

                $categories = $this->categoryService->getCategory($id);
            } else {

                $categories = $this->categoryService->getAllCategory();
            }
            if ($debug == 1) {
                dd($categories);
            }
            if (count($categories) <= 0) {
                echo 'no categories found';
                exit;
            }
            foreach ($categories as $category) {
                $mutations =   $this->createOrUpdateCollectionMutation($category);
                if ($debug == 2) {

                    dd($mutations);
                }

                $response = $this->sendShopifyQueryRequestV2('POST', $mutations,  $this->live);

                if ($debug == 3) {
                    dd($response);
                }

                # check response error
                if ($response['errors']) {
                    print_r($response['errors']);
                    exit;
                }

                # check for error in creating
                if ($response['data']['collectionCreate']['userErrors'] || $response['data']['collectionUpdate']['userErrors']) {
                    $error = $response['data']['collectionCreate']['userErrors'] ?? $response['data']['collectionUpdate']['userErrors'];
                    echo "error in creating collection " . $error[0]['field'];
                    echo "<br>";
                    echo $error[0]['message'];
                    echo "<br>";

                    # update the category table
                    $updatedata = [
                        'errorMessage' => $error[0]['message'],
                        # 3 means error in creating collection
                        'shopifyPendingProcess' => 3,

                        'lastPushedDate' => date('Y-m-d H:i:s'),
                    ];
                    continue;
                }

                $collections = $response['data']['collectionCreate']['collection'] ?? $response['data']['collectionUpdate']['collection'];

                # update the category table
                $updatedata = [
                    'shopifyCollectionId' => $collections['id'],
                    # 2 means collection created successfully
                    'shopifyPendingProcess' => 2,

                    'lastPushedDate' => date('Y-m-d H:i:s'),
                ];
                $this->categoryService->updateCategory($updatedata, $category->id);
            }
        } catch (Exception  $e) {
            dd($e);
            return $e->getMessage();
        }
    }



    public function destroy(Request $request)
    {
        try {
            $id = $request->input('id', '');
            if ($id) {
                $mutations = $this->deleteCollection($id);
                $response = $this->sendShopifyQueryRequestV2('POST', $mutations);

                if ($response['data']['collectionDelete']['deletedCollectionId']) {
                    echo "collection deleted";
                    exit;
                }
                echo "collection not found";
            }
        } catch (Exception $th) {
            info($th->getMessage());
            return $th->getMessage();
            //throw $th;
        }
    }
}
