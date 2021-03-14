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

Route::group(['namespace' => 'App\Http\Controllers\Api', 'middleware' => ['api', 'core']], function(){
    Route::get("/categories", "ShopWiseController@getCategories");
    Route::get("/menu-by-location", "ShopWiseController@getMenuNodeByLocation");
    Route::get("/featured-product-categories", "ShopWiseController@getFeaturedProductCatagories");
    Route::get("/featured-products", "ShopWiseController@getFeaturedProducts");
});