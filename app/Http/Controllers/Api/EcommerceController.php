<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Http\Responses\BaseHttpResponse;
use App\Http\Resources\{CategoryResource, MenuNodeResource, ProductResource, BrandResource, ProductAttributeSetResource, SimpleSliderResource, ReviewResource};
use Botble\Menu\Repositories\Interfaces\{MenuLocationInterface, MenuNodeInterface};
use Botble\Slug\Repositories\Interfaces\SlugInterface;
use Botble\Ecommerce\Services\Products\GetProductService;
use Botble\Ecommerce\Repositories\Interfaces\{ProductAttributeSetInterface, ProductInterface, ProductCategoryInterface, ReviewInterface};
use Botble\SimpleSlider\Repositories\Interfaces\SimpleSliderInterface;
use Illuminate\Http\Request;
use App\Http\Requests\ReviewRequest;
use App\Http\Requests\CartRequest;
use DB;
use Cart;
use EcommerceHelper;
use OrderHelper;


class EcommerceController extends Controller
{


    /**
     * @var ProductAttributeSetRepository
     */
    protected $productAttributeSetRepository;

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
        ProductCategoryInterface $categoryProductRepository,
        ProductAttributeSetInterface $productAttributeSetRepository
    ) {
        $this->menuLocationRepository = $menuLocationRepository;
        $this->menuNodeRepository = $menuNodeRepository;
        $this->slugRepository = $slugRepository;
        $this->productRepository = $productRepository;
        $this->categoryProductRepository = $categoryProductRepository;
        $this->productAttributeSetRepository = $productAttributeSetRepository;
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
    public function getProductsFeatured(Request $request)
    {
        $params = [
            'condition' => [
                'ec_products.is_variation' => 0,
                'ec_products.status'       => \Botble\Base\Enums\BaseStatusEnum::PUBLISHED,
                'ec_products.is_featured'  => 1
            ],
            'take'      => $request->input("take"),
            'paginate'  => [
                'per_page'      => $request->input("limit"),
                'current_paged' => $request->input("page"),
            ],
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

        $products = app(ProductInterface::class)->getProductsWithCategory($params);

        return ProductResource::collection($products);
    }

    /**
     * 
     */
    public function getProducts(Request $request, GetProductService $productService)
    {
        $products = $productService->getProduct($request);
        $request->request->add(["is_single" => false]);
        return ProductResource::collection($products);
        // return response()->json(["products" => $products]);
    }

    /**
     * 
     */
    public function getProductCategory(
        $id,
        Request $request,
        GetProductService $getProductService
    ) {

        $products = $getProductService->getProduct(
            $request,
            $id,
            null,
            ['slugable', 'variations', 'productCollections', 'variationAttributeSwatchesForProductList', 'promotions']
        );

        return ProductResource::collection($products);
    }


    /**
     * 
     */
    public function getProduct(Request $request, BaseHttpResponse $response, $id)
    {

        try {
            $request->request->add(["is_single" => true]);

            $condition = [
                'ec_products.id'     => $id,
                'ec_products.status' => BaseStatusEnum::PUBLISHED,
                "ec_products.is_variation" => 0
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

            if (empty($product)) return $response->setError()
                ->setCode(400)
                ->setMessage('Không tìm thấy sản phẩm');

            return  $response->setData(new ProductResource($product));
        } catch (\Throwable $th) {

            return $response->setError()
                ->setMessage($th->getMessage())
                ->setCode(500);
        }
    }

    /**
     * 
     */
    public function getRelatedProducts(BaseHttpResponse $response, $id)
    {
        try {
            $condition = [
                'ec_products.id'     => $id,
                'ec_products.status' => BaseStatusEnum::PUBLISHED,
            ];

            $product = get_products([
                'condition' => $condition,
                'take'      => 1,

            ]);

            $products = get_related_products($product);

            return $response->setData(ProductResource::collection($products));
        } catch (\Throwable $th) {

            return $response->setError()
                ->setMessage($th->getMessage())
                ->setCode(500);
        }
    }

    /**
     * 
     */
    public function getCategory($id)
    {

        $condition = [
            'id'     => $id,
            'status' => BaseStatusEnum::PUBLISHED,
        ];

        $category = $this->categoryProductRepository->getFirstBy($condition, ["*"], ["children", "parent"]);

        if (empty($category)) goto not_found;

        return new CategoryResource($category);

        not_found:
        return response()->json(["message" => "Không tìm thấy danh mục"], 404);
    }

    /**
     * 
     */
    public function getAllBrands()
    {

        $brands = get_all_brands(['status' => \Botble\Base\Enums\BaseStatusEnum::PUBLISHED], [], ['products']);


        return BrandResource::collection($brands);
    }

    /**
     * 
     */
    public function getAllAttributeSet()
    {

        $attributeSets = $this->productAttributeSetRepository
            ->advancedGet([
                'condition' => [
                    'status'        => BaseStatusEnum::PUBLISHED,
                    'is_searchable' => 1,
                ],
                'order_by'  => [
                    'order' => 'ASC',
                ],
                'with'      => [
                    'attributes',
                ],
            ]);

        return  ProductAttributeSetResource::collection($attributeSets);
    }

    /**
     * 
     */
    public function getSlider($key)
    {

        $slider = app(SimpleSliderInterface::class)->getFirstBy(["key" => $key]);
        if (empty($slider)) goto not_found;
        return new SimpleSliderResource($slider);

        not_found:
        return response()->json(["message" => "Không tìm thấy slider"], 404);
    }


    /**
     * 
     */
    public function getRatingsProduct($id)
    {
        try {
            $ratings = app(ReviewInterface::class)->getModel()
                ->select('star', DB::raw('count(id) as total'))
                ->where('product_id', $id)
                ->groupBy("star")
                ->get();

            $totalRating = $ratings->sum('total');

            $data =  collect([5, 4, 3, 2, 1])->map(function ($star) use ($ratings, $totalRating) {
                $total =  $ratings->firstWhere("star", $star)->total ?? 0;
                $percent = $totalRating ?  ceil(($total / $totalRating) * 100) : 0;
                return compact('star', 'total', 'percent');
            });

            return  response()->json(compact('data'));
        } catch (\Throwable $th) {

            return  response()->json(["error" => true], 500);
        }
    }

    /**
     * 
     */
    public function postCreateReview(ReviewRequest $request)
    {
        try {
            app(ReviewInterface::class)->create($request->input());

            return  response()->json(["error" => false, "message" => "Tạo bình luận thành công"]);
        } catch (\Throwable $th) {

            return  response()->json(["error" => true, "message" => "Tạo bình luận không thành công", "data"  => $request->input()], 500);
        }
    }

    /**
     * 
     */
    public function getProductReviews($id)
    {
        $reviews = app(ReviewInterface::class)->getModel()
            ->where('product_id', $id)
            ->where('status', BaseStatusEnum::PUBLISHED)
            ->orderBy('id', 'desc')
            ->paginate(10);



        return ReviewResource::collection($reviews);
    }

    /**
     * 
     */
    public function getCart(BaseHttpResponse $response)
    {
        try {
            $cartItems =  [];

            foreach (Cart::instance('cart')->content() as $item) {
                array_push($cartItems, $item);
            }

            $data = [
                "content"       => $cartItems,
                "count"         => Cart::instance('cart')->count(),
                "total_price"   => format_price(Cart::instance('cart')->rawSubTotal()),
                "token"         => OrderHelper::getOrderSessionToken()
            ];

            return $response->setData($data);
        } catch (\Throwable $th) {
            return $response->setError()
                ->setMessage($th->getMessage())
                ->setCode(500);
        }
    }


    /**
     * 
     */
    public function addToCart(CartRequest $request, BaseHttpResponse $response)
    {
        try {
            if (!EcommerceHelper::isCartEnabled()) {
                return $response->setError(true);
            }

            $product = $this->productRepository->findById($request->id);

            if (!$product) {
                return $response
                    ->setError()
                    ->setMessage(__('This product is out of stock or not exists!'))
                    ->toApiResponse();
            }

            if ($product->variations->count() > 0 && !$product->is_variation) {
                $product = $product->defaultVariation->product;
            }

            if ($product->isOutOfStock()) {
                return $response
                    ->setError()
                    ->setMessage(__('Product :product is out of stock!', ['product' => $product->original_product->name]));
            }
            $maxQuantity = $product->quantity;

            if (!$product->canAddToCart($request->input('qty', 1))) {
                return $response
                    ->setError()
                    ->setMessage(__('Maximum quantity is ' . $maxQuantity . '!'));
            }

            $product->quantity -= $request->input('qty', 1);

            $outOfQuantity = false;

            foreach (Cart::instance('cart')->content() as $item) {
                if ($item->id == $product->id) {
                    $originalQuantity = $product->quantity;
                    $product->quantity = (int)$product->quantity - $item->qty;
                    if ($product->quantity < 0) {
                        $product->quantity = 0;
                    }
                    if ($product->isOutOfStock()) {
                        $outOfQuantity = true;
                        break;
                    }
                    $product->quantity = $originalQuantity;
                }
            }

            if ($outOfQuantity) {
                return $response
                    ->setError()
                    ->setMessage(__('Product :product is out of stock!', ['product' => $product->original_product->name]));
            }

            $cartItems = OrderHelper::handleAddCart($product, $request);

            $token = OrderHelper::getOrderSessionToken();

            $nextUrl = route('public.checkout.information', $token);

            return $response
                ->setData([
                    'status'      => true,
                    'count'       => Cart::instance('cart')->count(),
                    'total_price' => format_price(Cart::instance('cart')->rawSubTotal()),
                    'content'     => $cartItems,
                    'next_url'    => $nextUrl,
                ])
                ->setMessage(__(
                    'Added product :product to cart successfully!',
                    ['product' => $product->original_product->name]
                ));
        } catch (\Throwable $th) {
            return $response->setError()
                ->setMessage($th->getMessage())
                ->setCode(500)
                ->toApiResponse();
        }
    }
}
