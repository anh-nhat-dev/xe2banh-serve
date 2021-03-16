<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Botble\Base\Enums\BaseStatusEnum;
use App\Http\Resources\{CategoryResource, MenuNodeResource, ProductResource};
use Botble\Ecommerce\Models\Product;
use Botble\Menu\Repositories\Interfaces\MenuLocationInterface;
use Botble\Menu\Repositories\Interfaces\MenuNodeInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductInterface;
use Botble\Slug\Repositories\Interfaces\SlugInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductVariationInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductVariationItemInterface;
use Illuminate\Http\Request;
use SlugHelper;



class ShopWiseController extends Controller
{


    /**
     * @var SlugInterface
     */
    protected $slugRepository;


    /**
     * @var ProductRepository
     */
    protected $productRepository;


    /**
     * @var MenuLocationInterface
     */
    protected $menuLocationRepository;

    /**
     * @var MenuNodeInterface
     */
    protected $menuNodeRepository;


    public function __construct(
        MenuLocationInterface $menuLocationRepository,
        ProductInterface $productRepository,
        MenuNodeInterface $menuNodeRepository,
        SlugInterface $slugRepository
    ) {
        $this->menuLocationRepository = $menuLocationRepository;
        $this->menuNodeRepository = $menuNodeRepository;
        $this->slugRepository = $slugRepository;
        $this->productRepository = $productRepository;
    }


    /**
     * 
     */
    public function getCategories()
    {
        $categories = get_product_categories(['status' => \Botble\Base\Enums\BaseStatusEnum::PUBLISHED], ['children'], [], true);
        return  CategoryResource::collection($categories);
    }


    /**
     * 
     */
    public function getMenuNodeByLocation(Request $request)
    {

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
    public function getFeaturedProductCatagories()
    {
        $categories = get_featured_product_categories();

        return CategoryResource::collection($categories);
    }

    /**
     * 
     */
    public function getProductsWithCategory(Request $request)
    {
        $params = [
            'condition' => [
                'ec_products.is_variation' => 0,
                'ec_products.status'       => \Botble\Base\Enums\BaseStatusEnum::PUBLISHED,
            ],
            'take'      => 10,
            'order_by'  => [
                'ec_products.created_at' => 'DESC',
            ],
            'select'    => ['ec_products.*'],
            'with'      => [
                'slugable',
                'variations',
                'productCollections',
                'variationAttributeSwatchesForProductList',
                'promotions',
            ]
        ];

        if ($request->has("category_id")) {
            $params["categories"] = [
                'by'       => 'id',
                'value_in' => [$request->input("category_id")],
            ];
        }

        if ($request->has("is_featured")) {
            $params["condition"]["ec_products.is_featured"]  = 1;
        }

        $products = app(ProductInterface::class)->getProductsWithCategory($params);

        return ProductResource::collection($products);
    }

    /**
     * 
     */
    public function getProducts(Request $request)
    {
        $request->request->add(["is_single" => false]);

        $params = [
            'condition' => [
                'ec_products.status'       => \Botble\Base\Enums\BaseStatusEnum::PUBLISHED,
                'ec_products.is_variation' => 0,
            ],
            'order_by'  => [
                'ec_products.order'      => 'ASC',
                'ec_products.created_at' => 'DESC',
            ],
            'take'      => null,
            'paginate'  => [
                'per_page'      => $request->input("limit"),
                'current_paged' => $request->input("page"),
            ],
            'select'    => [
                'ec_products.*',
            ],
            // 'with' => ['slugable'],
        ];


        if ($request->has('limit')) {
            $params["paginate"]["per_page"] = $request->input("limit");
        }

        if ($request->has('page')) {
            $params["paginate"]["current_paged"] = $request->input("page");
        }

        if ($request->has('take')) {
            $params["paginate"]["take"] = $request->input("take");
        }

        $products = app(ProductInterface::class)->getProducts($params);

        return ProductResource::collection($products);
        // return response()->json(["products" => $products]);
    }


    /**
     * 
     */
    public function getProduct(Request $request, $slug)
    {

        $request->request->add(["is_single" => true]);

        $slug = $this->slugRepository->getFirstBy([
            'key'            => $slug,
            'reference_type' => Product::class,
            'prefix'         => SlugHelper::getPrefix(Product::class),
        ]);
        // dd($slug);
        if (empty($slug)) {
            return response()->json(["message" => "Không tìm thấy sản phẩm"], 404);
        }

        $condition = [
            'ec_products.id'     => $slug->reference_id,
            'ec_products.status' => BaseStatusEnum::PUBLISHED,
        ];

        $product = get_products([
            'condition' => $condition,
            'take'      => 1,
            'with'      => [
                "productAttributes",
                "variations",
                "variations.productAttributes",
                "variations.product",
            ],
        ]);

        return new ProductResource($product);
        // return response()->json(["item"  => $product]);
    }
}
