<?php

Route::group(['namespace' => 'Botble\BaoKim\Http\Controllers', 'middleware' => ['api']], function () {
    Route::get('api/baokim/payment/{id}/callback', [
        'as'   => 'baokim.payment.callback',
        'uses' => 'BaoKimController@paymentCallback',
    ]);
});
