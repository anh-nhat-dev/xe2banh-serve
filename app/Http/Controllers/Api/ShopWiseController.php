<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\{CategoryResource,MenuNodeResource};
use Botble\Menu\Repositories\Interfaces\MenuLocationInterface;
use Botble\Menu\Repositories\Interfaces\MenuNodeInterface;
use Illuminate\Http\Request;
use Menu;


class ShopWiseController extends Controller
{


    

    /**
     * @var MenuLocationInterface
     */
    protected $menuLocationRepository;

     /**
     * @var MenuNodeInterface
     */
    protected $menuNodeRepository;


    public function __construct(MenuLocationInterface $menuLocationRepository, MenuNodeInterface $menuNodeRepository){
        $this->menuLocationRepository = $menuLocationRepository;
        $this->menuNodeRepository = $menuNodeRepository;
    }


    /**
     * 
     */
    public function getCategories(){
        $categories = get_product_categories(['status' => \Botble\Base\Enums\BaseStatusEnum::PUBLISHED], [ 'children' ], [], true);
       return  CategoryResource::collection($categories);
    }


    /**
     * 
     */
    public function getMenuNodeByLocation(Request $request){

        $location = $request->location;
        
        // Find location id
        $menuLocation = $this->menuLocationRepository->getFirstBy(['location' => $location]);
        if (!$menuLocation) return response()->json(["data" => []]);

        $menuNodes = $this->menuNodeRepository->allBy(["menu_id" => $menuLocation->menu_id, "parent_id" => 0], ["child"]);
        return MenuNodeResource::collection($menuNodes);
    }

    /**
     * 
     */
    public function getFeaturedProductCatagories() {
        $categories = get_featured_product_categories();

        return CategoryResource::collection($categories);
    }

    /**
     * 
     */
    public function getFeaturedProducts(){
        $products = get_featured_products([
            'take' => 10,
            'with' => [
                'slugable',
                'variations',
                'productCollections',
                'variationAttributeSwatchesForProductList',
                'promotions',
            ],
        ]);

        return response()->json(["data" => $products]);
    }
}
