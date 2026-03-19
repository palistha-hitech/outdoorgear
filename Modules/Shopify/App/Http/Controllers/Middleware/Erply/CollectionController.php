<?php

namespace Modules\Shopify\App\Http\Controllers\Middleware\Erply;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shopify\App\Services\ErplyService\ErplyProductService;
use Modules\Shopify\App\Services\SourceService\SourceProductGetService;

class CollectionController extends Controller
{
    protected $productService;
    protected $sourceProductService;
    public function __construct(ErplyProductService $productService)
    {
        $this->productService = $productService;
        $this->sourceProductService = new SourceProductGetService();
    }

    public function getCategory()
    {

        try {
            $categories = $this->productService->getCategory();

            if (\request()->query('debug') == 1) {
                dd($categories);
            }

            foreach ($categories as $category) {
                $result =  $this->sourceProductService->insertcategory($category);

                if ($result) {
                    echo "category inserted successfully" . "<br>";
                    echo "category = " . $result->categoryTitle . "<br>";
                    $updateData = [
                        'roadhouseStatus' => 0
                    ];

                    $this->productService->updateCategory($result->id, $updateData);
                } else {
                    echo "failed to insert category" . "<br>";
                    echo "category = " . $category->productCategoryName . "<br>";
                }
            }

            echo "process Completed successfully";
        } catch (Exception $e) {
            dd($e);
            return $e->getMessage();
        }
    }
}
