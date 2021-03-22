<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['namespace' => 'App\Http\Controllers\Api', 'middleware' => ['api', 'core']], function () {
    Route::get("/categories", "EcommerceController@getCategories");
    Route::get("/categories/{id}", "EcommerceController@getCategory");
    Route::get("/menu-by-location", "EcommerceController@getMenuNodeByLocation");
    Route::get("/featured-product-categories", "EcommerceController@getFeaturedProductCatagories");
    Route::get("/products-featured", "EcommerceController@getProductsFeatured");
    Route::get("/products", "EcommerceController@getProducts");
    Route::get("/products/{id}", "EcommerceController@getProduct");
    Route::get("/products/{id}/related-products", "EcommerceController@getRelatedProducts");
    Route::get("/brands", "EcommerceController@getAllBrands");
    Route::get("/attribute-set", "EcommerceController@getAllAttributeSet");
    Route::get("/categories/{id}/products", "EcommerceController@getProductCategory");
    Route::get("/sliders/{key}", "EcommerceController@getSlider");
    Route::get("/posts-featured", "BlogController@getPostFeatured");
    Route::get("/posts-related", "BlogController@getPostRelated");
    Route::get("/posts-top-views", "BlogController@getPopularPosts");
    Route::get("/posts/{id}", "BlogController@getPost");
    Route::get("/posts", "BlogController@getPosts");
    Route::get("/post-categories/{id}", "BlogController@getCategory");
    Route::get("/post-categories/{id}/posts", "BlogController@getPostByCategory");
    Route::get("/featured-categories", "BlogController@getFeaturedCategory");
    Route::get("/home-settings", "OtherApiController@getHomeSetting");
});
