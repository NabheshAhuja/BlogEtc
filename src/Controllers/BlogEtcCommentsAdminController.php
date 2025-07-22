<?php

namespace WebDevEtc\BlogEtc\Controllers;

use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Backend\BackendController;
use Carbon\Carbon;
use WebDevEtc\BlogEtc\Events\CommentApproved;
use WebDevEtc\BlogEtc\Events\CommentWillBeDeleted;
use WebDevEtc\BlogEtc\Helpers;
use WebDevEtc\BlogEtc\Middleware\UserCanManageBlogPosts;
use WebDevEtc\BlogEtc\Models\BlogEtcComment;
use App\Models\User;
use Auth;
/**
 * Class BlogEtcCommentsAdminController
 * @package WebDevEtc\BlogEtc\Controllers
 */
class BlogEtcCommentsAdminController extends BackendController
{
    /**
     * BlogEtcCommentsAdminController constructor.
     */
    public function __construct()
    {
       $this->middleware(UserCanManageBlogPosts::class);
    }

    /**
     * Show all comments (and show buttons with approve/delete)
     *
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
         return view("blogetc_admin::comments.index");
     }

    public function data_table(Request $request)
    {
        $comments = BlogEtcComment::withoutGlobalScopes()->orderBy("created_at", "desc")->with("post")->get()->toArray();
        //dd($comments);
          return Datatables::of($comments)

          ->addColumn('comment',function($comments)
          {
              return  '<p class="text-justify">'.substr($comments['comment'],0,200).'<br/><a href="javascript:void(0);" onclick="comment_view('.__($comments['id']).')">Read more...</a></p>';
              //return $blog_posts_data->user_id;
          })

          ->addColumn('post_title',function($comments)
          {
              return  $comments['post']['title'];
              //return $blog_posts_data->user_id;
          })

          ->addColumn('created_at',function($comments)
          {
              return  $comments['created_at'];
              //return $blog_posts_data->user_id;
          })

          ->addColumn('status',function($comments) {
             // dd($blog_posts_data->is_published);
             $checked='';
             if($comments['approved'] == 1)
             {
               $checked="checked";
               $value = 1;
             }
             else
             {
                $value = 0;
             }
             $status=
                '<span id="test" class="kt-switch kt-switch--outline kt-switch--icon kt-switch--primary">
                   <label id="switch_label">
                         <input type="checkbox" '.__($checked).' value="'.$value.'"  class="blog_commentid" id="blog_commentid'.__($comments['id']).'" name="codebar_status" onchange="blogCommentApprove('.__($comments['id']).')">
                         <span></span>
                   </label>
                </span>';
             return $status;
          })
          ->addColumn('action_buttons', function($comments) {
             $view = '<a href="javascript:void(0)" title="View Comment" class="btn btn-sm btn-clean btn-icon btn-icon-md" data-toggle="modal" data-target="#comment_show" onclick="comment_view('.__($comments['id']).')"><i class="la la-commenting-o"></i></a>';

             $delete = '<a href="javascript:void(0)" class="btn btn-sm btn-clean btn-icon btn-icon-md btn_delete"
                        data-confirm-button-class="btn-warning"
                        data-trans-button-cancel="'.__('buttons.general.cancel').'"
                        data-trans-button-confirm="'.__('buttons.general.crud.delete').'"
                        data-trans-title="'.__('strings.backend.general.are_you_sure').'"
                        data-toggle = "tooltip"
                        data-placement = "top" title="'.__('buttons.general.crud.delete').'" onclick="KTDatatabledeleterow('.__($comments['id']).');"
                        ><i class="la la-trash" ></i></a>';
                        return   $view.'  '.$delete;
                })

         ->rawColumns(['comment','post_title','created_at','status','action_buttons'])
         ->make(true);
     }

     public function status($type,$id,$value)
     {
          if($value==0)
         {
             $status =  BlogEtcComment::withoutGlobalScopes()->findOrFail($id);
             $status->approved= 1;
             $status->save();

             $msg = "Comment has published";
             $title = "Published :)";
             $msg_type = "success";
             $chk_values = 1 ;
         }

         if($value==1)
         {
             $status = BlogEtcComment::withoutGlobalScopes()->findOrFail($id);
             $status->approved = 0;
             $status->save();

             $msg = "Comment has unpublished.";
             $title = "Unpublished :(";
             $msg_type = "success";
             $chk_values = 0 ;
         }

         return response()->json(['msg'=>$msg, 'title'=>$title, 'msg_type' => $msg_type,'value' =>$chk_values]);

     }


    public function commentDetails($type,$id)
    {

        $comment = BlogEtcComment::withoutGlobalScopes()->where('id','=',$id)->get()->toArray();
        //dd();
        $html='';

        $html.='<div class="modal-header">
                    <h6 class="modal-title">Author : '.ucfirst($comment[0]['author_name']).'</h6>

                   <button type="button" class="close" data-dismiss="modal" aria-label="Close"> </button>
                </div>
                <div class="modal-body">
                    <div class="kt-portlet__body table-responsive">
                    <p class="modal-title">Post Date : '. date('d-m-Y',strtotime($comment[0]['created_at'])).'</p>
                    <br/>
                       <p class="text-justify">
                        '.$comment[0]['comment'].'
                       </p>
                    </div>
                </div>';


        return $html;

    }



     public function destroy($type,$blogCommentId)
    {
        $comment = BlogEtcComment::withoutGlobalScopes()->findOrFail($blogCommentId);
        event(new CommentWillBeDeleted($comment));

        $comment->delete();

        return response()->json('deleted');
    }


}
