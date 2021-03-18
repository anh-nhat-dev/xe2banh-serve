<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Botble\Blog\Models\Post;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Blog\Repositories\Interfaces\PostInterface;
use App\Http\Resources\PostResource;
use SlugHelper;

class BlogController extends Controller
{

    
    /**
     * @var PostInterface
     */
    protected $postRepository;


    /**
     * 
     */
    public function __construct(PostInterface  $postRepository){
            $this->postRepository = $postRepository;
    }


    /**
     * 
     */
    public function getPostFeatured(Request $request) {

        $posts = $this->postRepository->getFeatured((int) $request->input("limit"));

        return  PostResource::collection($posts);

    }

    /**
     * 
     */
    public function getPost($id){
        

     
        $post = $this->postRepository->getFirstBy(["id" =>  $id,  "status" =>  BaseStatusEnum::PUBLISHED]);

        if (empty($post)) goto not_found;

        return new PostResource($post);

        not_found:
        return response()->json(["message" => "Không tìm thấy bài viết"], 404);

    }
}
