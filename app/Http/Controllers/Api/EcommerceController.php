<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Ecommerce\Enums\ShippingMethodEnum;
use Botble\Ecommerce\Enums\OrderStatusEnum;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Base\Http\Responses\BaseHttpResponse;
use App\Http\Resources\{
    CategoryResource,
    MenuNodeResource,
    ProductResource,
    BrandResource,
    ProductAttributeSetResource,
    SimpleSliderResource,
    ReviewResource
};
use Botble\Menu\Repositories\Interfaces\{MenuLocationInterface, MenuNodeInterface};
use Botble\Slug\Repositories\Interfaces\SlugInterface;
use Botble\Ecommerce\Services\Products\GetProductService;
use Botble\Ecommerce\Repositories\Interfaces\{
    ProductAttributeSetInterface,
    ProductInterface,
    ProductCategoryInterface,
    ReviewInterface,
    OrderInterface,
    OrderProductInterface,
    OrderAddressInterface,
    OrderHistoryInterface,
    DiscountInterface
};
use Botble\SimpleSlider\Repositories\Interfaces\SimpleSliderInterface;
use Botble\Ecommerce\Services\HandleApplyPromotionsService;
use Botble\Ecommerce\Services\HandleApplyCouponService;
use Botble\Ecommerce\Services\HandleRemoveCouponService;
use Botble\Ecommerce\Services\HandleShippingFeeService;
use Botble\Ecommerce\Http\Requests\ApplyCouponRequest;
use Botble\Ecommerce\Http\Requests\UpdateCartRequest;
use Botble\Ecommerce\Http\Requests\CheckoutRequest;
use Botble\Payment\Services\Gateways\BankTransferPaymentService;
use Botble\Payment\Services\Gateways\CodPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Http\Requests\ReviewRequest;
use App\Http\Requests\CartRequest;
use DB;
use Cart;
use EcommerceHelper;
use OrderHelper;
use Validator;


class EcommerceController extends Controller
{

    /**
     * @var OrderInterface
     */
    protected $orderRepository;


    /**
     * @var OrderHistoryInterface
     */
    protected $orderHistoryRepository;

    /**
     * @var OrderProductInterface
     */
    protected  $orderProductRepository;

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
     * @var OrderAddressInterface
     */
    protected $orderAddressRepository;

    /**
     * @var MenuLocationInterface
     */
    protected $menuLocationRepository;

    /**
     * @var MenuNodeInterface
     */
    protected $menuNodeRepository;

    /**
     * @var DiscountInterface
     */
    protected $discountRepository;


    public function __construct(
        MenuLocationInterface $menuLocationRepository,
        ProductInterface $productRepository,
        MenuNodeInterface $menuNodeRepository,
        SlugInterface $slugRepository,
        ProductCategoryInterface $categoryProductRepository,
        ProductAttributeSetInterface $productAttributeSetRepository,
        OrderInterface $orderRepository,
        OrderProductInterface $orderProductRepository,
        OrderAddressInterface $orderAddressRepository,
        OrderHistoryInterface $orderHistoryRepository,
        DiscountInterface $discountRepository
    ) {
        $this->menuLocationRepository = $menuLocationRepository;
        $this->menuNodeRepository = $menuNodeRepository;
        $this->slugRepository = $slugRepository;
        $this->productRepository = $productRepository;
        $this->categoryProductRepository = $categoryProductRepository;
        $this->productAttributeSetRepository = $productAttributeSetRepository;
        $this->orderRepository = $orderRepository;
        $this->orderProductRepository = $orderProductRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->orderHistoryRepository = $orderHistoryRepository;
        $this->discountRepository = $discountRepository;
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
    public function getCart(BaseHttpResponse $response, HandleApplyPromotionsService $applyPromotionsService)
    {
        try {

            if (!EcommerceHelper::isCartEnabled()) {
                return $response->setError()
                    ->setMessage('Giỏ hàng hiện tại đang đóng để bảo trì. Xin vui lòng thử lại sau')
                    ->setCode(404);
            }

            $cartItems =  [];
            $promotionDiscountAmount = 0;
            $couponDiscountAmount = 0;

            foreach (Cart::instance('cart')->content() as $item) {
                $product = $this->productRepository->findById($item->id);
                if (!$product) {
                    Cart::remove($item->rowId);
                } else {
                    array_push($cartItems, $item);
                }

                $promotionDiscountAmount = $applyPromotionsService->execute();
                $sessionData = OrderHelper::getOrderSessionData();

                if (session()->has('applied_coupon_code')) {
                    $couponDiscountAmount = Arr::get($sessionData, 'coupon_discount_amount', 0);
                }
            }

            $data = [
                "content"       => $cartItems,
                "count"         => Cart::instance('cart')->count(),
                "total_price"   => Cart::instance('cart')->rawSubTotal(),
                "token"         => OrderHelper::getOrderSessionToken(),
                "final_price"   => Cart::instance('cart')->rawTotal() - $promotionDiscountAmount - $couponDiscountAmount,
            ];

            if ($promotionDiscountAmount > 0) {
                $data["promotion_discount_amount"] = $promotionDiscountAmount;
            }

            if ($couponDiscountAmount > 0) {
                $data["coupon_discount_amount"] = $couponDiscountAmount;
            }

            if (session('applied_coupon_code')) {
                $data["code"] = session('applied_coupon_code');
            }

            if (isset($data["is_free_ship"])) {
                $data["is_free_ship"] = $sessionData["is_free_ship"];
            }

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

    /**
     * 
     */
    public function postApplyCoupon(
        ApplyCouponRequest $request,
        HandleApplyCouponService $handleApplyCouponService,
        BaseHttpResponse $response
    ) {

        try {
            if (!EcommerceHelper::isCartEnabled()) {
                return $response->setError()
                    ->setMessage('Giỏ hàng hiện tại đang đóng để bảo trì. Xin vui lòng thử lại sau')
                    ->setCode(404);
            }

            $result = $handleApplyCouponService->execute($request->input('coupon_code'));

            if ($result['error']) {
                return $response
                    ->setError()
                    ->withInput()
                    ->setMessage($result['message']);
            }

            $couponCode = $request->input('coupon_code');

            return $response
                ->setMessage(__('Applied coupon ":code" successfully!', ['code' => $couponCode]));
        } catch (\Throwable $th) {
            return $response->setError()
                ->setMessage($th->getMessage())
                ->setCode(500);
        }
    }

    /**
     * 
     */
    public function postRemoveCoupon(
        Request $request,
        HandleRemoveCouponService $removeCouponService,
        BaseHttpResponse $response
    ) {
        try {
            if (!EcommerceHelper::isCartEnabled()) {
                return $response->setError()
                    ->setMessage('Giỏ hàng hiện tại đang đóng để bảo trì. Xin vui lòng thử lại sau')
                    ->setCode(404);
            }

            $result = $removeCouponService->execute();

            if ($result['error']) {
                if ($request->ajax()) {
                    return $result;
                }
                return $response
                    ->setError()
                    ->setData($result)
                    ->setMessage($result['message']);
            }

            return $response
                ->setMessage(__('Removed coupon :code successfully!', ['code' => session('applied_coupon_code')]));
        } catch (\Throwable $th) {
            return $response->setError()
                ->setMessage($th->getMessage())
                ->setCode(500);
        }
    }

    /**
     * 
     */
    public function getRemove($id, BaseHttpResponse $response)
    {
        if (!EcommerceHelper::isCartEnabled()) {
            return $response->setError()
                ->setMessage('Giỏ hàng hiện tại đang đóng để bảo trì. Xin vui lòng thử lại sau')
                ->setCode(404);
        }

        try {
            Cart::instance('cart')->remove($id);
        } catch (Exception $exception) {
            return $response->setError()->setMessage(__('Cart item is not existed!'));
        }

        return $response
            ->setMessage(__('Removed item from cart successfully!'));
    }


    /**
     * 
     */
    public function postUpdateCart(UpdateCartRequest $request, BaseHttpResponse $response)
    {
        if (!EcommerceHelper::isCartEnabled()) {
            return $response->setError()
                ->setMessage('Giỏ hàng hiện tại đang đóng để bảo trì. Xin vui lòng thử lại sau')
                ->setCode(404);
        }

        if ($request->has('checkout')) {
            $token = OrderHelper::getOrderSessionToken();

            return $response->setNextUrl(route('public.checkout.information', $token));
        }
        $data = $request->input('items', []);

        $outOfQuantity = false;
        foreach ($data as $item) {
            $cartItem = Cart::instance('cart')->get($item['rowId']);
            $product = null;
            if ($cartItem) {
                $product = $this->productRepository->findById($cartItem->id);
            }
            if ($product) {
                $originalQuantity = $product->quantity;
                $product->quantity = (int)$product->quantity - Arr::get($item['values'], 'qty', 0) + 1;
                if ($product->quantity < 0) {
                    $product->quantity = 0;
                }
                if ($product->isOutOfStock()) {
                    $outOfQuantity = true;
                } else {
                    Cart::instance('cart')->update($item['rowId'], $item['values']);
                }
                $product->quantity = $originalQuantity;
            }
        }

        if ($outOfQuantity) {
            return $response
                ->setError()
                ->setMessage(__('One or all products are not enough quantity so cannot update!'));
        }

        return $response
            ->setMessage(__('Update cart successfully!'));
    }


    /**
     * 
     */
    public function getCheckoutInfomation(
        $token,
        Request $request,
        BaseHttpResponse $response,
        HandleShippingFeeService $shippingFeeService,
        HandleApplyPromotionsService $applyPromotionsService
    ) {

        if (!EcommerceHelper::isCartEnabled()) {
            return $response->setError()
                ->setMessage('Giỏ hàng hiện tại đang đóng để bảo trì. Xin vui lòng thử lại sau')
                ->setCode(404);
        }

        if ($token !== session('tracked_start_checkout')) {
            $order = $this->orderRepository->getFirstBy(['token' => $token, 'is_finished' => false]);
            if (!$order) {
                return $response->setError('Đơn hàng không tồn tại!')
                    ->setCode(404);
            }
        }


        $sessionCheckoutData = OrderHelper::getOrderSessionData($token);

        $weight = 0;
        foreach (Cart::instance('cart')->content() as $cartItem) {
            $product = $this->productRepository->findById($cartItem->id);
            if (!$product) {
                Cart::remove($cartItem->rowId);
            } elseif ($product->weight) {
                $weight += $product->weight * $cartItem->qty;
            }
        }

        $weight = $weight ? $weight : 0.1;

        $promotionDiscountAmount = $applyPromotionsService->execute($token);
        $couponDiscountAmount = 0;
        if (session()->has('applied_coupon_code')) {
            $couponDiscountAmount = Arr::get($sessionCheckoutData, 'coupon_discount_amount', 0);
        }

        $orderTotal = Cart::instance('cart')->rawTotal() - $promotionDiscountAmount;
        $orderTotal = $orderTotal > 0 ? $orderTotal : 0;




        $shippingData = [
            'weight'      => $weight,
            'order_total' => $orderTotal,
        ];

        $shipping = $shippingFeeService->execute($shippingData);

        foreach ($shipping as $key => &$shipItem) {
            if (get_shipping_setting('free_ship', $key)) {
                foreach ($shipItem as &$subShippingItem) {
                    Arr::set($subShippingItem, 'price', 0);
                }
            }
        }

        $payment_method = [];

        if (setting('payment_cod_status') == 1) {
            $payment_method[] = [
                "method"        => "cod",
                "label"         => setting('payment_cod_name', trans('plugins/payment::payment.payment_via_cod')),
                "description"   => setting('payment_cod_description'),
                "is_default"       => setting('default_payment_method') == \Botble\Payment\Enums\PaymentMethodEnum::COD
            ];
        }

        if (setting('payment_bank_transfer_status') == 1) {
            $payment_method[] = [
                "method"        => "bank_transfer",
                "label"         => setting('payment_bank_transfer_name', trans('plugins/payment::payment.payment_via_bank_transfer')),
                "description"   => setting('payment_bank_transfer_description'),
                "is_default"       => setting('default_payment_method') == \Botble\Payment\Enums\PaymentMethodEnum::BANK_TRANSFER
            ];
        }


        return $response->setData(compact("shipping", "payment_method"));
    }


    /**
     * @param string $token
     * @param CheckoutRequest $request
     * @param PayPalPaymentService $palPaymentService
     * @param StripePaymentService $stripePaymentService
     * @param BaseHttpResponse $response
     * @param HandleShippingFeeService $shippingFeeService
     * @param HandleApplyCouponService $applyCouponService
     * @param HandleRemoveCouponService $removeCouponService
     * @param HandleApplyPromotionsService $handleApplyPromotionsService
     * @return mixed
     * @throws Throwable
     */
    public function postCheckout(
        $token,
        CheckoutRequest $request,
        CodPaymentService $codPaymentService,
        BankTransferPaymentService $bankTransferPaymentService,
        BaseHttpResponse $response,
        HandleShippingFeeService $shippingFeeService,
        HandleApplyCouponService $applyCouponService,
        HandleRemoveCouponService $removeCouponService,
        HandleApplyPromotionsService $handleApplyPromotionsService
    ) {


        if (!EcommerceHelper::isCartEnabled()) {
            return $response->setError()
                ->setMessage('Giỏ hàng hiện tại đang đóng để bảo trì. Xin vui lòng thử lại sau')
                ->setCode(404);
        }

        if (!Cart::instance('cart')->count()) {
            return $response
                ->setError()
                ->setMessage(__('No products in cart'));
        }

        $sessionData = OrderHelper::getOrderSessionData($token);

        $this->processOrderData($token, $sessionData, $request);
        $weight = 0;
        foreach (Cart::instance('cart')->content() as $cartItem) {
            $product = $this->productRepository->findById($cartItem->id);
            if ($product) {
                if ($product->weight) {
                    $weight += $product->weight * $cartItem->qty;
                }
            }
        }

        $weight = $weight < 0.1 ? 0.1 : $weight;

        $promotionDiscountAmount = $handleApplyPromotionsService->execute($token);
        $couponDiscountAmount = Arr::get($sessionData, 'coupon_discount_amount');

        $shippingAmount = 0;

        if ($request->has('shipping_method') && !get_shipping_setting(
            'free_ship',
            $request->input('shipping_method')
        )) {

            $shippingData = [
                'address'     => Arr::get($sessionData, 'address'),
                'country'     => Arr::get($sessionData, 'country'),
                'state'       => Arr::get($sessionData, 'state'),
                'city'        => Arr::get($sessionData, 'city'),
                'weight'      => $weight,
                'order_total' => Cart::instance('cart')->rawTotal() - $promotionDiscountAmount - $couponDiscountAmount,
            ];

            $shippingMethod = $shippingFeeService->execute(
                $shippingData,
                $request->input('shipping_method'),
                $request->input('shipping_option')
            );

            $shippingAmount = Arr::get(Arr::first($shippingMethod), 'price', 0);
        }

        if (session()->has('applied_coupon_code')) {
            $discount = $applyCouponService->getCouponData(session('applied_coupon_code'), $sessionData);
            if (empty($discount)) {
                $removeCouponService->execute();
            } else {
                $shippingAmount = Arr::get($sessionData, 'is_free_ship') ? 0 : $shippingAmount;
            }
        }

        $currentUserId = 0;

        $request->merge([
            'amount'          => ($promotionDiscountAmount + $couponDiscountAmount - $shippingAmount) > Cart::instance('cart')
                ->rawTotal() ? 0 : Cart::instance('cart')
                ->rawTotal() + $shippingAmount - $promotionDiscountAmount - $couponDiscountAmount,
            'currency'        => $request->input('currency', strtoupper(get_application_currency()->title)),
            'user_id'         => $currentUserId,
            'shipping_method' => $request->input('shipping_method', ShippingMethodEnum::DEFAULT),
            'shipping_option' => $request->input('shipping_option'),
            'shipping_amount' => $shippingAmount,
            'tax_amount'      => Cart::instance('cart')->rawTax(),
            'sub_total'       => Cart::instance('cart')->rawSubTotal(),
            'coupon_code'     => session()->get('applied_coupon_code'),
            'discount_amount' => $promotionDiscountAmount + $couponDiscountAmount,
            'status'          => OrderStatusEnum::PENDING,
            'is_finished'     => true,
            'token'           => $token,
        ]);

        $order = $this->orderRepository->getFirstBy(compact('token'));
        if ($order) {
            $order->fill($request->input());
            $order = $this->orderRepository->createOrUpdate($order);
        } else {
            $order = $this->orderRepository->createOrUpdate($request->input());
        }

        if ($order) {

            $this->orderHistoryRepository->createOrUpdate([
                'action'      => 'create_order_from_payment_page',
                'description' => __('Order is created from checkout page'),
                'order_id'    => $order->id,
            ]);

            $discount = $this->discountRepository
                ->getModel()
                ->where('code', session()->get('applied_coupon_code'))
                ->where('type', 'coupon')
                ->where('start_date', '<=', now())
                ->where(function ($query) {
                    /**
                     * @var Builder $query
                     */
                    return $query
                        ->whereNull('end_date')
                        ->orWhere('end_date', '>', now());
                })
                ->first();

            if (!empty($discount)) {
                $discount->total_used++;
                $this->discountRepository->createOrUpdate($discount);
            }

            $this->orderProductRepository->deleteBy(['order_id' => $order->id]);

            foreach (Cart::instance('cart')->content() as $cartItem) {
                $data = [
                    'order_id'     => $order->id,
                    'product_id'   => $cartItem->id,
                    'product_name' => $cartItem->name,
                    'qty'          => $cartItem->qty,
                    'weight'       => $weight,
                    'price'        => $cartItem->price,
                    'tax_amount'   => EcommerceHelper::isTaxEnabled() ? $cartItem->taxRate / 100 * $cartItem->price : 0,
                    'options'      => [],
                ];

                if ($cartItem->options->extras) {
                    $data['options'] = $cartItem->options->extras;
                }

                $this->orderProductRepository->create($data);

                $this->productRepository
                    ->getModel()
                    ->where([
                        'id'                         => $cartItem->id,
                        'with_storehouse_management' => 1,
                    ])
                    ->where('quantity', '>=', $cartItem->qty)
                    ->decrement('quantity', $cartItem->qty);
            }

            $request->merge([
                'order_id' => $order->id,
            ]);

            $paymentData = [
                'error'     => false,
                'message'   => false,
                'amount'    => $order->amount,
                'currency'  => strtoupper(get_application_currency()->title),
                'type'      => $request->input('payment_method'),
                'charge_id' => null,
            ];

            switch ($request->input('payment_method')) {

                case PaymentMethodEnum::COD:
                    $paymentData['charge_id'] = $codPaymentService->execute($request);
                    break;

                case PaymentMethodEnum::BANK_TRANSFER:
                    $paymentData['charge_id'] = $bankTransferPaymentService->execute($request);
                    break;
                default:
                    $paymentData = apply_filters(PAYMENT_FILTER_AFTER_POST_CHECKOUT, $paymentData, $request);
                    break;
            }

            OrderHelper::processOrder($order->id, $paymentData['charge_id']);

            if ($paymentData['error']) {
                return $response
                    ->setError()
                    ->setMessage($paymentData['message']);
            }

            return $response
                ->setMessage(__('Checkout successfully!'));
        }

        return $response
            ->setError()
            ->setMessage(__('There is an issue when ordering. Please try again later!'));
    }

    /**
     * @param string $token
     * @param array $sessionData
     * @param Request $request
     */
    protected function processOrderData(string $token, array $sessionData, Request $request): array
    {
        if (!isset($sessionData['created_order'])) {
            $currentUserId = 0;

            $request->merge([
                'amount'          => Cart::instance('cart')->rawTotal(),
                'currency_id'     => get_application_currency_id(),
                'user_id'         => $currentUserId,
                'shipping_method' => $request->input('shipping_method', ShippingMethodEnum::DEFAULT),
                'shipping_option' => $request->input('shipping_option'),
                'shipping_amount' => 0,
                'tax_amount'      => Cart::instance('cart')->rawTax(),
                'sub_total'       => Cart::instance('cart')->rawSubTotal(),
                'coupon_code'     => session()->get('applied_coupon_code'),
                'discount_amount' => 0,
                'status'          => OrderStatusEnum::PENDING,
                'is_finished'     => false,
                'token'           => $token,
            ]);

            $order = $this->orderRepository->createOrUpdate($request->input());
            $sessionData['created_order'] = true;
            $sessionData['created_order_id'] = $order->id;
        }

        $address = null;

        $addressData = [];
        if (!empty($address)) {
            $addressData = [
                'name'     => $address->name,
                'phone'    => $address->phone,
                'email'    => $address->email,
                'country'  => $address->country,
                'state'    => $address->state,
                'city'     => $address->city,
                'address'  => $address->address,
                'zip_code' => $address->zip_code,
                'order_id' => $sessionData['created_order_id'],
            ];
        } elseif ((array)$request->input('address', [])) {
            $addressData = array_merge(
                ['order_id' => $sessionData['created_order_id']],
                (array)$request->input('address', [])
            );
        }

        if ($addressData && !empty($addressData['name']) && !empty($addressData['phone']) && !empty($addressData['address'])) {
            if (!isset($sessionData['created_order_address'])) {
                if ($addressData) {
                    $createdOrderAddress = $this->createOrderAddress($addressData);
                    if ($createdOrderAddress) {
                        $sessionData['created_order_address'] = true;
                        $sessionData['created_order_address_id'] = $createdOrderAddress->id;
                    }
                }
            } elseif (!empty($sessionData['created_order_address_id'])) {
                $this->createOrderAddress($addressData, $sessionData['created_order_address_id']);
            }
        }

        $sessionData = array_merge($sessionData, $addressData);

        if (!isset($sessionData['created_order_product'])) {
            $weight = 0;
            foreach (Cart::instance('cart')->content() as $cartItem) {
                $product = $this->productRepository->findById($cartItem->id);
                if ($product) {
                    if ($product->weight) {
                        $weight += $product->weight * $cartItem->qty;
                    }
                }
            }

            $weight = $weight > 0.1 ? $weight : 0.1;

            foreach (Cart::instance('cart')->content() as $cartItem) {
                $data = [
                    'order_id'     => $sessionData['created_order_id'],
                    'product_id'   => $cartItem->id,
                    'product_name' => $cartItem->name,
                    'qty'          => $cartItem->qty,
                    'weight'       => $weight,
                    'price'        => $cartItem->price,
                    'tax_amount'   => EcommerceHelper::isTaxEnabled() ? $cartItem->taxRate / 100 * $cartItem->price : 0,
                    'options'      => [],
                ];

                if ($cartItem->options->extras) {
                    $data['options'] = $cartItem->options->extras;
                }

                $this->orderProductRepository->create($data);
            }

            $sessionData['created_order_product'] = true;
        }

        OrderHelper::setOrderSessionData($token, $sessionData);

        return $sessionData;
    }

    /**
     * @param array $data
     * @return false|mixed
     */
    protected function createOrderAddress(array $data, $orderAddressId = null)
    {
        if ($orderAddressId) {
            return $this->orderAddressRepository->createOrUpdate($data, ['id' => $orderAddressId]);
        }

        $rules = [
            'name'    => 'required|max:255',
            'email'   => 'email|nullable|max:60',
            'phone'   => 'required|numeric',
            'state'   => 'required|max:120',
            'city'    => 'required|max:120',
            'address' => 'required|max:120',
        ];

        if (EcommerceHelper::isZipCodeEnabled()) {
            $rules['zip_code'] = 'required|max:20';
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return false;
        }

        return $this->orderAddressRepository->create($data);
    }
}
