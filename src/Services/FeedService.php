<?php

namespace WebDevEtc\BlogEtc\Services;

use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Rumenx\Feed\Feed; // Updated namespace
use WebDevEtc\BlogEtc\Models\Post;

/**
 * Class BlogEtcFeedService.
 */
class FeedService
{
    /**
     * @var PostsService
     */
    private $postsService;

    /**
     * FeedService constructor.
     */
    public function __construct(PostsService $postsService)
    {
        $this->postsService = $postsService;
    }

    /**
     * Build the Feed object and populate it with blog posts.
     */
    public function getFeed(Feed $feed, string $feedType): Response
    {
        $userOrGuest = Auth::check()
            ? 'logged-in-'.Auth::id()
            : 'guest';

        $key = 'blogetc-'.$feedType.$userOrGuest;

        // Implementing caching manually using Laravel Cache
        $cacheMinutes = config('blogetc.rssfeed.cache_in_minutes', 60);

        if (cache()->has($key)) {
            return response(cache()->get($key), 200, ['Content-Type' => $this->getMimeType($feedType)]);
        }

        $content = $this->makeFreshFeed($feed, $feedType);

        cache()->put($key, $content, now()->addMinutes($cacheMinutes));

        return response($content, 200, ['Content-Type' => $this->getMimeType($feedType)]);
    }

    /**
     * Create fresh feed by passing latest blog posts.
     */
    protected function makeFreshFeed(Feed $feed, string $feedType): string
    {
        $blogPosts = $this->postsService->rssItems();

        $this->setupFeed($feed, $this->pubDate($blogPosts));

        /** @var Post $blogPost */
        foreach ($blogPosts as $blogPost) {
            $feed->addItem([
                'title'       => $blogPost->title,
                'author'      => $blogPost->authorString(),
                'link'        => $blogPost->url(),
                'pubDate'     => $blogPost->posted_at->toRssString(),
                'description' => $this->shortenText($blogPost->short_description),
                'content'     => $this->shortenText($blogPost->generateIntroduction()),
            ]);
        }

        return $feed->render($feedType);
    }

    /**
     * Basic set up of the Feed object.
     */
    protected function setupFeed(Feed $feed, Carbon $pubDate): Feed
    {
        $feed->setTitle(config('blogetc.rssfeed.title'));
        $feed->setDescription(config('blogetc.rssfeed.description'));
        $feed->setLink(route('blogetc.index'));
        $feed->setLanguage(config('blogetc.rssfeed.language'));
        $feed->setPubDate($pubDate->toRssString());

        // Save shortening preferences as custom meta (php-feed doesn't support text shortening natively)
        $feed->setCustom([
            'should_shorten_text' => config('blogetc.rssfeed.should_shorten_text', true),
            'text_limit' => config('blogetc.rssfeed.text_limit', 100)
        ]);

        return $feed;
    }

    /**
     * Return the first post posted_at date, or today if none exist.
     */
    protected function pubDate(Collection $blogPosts): Carbon
    {
        return $blogPosts->first()
            ? $blogPosts->first()->posted_at
            : Carbon::now();
    }

    /**
     * Mimic Laravelium's text shortening feature.
     */
    protected function shortenText(string $text): string
    {
        if (!config('blogetc.rssfeed.should_shorten_text', true)) {
            return $text;
        }

        $limit = config('blogetc.rssfeed.text_limit', 100);

        return \Str::limit(strip_tags($text), $limit);
    }

    /**
     * Set proper Content-Type headers based on feed type.
     */
    protected function getMimeType(string $feedType): string
    {
        return match ($feedType) {
            'rss'  => 'application/rss+xml',
            'atom' => 'application/atom+xml',
            default => 'application/xml',
        };
    }
}
