<?php

namespace WebDevEtc\BlogEtc\Controllers;
use Yajra\Datatables\Datatables;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Backend\BackendController;
use WebDevEtc\BlogEtc\Events\CategoryAdded;
use WebDevEtc\BlogEtc\Events\CategoryEdited;
use WebDevEtc\BlogEtc\Events\CategoryWillBeDeleted;
use WebDevEtc\BlogEtc\Helpers;
use WebDevEtc\BlogEtc\Middleware\UserCanManageBlogPosts;
use WebDevEtc\BlogEtc\Models\BlogEtcCategory;
use WebDevEtc\BlogEtc\Requests\DeleteBlogEtcCategoryRequest;
use WebDevEtc\BlogEtc\Requests\StoreBlogEtcCategoryRequest;
use WebDevEtc\BlogEtc\Requests\UpdateBlogEtcCategoryRequest;

/**
 * Class BlogEtcCategoryAdminController
 * @package WebDevEtc\BlogEtc\Controllers
 */
class BlogEtcCategoryAdminController extends BackendController
{
    /**
     * BlogEtcCategoryAdminController constructor.
     */
    public function __construct()
    {
        $this->middleware(UserCanManageBlogPosts::class);

    }

    /**
     * Show list of categories
     *
     * @return mixed
     */
    public function catindex($type){
        //$blog_category_data = BlogEtcCategory::all()->toArray();
        return view("blogetc_admin::categories.index",['type'=> $type]);
    }

    public function managedCategory($child_category, $blog_array, $space_indent, $type = null )
    {
        $space_indent.= "&nbsp;&nbsp;&nbsp;&nbsp;";
        foreach($child_category as $child)
        {
            $blog_array[] = ['category_name' =>$space_indent. "-".$child->category_name, 'id' => $child->id, 'state' => $child->state, 'type' => $type] ;
            if(count($child->childs))
            {
               $blog_array = $this->managedCategory($child->childs, $blog_array, $space_indent, $type);
            }
        }
        return $blog_array;
    }

    public function getBlogChild($blog_category_data, $type = null)
    {
       $space_indent = "&nbsp;&nbsp;&nbsp";
       $blog_array = [];
       foreach($blog_category_data as $category)
       {
         $blog_array[] = ['category_name' =>  $space_indent."-".$category->category_name, 'id' =>$category->id, 'state'=> $category->state, 'type'=> $type ];
         if(count($category->childs))
         {
             $blog_array = $this->managedCategory($category->childs, $blog_array, $space_indent, $type);
            //  foreach($category->childs as $child)
            //  {
            //     $blog_array[] = $child->category_name;
            //     if(count($child->childs))
            //     {
            //         $this->getBlogChild($child->childs);
            //     }
            //  }
         }
       }
    //    dd($blog_array);
       return $blog_array;
    }

    public function data_table($type)
    {
        if($type == "blog")
        {
          $blog_category_id = BlogEtcCategory::where('category_name', '=', 'Blog Category')->first();
          $blog_category_data = BlogEtcCategory::orderby('id','asc')->Where('parent_id', $blog_category_id['id'])->get();
          $category_array = $this->getBlogChild($blog_category_data,$type);
        }
        else
        {
            $other_category = BlogEtcCategory::where('parent_id', '=', 0)->where('category_name', '!=', 'Blog Category')->get();
            foreach($other_category as $category)
            {
                $other_category_id[] = $category->id;
            }

            $category_data = BlogEtcCategory::orderby('id','asc')->WhereIn('parent_id', $other_category_id)->get();

            $category_array = $this->getBlogChild($category_data, $type);

        }
        //   dd($category_array);
        //   $temp_cat_id = NULL;
          return Datatables::of($category_array)
          ->addColumn('name',function($category_array) {
            $temp_cat_id='';
            // dd($category_array);
             //dd($blog_category_data);
                return $category_array['category_name'];
            // if($blog_category_data->parent_id > 0)
            // {
            //     $cat_parent_id = $blog_category_data->parent_id;

            //     if($cat_parent_id == $temp_cat_id)
            //     {
            //       return "&nbsp;&nbsp;&nbsp;&nbsp;-&nbsp;".$blog_category_data->category_name;
            //     }
            //     else
            //     {
            //         $temp_cat_id .= $blog_category_data->parent_id;
            //         return "&nbsp;&nbsp;&nbsp;&nbsp;--".$blog_category_data->category_name;
            //     }
            // }
            //     else
            //     {
            //       $temp_cat_id .= $blog_category_data->id;
            //       return $blog_category_data->category_name;
            //     }



            })

          ->addColumn('status',function($category_array) {
             $checked='';
             if($category_array['state'] == 1)
             {
               $checked="checked";
             }
             $status=
                '<span id="test" class="kt-switch kt-switch--outline kt-switch--icon kt-switch--primary">
                   <label id="switch_label">
                         <input type="checkbox" '.__($checked).' value="'.__($category_array['state']).'"  class="category_staus" category_type ='.$category_array['type'].' id="category_statusid'.__($category_array['id']).'" name="codebar_status" onchange="categoryStatus('.__($category_array['id']).')">
                         <span></span>
                   </label>
                </span>';
             return $status;
          })
          ->addColumn('action_buttons', function($category_array) {
          $edit_btn = '<a href="'.route('blogetc.admin.categories.edit_category',[$category_array['id'], 'type' => $category_array['type']]).'"  data-toggle="tooltip"  data-placement="top" title="'.__('buttons.general.crud.edit').'"  class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                         <i class="la la-edit"></i></a>';
          $delete   = '<a href="javascript:void(0)" class="btn btn-sm btn-clean btn-icon btn-icon-md btn_delete"
                         data-confirm-button-class="btn-warning"
                         data-trans-button-cancel="'.__('buttons.general.cancel').'"
                         data-trans-button-confirm="'.__('buttons.general.crud.delete').'"
                         data-trans-title="'.__('strings.backend.general.are_you_sure').'"
                         data-toggle = "tooltip"
                         data-placement = "top" id = "delete_category" delete_category = '.$category_array['type'].' title="'.__('buttons.general.crud.delete').'" onclick="KTDatatabledeleterow('.__($category_array['id']).');"
                         ><i class="la la-trash" ></i></a>';
                         return  $edit_btn."".$delete;
          })
         ->rawColumns(['name','status','action_buttons'])
         ->make(true);

    }
    public function status($type,$id,$value)
    {

         if($value==0)
        {
            $status =  BlogEtcCategory::find($id);
            $status->state= 1;
            $status->save();
            $msg = "Category has published";
            $title = "Published :)";
            $msg_type = "success";
            $chk_values = 1 ;
        }

        if($value==1)
        {
            $status =  BlogEtcCategory::find($id);
            $status->state = 0;
            $status->save();
            $msg = "Category has unpublished.";
            $title = "Unpublished :(";
            $msg_type = "success";
            $chk_values = 0 ;
        }

        return response()->json(['msg'=>$msg, 'title'=>$title, 'msg_type' => $msg_type,'value' =>$chk_values ]);

    }






     /**
     * Show the form for creating new category
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create_category($type){
       // $categories = BlogEtcCategory::all()->toArray();
        $categories = BlogEtcCategory::where('parent_id', '=', 0)->where('category_name', '=' ,'Blog Category')->orderby('id','asc')->get();
        $allCategories = BlogEtcCategory::pluck('category_name','id')->all();
        $space_indent = '';

        $blog_category_array = $this->getBlogChild( $categories);
        // foreach($blog_category_array as $category_array)
        // {
        //     $category_id_array[] = $category_array['id'];
        // }

        // $other_categories = BlogEtcCategory::where('category_name', '=', 'Website FAQ')->orWhere('category_name', '=', 'Nutrition FAQ')->orWhere('category_name', '=', 'Fitness FAQ')->get();
        // $other_categories = BlogEtcCategory::whereNotIn('id', $category_id_array)->get();
        $other_categories = BlogEtcCategory::where('parent_id', '=', 0)->where('category_name', '!=', 'Blog Category')->get();
        $category_array = $this->getBlogChild( $other_categories );

        return view("blogetc_admin::categories.add_category",['categories'=>$categories,'space_indent'=>$space_indent,  'blog_category_array'=> $blog_category_array, 'type' => $type, 'category_array' => $category_array]);

    }

    /**
     * Store a new category
     *
     * @param StoreBlogEtcCategoryRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */

    public function store_category(StoreBlogEtcCategoryRequest $request, $type){
        $new_category = new BlogEtcCategory;
        $new_category->category_name = $request->category_name;
        $new_category->slug = $request->slug;
        $new_category->parent_id = $request->parent_id;
        $new_category->category_description = $request->category_description;

        $new_category->save();
        Helpers::flash_message("Saved new category");
        event(new CategoryAdded($new_category));
        //return redirect( route('blogetc.admin.categories.index'));
        return $this->redirectResponse($request, __('alerts.backend.category.created'));
    }



    /**
     * Show the edit form for category
     * @param $categoryId
     * @return mixed
     */
    public function edit_category($type, $categoryId){
        $category = BlogEtcCategory::findOrFail($categoryId);
       // $categories = BlogEtcCategory::all()->toArray();
       $categories = BlogEtcCategory::where('parent_id', '=', 0)->where('category_name', '=' ,'Blog Category')->orderby('id','asc')->get();
        $allCategories = BlogEtcCategory::pluck('category_name','id')->all();
        $space_indent = '';
        $blog_category_array = $this->getBlogChild( $categories);

        $other_categories = BlogEtcCategory::where('parent_id', '=', 0)->where('category_name', '!=', 'Blog Category')->get();
        $category_array = $this->getBlogChild( $other_categories);

        return view("blogetc_admin::categories.edit_category",['categories'=>$categories,'space_indent'=>$space_indent, 'blog_category_array' => $blog_category_array, 'category_array' => $category_array, 'type' => $type])->withCategory($category);
    }

    /**
     * Save submitted changes
     *
     * @param UpdateBlogEtcCategoryRequest $request
     * @param $categoryId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update_category(UpdateBlogEtcCategoryRequest $request, $type, $categoryId){
        /** @var BlogEtcCategory $category */
        $category = BlogEtcCategory::findOrFail($categoryId);
       if(isset($request->state))
       {
            $category->state = 1;
       }
       else
       {
            $category->state = 0;
       }
       $category->category_name = $request->category_name;
        $category->slug = $request->slug;
        $category->parent_id = $request->parent_id;
        $category->category_description = $request->category_description;
        $category->save();
       // Helpers::flash_message("Saved category changes");
        //event(new CategoryEdited($category));
        //return redirect($category->edit_url());
        return $this->redirectResponse($request, __('alerts.backend.category.updated'));

    }

    /**
     * Delete the category
     *
     * @param DeleteBlogEtcCategoryRequest $request
     * @param $categoryId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function destroy_category($type,$categoryId){


        /* Please keep this in, so code inspections don't say $request was unused. Of course it might now get marked as left/right parts are equal */

        $category = BlogEtcCategory::findOrFail($categoryId);
        event(new CategoryWillBeDeleted($category));
        $category->delete();
       // return view ("blogetc_admin::categories.deleted_category");
       return response()->json('deleted');
    }

}
