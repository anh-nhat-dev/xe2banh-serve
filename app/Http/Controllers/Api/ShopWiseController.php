<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Botble\Base\Enums\BaseStatusEnum;
use App\Http\Resources\{CategoryResource, MenuNodeResource, ProductResource};
use Botble\Ecommerce\Models\{Product, ProductCategory};
use Botble\Menu\Repositories\Interfaces\{MenuLocationInterface, MenuNodeInterface};
use Botble\Slug\Repositories\Interfaces\SlugInterface;
use Botble\Ecommerce\Repositories\Interfaces\{ProductVariationInterface, ProductVariationItemInterface, ProductInterface, ProductCategoryInterface};
use Illuminate\Http\Request;
use SlugHelper;



class ShopWiseController extends Controller
{



    /**
     * @var ProductCategoryInterface
     */
    protected $categoryProductRepository;

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
        SlugInterface $slugRepository,
        ProductCategoryInterface $categoryProductRepository
    ) {
        $this->menuLocationRepository = $menuLocationRepository;
        $this->menuNodeRepository = $menuNodeRepository;
        $this->slugRepository = $slugRepository;
        $this->productRepository = $productRepository;
        $this->categoryProductRepository = $categoryProductRepository;
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

        if (empty($slug)) goto not_found;

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
                "promotions"
            ],
        ]);

        if (empty($product)) goto not_found;

        return new ProductResource($product);

        not_found:
        return response()->json(["message" => "Không tìm thấy sản phẩm"], 404);
    }

    /**
     * 
     */
    public function getRelatedProducts(Request $request, $id)
    {

        $condition = [
            'ec_products.id'     => $id,
            'ec_products.status' => BaseStatusEnum::PUBLISHED,
        ];

        $product = get_products([
            'condition' => $condition,
            'take'      => 1,

        ]);

        $products = get_related_products($product);

        return ProductResource::collection($products);
    }

    /**
     * 
     */
    public function getCategoryBySlug($slug)
    {
        $slug = $this->slugRepository->getFirstBy([
            'key'            => $slug,
            'reference_type' => ProductCategory::class,
            'prefix'         => SlugHelper::getPrefix(ProductCategory::class),
        ]);


        if (empty($slug)) goto not_found;

        $condition = [
            'id'     => $slug->reference_id,
            'status' => BaseStatusEnum::PUBLISHED,
        ];

        $category = $this->categoryProductRepository->getFirstBy($condition, ["*"], ["children", "parent"]);

        if (empty($category)) goto not_found;

        return new CategoryResource($category);

        not_found:
        return response()->json(["message" => "Không tìm thấy danh mục"], 404);
    }
}
