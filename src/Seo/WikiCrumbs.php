<?php

namespace Ernestdefoe\Waymark\Seo;

use Flarum\Frontend\Document;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The server's copy of the linkrobins/wiki trails in wiki.js, for search
 * engines:
 *
 *   /wiki            Home › Wiki
 *   /wiki/{article}  Home › Wiki › Category › Article   (no category: Home › Wiki › Article)
 *
 * 🚨 Zero queries. The wiki's own route content (priority 100) has already
 * loaded the article through its API, as the visitor, with its category
 * included, and left that document in the page payload for the forum app.
 * Waymark reads it from there; anything the visitor may not see is never in
 * it. Category pages (/wiki?category=3) are canonical to /wiki, so they get
 * the index's trail.
 */
class WikiCrumbs
{
    public function __construct(
        protected UrlGenerator $url,
        protected TranslatorInterface $translator,
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * The crumbs after Home, the last being the page itself.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function crumbs(Document $document, ServerRequestInterface $request): array
    {
        $route = $request->getAttribute('routeName');

        if (($route !== 'linkrobins-wiki.index' && $route !== 'linkrobins-wiki.show')
            || ! $this->settings->get('ernestdefoe-waymark.show_other')) {
            return [];
        }

        $index = $this->url->to('forum')->route('linkrobins-wiki.index');
        // The wiki as the forum's home page: Home already is the wiki.
        $wiki = trim((string) $this->settings->get('default_route'), '/') === 'wiki'
            ? null
            : [$this->translator->trans('linkrobins-wiki.forum.nav'), $index];

        if ($route === 'linkrobins-wiki.index') {
            return $wiki ? [$wiki] : [];
        }

        $api = $document->payload['apiDocument'] ?? null;
        $article = is_object($api) ? ($api->data ?? null) : null;

        if (! is_object($article) || ($article->type ?? null) !== 'linkrobins-wiki-articles'
            || ! empty($article->attributes->isDeleted) || ! empty($article->attributes->isDraft)) {
            return [];
        }

        $categoryId = $article->relationships->category->data->id ?? null;
        $category = null;

        foreach ($categoryId === null ? [] : ($api->included ?? []) as $resource) {
            if ($resource->type === 'linkrobins-wiki-categories' && (string) $resource->id === (string) $categoryId) {
                $category = [(string) $resource->attributes->name, $index.'?category='.rawurlencode((string) $resource->id)];
                break;
            }
        }

        return array_values(array_filter([
            $wiki,
            $category,
            [(string) $article->attributes->title, (string) $document->canonicalUrl],
        ]));
    }
}
