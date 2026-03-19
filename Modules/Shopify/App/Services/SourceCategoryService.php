<?php

namespace Modules\Shopify\App\Services;

use Modules\Shopify\App\Models\Source\SourceCategory;
use Modules\Shopify\Database\factories\Source\SourceCategoryFactory;

class SourceCategoryService
{
    public function getAllCategory($limit)
    {
        $sql = SourceCategory::where([
            'shopifyPendingProcess' => 1
        ]);
        if ($limit > 0) {
            $sql->limit($limit);
        } else {
            $sql->limit(3);
        }
        return $sql->get();
    }
    
    public function getCategory($id)
    {

        return SourceCategory::where('categoryID', $id)->get();
    }
    public function updateCategory($data, $id)
    {
        $category = SourceCategory::find($id);
        return  $category->update($data);
    }
}
