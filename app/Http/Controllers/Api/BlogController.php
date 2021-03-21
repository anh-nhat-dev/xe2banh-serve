<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Botble\Blog\Models\Post;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Blog\Repositories\Interfaces\{CategoryInterface, PostInterface};
use App\Http\Resources\{PostResource, BlogCategoryResource};
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
    public function __construct(PostInterface  $postRepository)
    {
        $this->postRepository = $postRepository;
    }


    /**
     * 
     */
    public function getPostFeatured(Request $request)
    {

        $posts = $this->postRepository->getFeatured((int) $request->input("limit"));

        return  PostResource::collection($posts);
    }

    /**
     * 
     */
    public function getPost(Request $request, $id)
    {
        $request->request->add(["is_single" => true]);

        $post = $this->postRepository->getFirstBy(["id" =>  $id,  "status" =>  BaseStatusEnum::PUBLISHED]);

        if (empty($post)) goto not_found;

        return new PostResource($post);

        not_found:
        return response()->json(["message" => "Không tìm thấy bài viết"], 404);
    }


    /**
     * 
     */
    public function getPostRelated(Request $request)
    {

        $request->request->add(["is_single" => false]);

        $posts = $this->postRepository->getRelated((int) $request->input("id"));

        return PostResource::collection($posts);
    }

    /**
     * 
     */
    public function getPopularPosts(Request $request)
    {
        $request->request->add(["is_single" => false]);


        $posts = $this->postRepository
            ->getModel()
            ->where([
                'posts.status'    => BaseStatusEnum::PUBLISHED,
            ])
            ->limit(6)
            ->orderBy('posts.views', 'desc');

        $posts = $this->postRepository->applyBeforeExecuteQuery($posts)->get();
        return PostResource::collection($posts);
    }


    /**
     * 
     */
    public function getPostByCategory(Request $request, $id)
    {
        $posts = $this->postRepository
            ->getByCategory($id, (int) $request->input("take"), (int) $request->input("limit"));

        return PostResource::collection($posts);
    }

    /**
     * 
     */
    public function getFeaturedCategory(Request $request)
    {

        $limit = $request->has("limit") ? $request->input("limit") : 3;

        $categories = app(CategoryInterface::class)->getFeaturedCategories($limit);

        return BlogCategoryResource::collection($categories);
    }

    /**
     * 
     */
    public function getPosts(Request $request)
    {

        $perPage = $request->has("limit") ? $request->input("limit") : 5;

        $posts = $this->postRepository->getAllPosts($perPage);

        return PostResource::collection($posts);
    }

    /**
     * 
     */
    public function getCategory(Request $request, $id)  {
        $category =  app(CategoryInterface::class)->getCategoryById($id);

        return  new BlogCategoryResource($category);
    }
}
