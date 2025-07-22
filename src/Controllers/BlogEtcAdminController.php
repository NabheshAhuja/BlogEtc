<?php

namespace WebDevEtc\BlogEtc\Controllers;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Backend\BackendController;
use Carbon\Carbon;
use WebDevEtc\BlogEtc\Interfaces\BaseRequestInterface;
use WebDevEtc\BlogEtc\Events\BlogPostAdded;
use WebDevEtc\BlogEtc\Events\BlogPostEdited;
use WebDevEtc\BlogEtc\Events\BlogPostWillBeDeleted;
use WebDevEtc\BlogEtc\Helpers;
use WebDevEtc\BlogEtc\Middleware\UserCanManageBlogPosts;
use WebDevEtc\BlogEtc\Models\BlogEtcPost;
use WebDevEtc\BlogEtc\Models\BlogEtcCategory;
use WebDevEtc\BlogEtc\Models\BlogEtcUploadedPhoto;
use WebDevEtc\BlogEtc\Requests\CreateBlogEtcPostRequest;
use WebDevEtc\BlogEtc\Requests\DeleteBlogEtcPostRequest;
use WebDevEtc\BlogEtc\Requests\UpdateBlogEtcPostRequest;
use WebDevEtc\BlogEtc\Traits\UploadFileTrait;
use Auth;
use WebDevEtc\BlogEtc\Controllers\BlogEtcCategoryAdminController;
use DB;
/**
 * Class BlogEtcAdminController
 * @package WebDevEtc\BlogEtc\Controllers
 */
class BlogEtcAdminController extends BackendController
{
    use UploadFileTrait;

    protected $blog_category_admin_controller;

    /**
     * BlogEtcAdminController constructor.
     */
    public function __construct(BlogEtcCategoryAdminController $blog_category_admin_controller)
    {
        $this->blog_category_admin_controller = $blog_category_admin_controller;
        $this->middleware(UserCanManageBlogPosts::class);

        if (!is_array(config('blogetc'))) {
            throw new \RuntimeException(
                'The config/blogetc.php does not exist. Publish the vendor files for the BlogEtc package by running the php artisan publish:vendor command'
            );
        }
    }

    /**
     * View all posts
     *
     * @return mixed
     */
    public function postindex($type)
    {
        return view('blogetc_admin::posts.index', ['type' => $type]);
    }

    public function data_table(Request $request, $type)
    {
        if ($type == 'blog') {
            $category_data = BlogEtcCategory::orderby('id', 'asc')
                ->Where('category_name', '=', 'Blog Category')
                ->get();
        } else {
            $category_data = BlogEtcCategory::where('parent_id', '=', 0)
                ->where('category_name', '!=', 'Blog Category')
                ->get();
        }

        $category_array = $this->blog_category_admin_controller->getBlogChild($category_data);

        foreach ($category_array as $category_data_array) {
            $category_id[] = $category_data_array['id'];
        }

        $blog_post_categories = DB::table('blog_etc_post_categories')
            ->whereIn('blog_etc_category_id', $category_id)
            ->get();
        // dd($blog_post_categories);

        foreach ($blog_post_categories as $blog_post_category) {
            $blog_post_id[] = $blog_post_category->blog_etc_post_id;
        }

        $blog_posts_data = BlogEtcPost::whereIn('id', $blog_post_id)->get();
        $blog_posts_data->type = $type;

        // $blog_posts_data = BlogEtcPost::all();
        return Datatables::of($blog_posts_data)
            ->addColumn('category', function ($blog_posts_data) {
                foreach ($blog_posts_data->categories as $category) {
                    return $category->category_name;
                }
            })

            ->addColumn('Author', function ($blog_posts_data) {
                $id = $blog_posts_data->id;
                $author_name = Auth::user()->name;
                return $author_name;
                //return $blog_posts_data->user_id;
            })

            ->addColumn('status', function ($blog_posts_data) use ($type) {
                // dd($blog_posts_data->is_published);
                $checked = '';
                if ($blog_posts_data->is_published == true) {
                    $checked = 'checked';
                    $value = 1;
                } else {
                    $value = 0;
                }

                $status =
                    '<span id="test" class="kt-switch kt-switch--outline kt-switch--icon kt-switch--primary">
                   <label id="switch_label">
                         <input type="checkbox" ' .
                    __($checked) .
                    ' value="' .
                    $value .
                    '"  class="blog_statusid" category_type = ' .
                    $type .
                    ' id="blog_statusid' .
                    __($blog_posts_data->id) .
                    '" name="codebar_status" onchange="blogStatus(' .
                    __($blog_posts_data->id) .
                    ')">
                         <span></span>
                   </label>
                </span>';
                return $status;
            })
            ->addColumn('action_buttons', function ($blog_posts_data) use ($type) {
                $edit_btn =
                    '<a href="' .
                    route('blogetc.admin.edit_post', [$blog_posts_data->id, 'type' => $type]) .
                    '"  data-toggle="tooltip"  data-placement="top" title="' .
                    __('buttons.general.crud.edit') .
                    '"  class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                         <i class="la la-edit"></i></a>';
                $delete =
                    '<a href="javascript:void(0)" class="btn btn-sm btn-clean btn-icon btn-icon-md btn_delete"
                         data-confirm-button-class="btn-warning"
                         data-trans-button-cancel="' .
                    __('buttons.general.cancel') .
                    '"
                         data-trans-button-confirm="' .
                    __('buttons.general.crud.delete') .
                    '"
                         data-trans-title="' .
                    __('strings.backend.general.are_you_sure') .
                    '"
                         data-toggle = "tooltip"
                         data-placement = "top" title="' .
                    __('buttons.general.crud.delete') .
                    '" id ="delete_category" delete_category =' .
                    $type .
                    ' onclick="KTDatatabledeleterow(' .
                    __($blog_posts_data->id) .
                    ');"
                         ><i class="la la-trash" ></i></a>';
                return $edit_btn . '' . $delete;
            })
            ->rawColumns(['category', 'Author', 'status', 'action_buttons'])
            ->make(true);
    }

    /**
     * Show form for creating new post
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */

    public function status($type, $id, $value)
    {
        if ($value == 0) {
            $status = BlogEtcPost::find($id);
            $status->is_published = 1;
            $status->save();
            $msg = 'Article has published';
            $title = 'Published :)';
            $msg_type = 'success';
            $chk_values = 1;
        }

        if ($value == 1) {
            $status = BlogEtcPost::find($id);
            $status->is_published = 0;
            $status->save();

            $msg = 'Article has unpublished.';
            $title = 'Unpublished :(';
            $msg_type = 'success';
            $chk_values = 0;
        }

        return response()->json(['msg' => $msg, 'title' => $title, 'msg_type' => $msg_type, 'value' => $chk_values]);
    }

    public function create_post($type)
    {
        $categories = BlogEtcCategory::where('parent_id', '=', 0)
            ->where('category_name', '=', 'Blog Category')
            ->orderby('id', 'asc')
            ->get();
        $allCategories = BlogEtcCategory::pluck('category_name', 'id')->all();
        $space_indent = '';
        $blog_category_array = $this->blog_category_admin_controller->getBlogChild($categories, $type);

        $other_categories = BlogEtcCategory::where('parent_id', '=', 0)
            ->where('category_name', '!=', 'Blog Category')
            ->get();
        $category_array = $this->blog_category_admin_controller->getBlogChild($other_categories, $type);
        //$blog_category_array = array_except($blog_category_array,0);
        return view('blogetc_admin::posts.add_post', [
            'categories' => $categories,
            'space_indent' => $space_indent,
            'blog_category_array' => $blog_category_array,
            'type' => $type,
            'category_array' => $category_array,
        ]);
    }

    /**['categories'=>$categories]
     * Save a new post
     *
     * @param CreateBlogEtcPostRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function store_post(CreateBlogEtcPostRequest $request, $type)
    {
        $request->merge(['title' => $request->post_title]);
        $new_blog_post = new BlogEtcPost($request->all());

        $this->processUploadedImages($request, $new_blog_post);

        if (!$new_blog_post->posted_at) {
            $new_blog_post->posted_at = Carbon::now();
        }

        $new_blog_post->user_id = Auth::user()->id;
        $new_blog_post->save();

        $new_blog_post->categories()->sync($request->categories);

        /*     Helpers::flash_message("Added post");
         event(new BlogPostAdded($new_blog_post)); */
        //return redirect($new_blog_post->edit_url());
        if ($type == 'blog') {
            return $this->redirectResponse($request, __('alerts.backend.article.created'));
        } else {
            return $this->redirectResponse($request, __('alerts.backend.cms_article.created'));
        }
    }

    /**
     * Show form to edit post
     *
     * @param $blogPostId
     * @return mixed
     */
    public function edit_post($type, $blogPostId)
    {
        $categories = BlogEtcCategory::where('parent_id', '=', 0)
            ->where('category_name', '=', 'Blog Category')
            ->orderby('id', 'asc')
            ->get();
        $allCategories = BlogEtcCategory::pluck('category_name', 'id')->all();
        $post = BlogEtcPost::findOrFail($blogPostId);
        $blog_category_array = $this->blog_category_admin_controller->getBlogChild($categories, $type);

        $other_categories = BlogEtcCategory::where('parent_id', '=', 0)
            ->where('category_name', '!=', 'Blog Category')
            ->get();
        $category_array = $this->blog_category_admin_controller->getBlogChild($other_categories, $type);
        //$blog_category_array = array_except($blog_category_array,0);
        return view('blogetc_admin::posts.edit_post', [
            'categories' => $categories,
            'blog_category_array' => $blog_category_array,
            'category_array' => $category_array,
            'type' => $type,
        ])->withPost($post);
    }

    /**
     * Save changes to a post
     *
     * @param UpdateBlogEtcPostRequest $request
     * @param $blogPostId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function update_post(UpdateBlogEtcPostRequest $request, $type, $blogPostId)
    {
        /** @var BlogEtcPost $post */
        $request->merge(['title' => $request->post_title]);
        $post = BlogEtcPost::findOrFail($blogPostId);
        $post->fill($request->all());
        $this->processUploadedImages($request, $post);

        $post->save();

        // $post->categories()->sync($request->categories());
        $post->categories()->sync($request->categories);

        Helpers::flash_message('Updated post');
        event(new BlogPostEdited($post));

        // return redirect($post->edit_url());
        if ($type == 'blog') {
            return $this->redirectResponse($request, __('alerts.backend.article.updated'));
        } else {
            return $this->redirectResponse($request, __('alerts.backend.cms_article.updated'));
        }
    }

    /**
     * Delete a post
     *
     * @param DeleteBlogEtcPostRequest $request
     * @param $blogPostId
     * @return mixed
     */
    public function destroy_post($type, $id)
    {
        $post = BlogEtcPost::findOrFail($id);
        $post->delete();
        /*
        // todo - delete the featured images?
        // At the moment it just issues a warning saying the images are still on the server.

        return view("blogetc_admin::posts.deleted_post")
            ->withDeletedPost($post); */
        return response()->json('deleted');
    }

    /**
     * Process any uploaded images (for featured image)
     *
     * @param BaseRequestInterface $request
     * @param $new_blog_post
     * @throws \Exception
     * @todo - next full release, tidy this up!
     */
    protected function processUploadedImages(BaseRequestInterface $request, BlogEtcPost $new_blog_post)
    {
        // dd($request);
        if (!config('blogetc.image_upload_enabled')) {
            // image upload was disabled
            return;
        }

        $this->increaseMemoryLimit();

        // to save in db later
        $uploaded_image_details = [];

        foreach ((array) config('blogetc.image_sizes') as $size => $image_size_details) {
            if ($image_size_details['enabled'] && ($photo = $request->get_image_file($size))) {
                // this image size is enabled, and
                // we have an uploaded image that we can use

                $uploaded_image = $this->UploadAndResize(
                    $new_blog_post,
                    $new_blog_post->title,
                    $image_size_details,
                    $photo
                );

                $new_blog_post->$size = $uploaded_image['filename'];
                $uploaded_image_details[$size] = $uploaded_image;
            }
        }

        // store the image upload.
        // todo: link this to the blogetc_post row.
        if (count(array_filter($uploaded_image_details)) > 0) {
            BlogEtcUploadedPhoto::create([
                'source' => 'BlogFeaturedImage',
                'uploaded_images' => $uploaded_image_details,
            ]);
        }
    }
}
