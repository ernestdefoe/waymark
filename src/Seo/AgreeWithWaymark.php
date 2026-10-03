<?php

namespace Ernestdefoe\Waymark\Seo;

use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Seo\Breadcrumb\Crumb;
use FoF\Seo\Event\BuildingBreadcrumb;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Keeps FoF SEO's breadcrumbs (the ones search engines read) saying what the
 * visible trail says.
 *
 * Only registered when FoF SEO is installed. Two differences are corrected:
 *
 *  - The root crumb. FoF SEO names it after the forum; Waymark's trail says
 *    "Home" unless the forum chose its title. Both now use the same word.
 *
 *  - The "Tags" crumb on a forum whose home IS the tags page. FoF SEO links it
 *    to /tags, an alias of the home page, so the trail reads Home › Tags › SEC
 *    with two crumbs for one place. Waymark drops it there, and so does this.
 *    Everywhere else it is kept and translated: FoF SEO writes it in English.
 *
 *  - A profile's Discussions tab. FoF SEO describes /u/name/discussions as the
 *    profile itself; the visible trail says Home › Name › Discussions, so the
 *    name becomes a link to the profile and the tab is added after it.
 */
class AgreeWithWaymark
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected TranslatorInterface $translator,
    ) {
    }

    public function handle(BuildingBreadcrumb $event): void
    {
        $crumbs = $event->trail->all();

        if ($crumbs === []) {
            return;
        }

        $tagsIsHome = trim((string) $this->settings->get('default_route'), '/') === 'tags';
        $tagsLabel = $this->translator->trans('flarum-tags.ref.tags');

        $out = [];

        foreach ($crumbs as $i => $crumb) {
            if ($i === 0) {
                if ($this->settings->get('ernestdefoe-waymark.home_label') !== 'title') {
                    $crumb = new Crumb(
                        $this->translator->trans('ernestdefoe-waymark.forum.home'),
                        $crumb->url,
                        $crumb->type,
                        $crumb->extra,
                    );
                }

                $out[] = $crumb;
                continue;
            }

            if ($this->isTagsCrumb($crumb)) {
                if ($tagsIsHome) {
                    continue;
                }

                if ($tagsLabel !== 'flarum-tags.ref.tags') {
                    $crumb = new Crumb($tagsLabel, $crumb->url, $crumb->type, $crumb->extra);
                }
            }

            $out[] = $crumb;
        }

        $this->addProfileTab($out, $event->request->getUri()->getPath());

        $event->trail->set($out);
    }

    /**
     * Only the Discussions tab: it is public and indexable. Security and the
     * rest are private to the member, and search engines never see them.
     *
     * @param list<Crumb> $crumbs
     */
    private function addProfileTab(array &$crumbs, string $path): void
    {
        if (! preg_match('#/u/([^/]+)/discussions/?$#', $path, $m) || count($crumbs) < 2) {
            return;
        }

        $last = array_key_last($crumbs);
        $home = rtrim((string) $crumbs[0]->url, '/');

        $crumbs[$last] = new Crumb($crumbs[$last]->name, $home.'/u/'.$m[1], $crumbs[$last]->type, $crumbs[$last]->extra);
        $crumbs[] = new Crumb($this->translator->trans('core.ref.discussions'));
    }

    private function isTagsCrumb(Crumb $crumb): bool
    {
        return $crumb->url !== null && (bool) preg_match('#/tags/?$#', $crumb->url);
    }
}
