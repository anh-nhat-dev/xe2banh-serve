<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use RvMedia;
use Illuminate\Http\Request;

class OtherApiController extends Controller
{
    public function getHomeSetting(Request $request)
    {

        $request->session()->regenerate();
        $data = [
            "site_title" => theme_option('site_title'),
            "logo" => RvMedia::getImageUrl(theme_option('logo'), null, false),
            "favicon" => RvMedia::getImageUrl(theme_option('favicon'), null, false)
        ];

        return response()->json(compact('data'));
    }
}
