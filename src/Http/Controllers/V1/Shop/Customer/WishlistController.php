<?php

namespace Mrpath\RestApi\Http\Controllers\V1\Shop\Customer;

use Illuminate\Http\Request;
use Mrpath\Checkout\Facades\Cart;
use Mrpath\Customer\Repositories\WishlistRepository;
use Mrpath\Product\Repositories\ProductRepository;
use Mrpath\RestApi\Http\Resources\V1\Shop\Checkout\CartResource;
use Mrpath\RestApi\Http\Resources\V1\Shop\Customer\CustomerWishlistResource;

class WishlistController extends CustomerController
{
    /**
     * Wishlist repository instance.
     *
     * @var \Mrpath\Customer\Repositories\WishlistRepository
     */
    protected $wishlistRepository;

    /**
     * Product repository instance.
     *
     * @var \Mrpath\Customer\Repositories\ProductRepository
     */
    protected $productRepository;

    /**
     * Create a new controller istance.
     *
     * @param  \Mrpath\Customer\Repositories\WishlistRepository  $wishlistRepository
     * @param  \Mrpath\Product\Repositories\ProductRepository  $productRepository
     */
    public function __construct(
        WishlistRepository $wishlistRepository,
        ProductRepository $productRepository
    ) {
        parent::__construct();
        
        $this->wishlistRepository = $wishlistRepository;

        $this->productRepository = $productRepository;
    }

    /**
     * Get customer wishlist.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $customer = $this->resolveShopUser($request);

        return response([
            'data' => CustomerWishlistResource::collection($customer->wishlist_items()->get()),
        ]);
    }

    /**
     * Add or remote item from wishlist.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addOrRemove(Request $request, $id)
    {
        $customer = $this->resolveShopUser($request);

        $wishlistItem = $this->wishlistRepository->findOneWhere([
            'channel_id'  => core()->getCurrentChannel()->id,
            'product_id'  => $id,
            'customer_id' => $customer->id,
        ]);

        if ($wishlistItem) {
            $this->wishlistRepository->delete($wishlistItem->id);

            return response([
                'data'    => null,
                'message' => 'Item removed from wishlist successfully.',
            ]);
        }

        $wishlistItem = $this->wishlistRepository->create([
            'channel_id'  => core()->getCurrentChannel()->id,
            'product_id'  => $id,
            'customer_id' => $customer->id,
        ]);

        return response([
            'data'    => new CustomerWishlistResource($wishlistItem),
            'message' => __('rest-api::app.common-response.success.add', ['name' => 'Wishlist']),
        ]);
    }

    /**
     * Move product from wishlist to cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function moveToCart(Request $request, $id)
    {
        $customer = $this->resolveShopUser($request);

        $wishlistItem = $this->wishlistRepository->findOneWhere([
            'channel_id'  => core()->getCurrentChannel()->id,
            'product_id'  => $id,
            'customer_id' => $customer->id,
        ]);

        if ($wishlistItem->customer_id != $customer->id) {
            return response([
                'message' => __('rest-api::app.common-response.error.security-warning'),
            ], 400);
        }

        $result = Cart::moveToCart($wishlistItem);

        if ($result) {
            Cart::collectTotals();

            $cart = Cart::getCart();

            return response([
                'data'    => $cart ? new CartResource($cart) : null,
                'message' => __('rest-api::app.wishlist.moved'),
            ]);
        }

        return response([
            'message' => __('rest-api::app.wishlist.option-missing'),
        ], 400);
    }
}
