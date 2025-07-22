<?php

namespace WebDevEtc\BlogEtc\Requests;


use Illuminate\Validation\Rule;
use WebDevEtc\BlogEtc\Models\BlogEtcPost;
use WebDevEtc\BlogEtc\Requests\Traits\HasCategoriesTrait;
use WebDevEtc\BlogEtc\Requests\Traits\HasImageUploadTrait;

class UpdateBlogEtcPostRequest  extends BaseBlogEtcPostRequest {

    use HasCategoriesTrait;
    use HasImageUploadTrait;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $return = $this->baseBlogPostRules();
        $return['image_large'] = 'mimes:jpg,png,jpeg';
        $return['image_medium'] = 'mimes:jpg,png,jpeg';
        $return['image_small'] = 'mimes:jpg,png,jpeg';
        $return['slug'] [] = Rule::unique("blog_etc_posts", "slug")->ignore($this->route()->parameter("blogPostId"));
        return $return;
    }
}
