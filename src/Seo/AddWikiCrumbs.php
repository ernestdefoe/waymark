<?php

namespace Ernestdefoe\Waymark\Seo;

use FoF\Seo\Breadcrumb\Crumb;
use FoF\Seo\Event\PreparingPageMeta;

/**
 * With FoF SEO, the wiki's pages get its BreadcrumbList instead of Waymark's.
 *
 * FoF SEO knows nothing of the wiki, so its trail there is Home alone, which
 * renders as nothing. This fills it in with the visible trail. Listened to on
 * PreparingPageMeta rather than BuildingBreadcrumb because only it carries the
 * page's Document, where the wiki left the article (see WikiCrumbs).
 */
class AddWikiCrumbs
{
    public function __construct(protected WikiCrumbs $wiki)
    {
    }

    public function handle(PreparingPageMeta $event): void
    {
        $crumbs = $this->wiki->crumbs($event->document, $event->request);
        $trail = $event->properties->breadcrumb();

        // A driver of its own already described the page.
        if ($crumbs === [] || $trail->count() !== 1) {
            return;
        }

        $last = array_key_last($crumbs);

        foreach ($crumbs as $i => [$name, $url]) {
            // The page itself carries no url: search engines use the page's own.
            $trail->push(new Crumb($name, $i === $last ? null : $url));
        }
    }
}
