<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Widget\Repositories\Interfaces\WidgetInterface;
use Botble\Page\Repositories\Interfaces\PageInterface;
use App\Http\Resources\WidgetResource;
use Botble\Page\Models\Page;
use Botble\Page\Services\PageService;
use RvMedia;
use SlugHelper;



class OtherApiController extends Controller
{
    /**
     * 
     */
    public function getHomeSetting(Request $request)
    {

        $request->session()->regenerate();
        $data = [
            "site_title"        => theme_option('site_title'),
            "logo"              => RvMedia::getImageUrl(theme_option('logo'), null, false),
            "favicon"           => RvMedia::getImageUrl(theme_option('favicon'), null, false),
            "facebook"          => theme_option('facebook'),
            "twitter"           => theme_option('twitter'),
            "youtube"           => theme_option('youtube'),
            "instagram"         => theme_option('instagram'),
            "hotline"           => theme_option('hotline')
        ];

        return response()->json(compact('data'));
    }

    /**
     * 
     */
    public function getWidget(BaseHttpResponse $response, $key)
    {
        $widgets =  app(WidgetInterface::class)->getModel()
            ->where('sidebar_id', $key)
            ->get();

        return $response->setData(WidgetResource::collection($widgets));
    }


    /**
     * 
     */
    public function getPageBySlug($slug, BaseHttpResponse $response)
    {
        $slug = SlugHelper::getSlug($slug, SlugHelper::getPrefix(Page::class));


        if (!$slug) {
            return $response->setError()->setCode(404);
        }

        $condition = [
            'id'     => $slug->reference_id,
            'status' => BaseStatusEnum::PUBLISHED,
        ];

        $page = app(PageInterface::class)->getFirstBy($condition, ['*'], []);

        return $response->setData($page);
    }
}
