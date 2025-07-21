<?php

namespace WebDevEtc\BlogEtc\Controllers;

use App\Http\Controllers\Controller;
use Auth;
use Carbon\Carbon;
use Rumenx\Feed\Feed; // Updated namespace
use WebDevEtc\BlogEtc\Models\Post;
use WebDevEtc\BlogEtc\Requests\FeedRequest;

class FeedController extends Controller
{
    public function feed(FeedRequest $request, Feed $feed)
    {
        $user_or_guest = Auth::check() ? Auth::user()->id : 'guest';

        // php-feed doesn't have built-in caching like Laravelium\Feed, implement caching manually if needed
        $cacheMinutes = config('blogetc.rssfeed.cache_in_minutes', 60);
        $cacheKey = 'blogetc-'.$request->getFeedType().$user_or_guest;

        // If using Laravel cache
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $content = $this->makeFreshFeed($feed, $request->getFeedType());

        cache()->put($cacheKey, $content, now()->addMinutes($cacheMinutes));

        return $content;
    }

    protected function makeFreshFeed(Feed $feed, string $format)
    {
        $posts = Post::orderBy('posted_at', 'desc')
            ->limit(config('blogetc.rssfeed.posts_to_show_in_rss_feed', 10))
            ->with('author')
            ->get();

        $this->setupFeed($feed, $posts);

        /** @var Post $post */
        foreach ($posts as $post) {
            $feed->addItem([
                'title'       => $post->title,
                'author'      => $post->authorString(),
                'link'        => $post->url(),
                'pubDate'     => $post->posted_at->toRssString(),
                'description' => $post->short_description,
                'content'     => $post->generateIntroduction(),
            ]);
        }

        // `render()` in php-feed requires format to be specified
        return $feed->render($format); // e.g., 'rss', 'atom'
    }

    protected function setupFeed(Feed $feed, $posts)
    {
        $feed->setTitle(config('app.name').' Blog');
        $feed->setDescription(config('blogetc.rssfeed.description', 'Our blog RSS feed'));
        $feed->setLink(route('blogetc.index'));
        $feed->setLanguage(config('blogetc.rssfeed.language', 'en'));
        $feed->setPubDate(isset($posts[0]) ? $posts[0]->posted_at->toRssString() : Carbon::now()->subYear()->toRssString());

        // Optional customizations if needed (not all php-feed methods may exist)
        // Simulating shortening by limiting description/content length manually
        $feed->setCustom([
            'text_limit' => config('blogetc.rssfeed.text_limit', 100),
            'should_shorten_text' => config('blogetc.rssfeed.should_shorten_text', true),
        ]);
    }
}
