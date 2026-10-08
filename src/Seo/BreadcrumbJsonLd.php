<?php

namespace Ernestdefoe\Waymark\Seo;

use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The trail as schema.org BreadcrumbList structured data, for search engines.
 *
 * 🚨 Only when FoF SEO is NOT enabled. FoF SEO already publishes a
 * BreadcrumbList on every page (and AgreeWithWaymark keeps it saying what the
 * visible trail says); a second list on the same page gives search engines two
 * answers to one question.
 *
 * The visible trail is drawn in the browser, so this is the server's copy of
 * the same rules, for the two kinds of page where a search result shows a
 * breadcrumb — discussions and tags — and it must agree with trails.js:
 *
 *   discussion  Home › parent tag › child tag › the discussion   (no "Tags")
 *   tag         Home › Tags › parent tag › the tag   ("Tags" left out when the
 *                                                     tags page is home)
 *
 * Built as the visitor sees the page: a discussion or tag they cannot see gets
 * no structured data at all, rather than a trail naming it.
 */
class BreadcrumbJsonLd
{
    public function __construct(
        protected ExtensionManager $extensions,
        protected SettingsRepositoryInterface $settings,
        protected TranslatorInterface $translator,
        protected UrlGenerator $url,
        protected SlugManager $slugs,
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        if ($this->extensions->isEnabled('fof-seo')) {
            return;
        }

        try {
            $crumbs = $this->crumbs($request);
        } catch (\Throwable $e) {
            // Structured data is a nicety. It never costs a visitor the page.
            return;
        }

        if (count($crumbs) < 2) {
            return;
        }

        $items = [];
        foreach ($crumbs as $i => [$name, $url]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
        }

        // JSON_HEX_TAG: a title containing "</script>" cannot end the block early.
        $json = json_encode(
            ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );

        $document->head[] = '<script type="application/ld+json">'.$json.'</script>';
    }

    /** @return list<array{0: string, 1: string}> */
    protected function crumbs(ServerRequestInterface $request): array
    {
        $route = $request->getAttribute('routeName');
        $params = (array) $request->getAttribute('routeParameters');
        $tagsOn = $this->extensions->isEnabled('flarum-tags');
        $actor = RequestUtil::getActor($request);

        if ($route === 'discussion' && $this->on('show_discussions')) {
            $id = (int) explode('-', (string) ($params['id'] ?? ''), 2)[0];
            $discussion = $id ? Discussion::whereVisibleTo($actor)->find($id) : null;
            if (! $discussion) {
                return [];
            }

            $chain = $tagsOn ? array_map(fn (Tag $tag) => $this->tagCrumb($tag), $this->primaryLineage($discussion, $actor)) : [];

            return array_values(array_filter([
                $this->home(),
                $chain && $this->tagsCrumbMode() === 'always' ? $this->tagsCrumb() : null,
                ...$chain,
                [$discussion->title, $this->url->to('forum')->route('discussion', ['id' => $this->slugs->forResource(Discussion::class)->toSlug($discussion)])],
            ]));
        }

        if ($route === 'tag' && $tagsOn && $this->on('show_tags')) {
            $tag = Tag::whereVisibleTo($actor)->where('slug', (string) ($params['slug'] ?? ''))->first();
            if (! $tag) {
                return [];
            }

            return array_values(array_filter([
                $this->home(),
                $this->tagsCrumb(),
                ...array_map(fn (Tag $t) => $this->tagCrumb($t), $this->lineage($tag, $actor)),
            ]));
        }

        return [];
    }

    /**
     * Its child tag and that tag's parent, or its primary tag alone, exactly as
     * trails.js picks it. Secondary tags (no position, no parent) describe a
     * discussion; they are not where it lives.
     *
     * @return list<Tag>
     */
    protected function primaryLineage(Discussion $discussion, User $actor): array
    {
        $tags = $discussion->tags()->whereVisibleTo($actor)->get();

        $child = $tags->first(fn (Tag $tag) => $tag->parent_id !== null);
        if ($child) {
            return $this->lineage($child, $actor);
        }

        $primary = $tags->first(fn (Tag $tag) => $tag->position !== null);

        return $primary ? [$primary] : [];
    }

    /** @return list<Tag> */
    protected function lineage(Tag $tag, User $actor): array
    {
        $chain = [];
        $guard = 0;

        while ($tag && $guard++ < 10) {
            array_unshift($chain, $tag);
            $tag = $tag->parent_id ? Tag::whereVisibleTo($actor)->find($tag->parent_id) : null;
        }

        return $chain;
    }

    protected function home(): array
    {
        $label = trim((string) $this->settings->get('ernestdefoe-waymark.home_text'));

        if ($label === '') {
            $label = $this->settings->get('ernestdefoe-waymark.home_label') === 'title'
                ? (string) $this->settings->get('forum_title')
                : $this->translator->trans('ernestdefoe-waymark.forum.home');
        }

        $url = match (true) {
            $this->homeIsTags() => $this->url->to('forum')->route('tags'),
            $this->settings->get('ernestdefoe-waymark.home_target') === 'all' => $this->url->to('forum')->route('index'),
            default => rtrim($this->url->to('forum')->base(), '/').'/',
        };

        return [$label, $url];
    }

    /**
     * "Tags" (or the forum's own word for it), unless the tags page is Home
     * already — as the forum's root, or because Home was pointed at it.
     */
    protected function tagsCrumb(): ?array
    {
        if (trim((string) $this->settings->get('default_route'), '/') === 'tags' || $this->homeIsTags()
            || $this->tagsCrumbMode() === 'never') {
            return null;
        }

        $label = trim((string) $this->settings->get('ernestdefoe-waymark.tags_text'));

        return [$label !== '' ? $label : $this->translator->trans('flarum-tags.ref.tags'), $this->url->to('forum')->route('tags')];
    }

    /** 'never', 'tags' (tag pages only) or 'always' (discussions too). */
    protected function tagsCrumbMode(): string
    {
        $mode = $this->settings->get('ernestdefoe-waymark.tags_crumb');

        return in_array($mode, ['never', 'always'], true) ? $mode : 'tags';
    }

    protected function homeIsTags(): bool
    {
        return $this->settings->get('ernestdefoe-waymark.home_target') === 'tags'
            && $this->extensions->isEnabled('flarum-tags');
    }

    protected function tagCrumb(Tag $tag): array
    {
        return [$tag->name, $this->url->to('forum')->route('tag', ['slug' => $tag->slug])];
    }

    protected function on(string $key): bool
    {
        return (bool) $this->settings->get('ernestdefoe-waymark.'.$key);
    }
}
