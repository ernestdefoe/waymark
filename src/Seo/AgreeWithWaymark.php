<?php

namespace Ernestdefoe\Waymark\Seo;

use Flarum\Extension\ExtensionManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Guest;
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
 *    name becomes a link to the profile and the tab is added after it. With
 *    the full profile trail, the Posts tab is named too and Members leads.
 */
class AgreeWithWaymark
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected TranslatorInterface $translator,
        protected ExtensionManager $extensions,
    ) {
    }

    public function handle(BuildingBreadcrumb $event): void
    {
        $crumbs = $event->trail->all();

        if ($crumbs === []) {
            return;
        }

        $homeIsTags = $this->settings->get('ernestdefoe-waymark.home_target') === 'tags';
        $tagsIsHome = $homeIsTags || trim((string) $this->settings->get('default_route'), '/') === 'tags';
        $tagsText = trim((string) $this->settings->get('ernestdefoe-waymark.tags_text'));
        $tagsLabel = $tagsText !== '' ? $tagsText : $this->translator->trans('flarum-tags.ref.tags');
        $homeText = trim((string) $this->settings->get('ernestdefoe-waymark.home_text'));
        $tagsNever = $this->settings->get('ernestdefoe-waymark.tags_crumb') === 'never';

        $out = [];
        // The forum's root, before the first crumb may be pointed at /tags.
        $root = rtrim((string) $crumbs[0]->url, '/');

        foreach ($crumbs as $i => $crumb) {
            if ($i === 0) {
                $name = $crumb->name;
                if ($homeText !== '') {
                    $name = $homeText;
                } elseif ($this->settings->get('ernestdefoe-waymark.home_label') !== 'title') {
                    $name = $this->translator->trans('ernestdefoe-waymark.forum.home');
                }

                // Home pointed at the tags page or the discussions list: the
                // root crumb leads there.
                $target = $this->settings->get('ernestdefoe-waymark.home_target');
                $url = $crumb->url === null ? null : match (true) {
                    $homeIsTags => $root.'/tags',
                    $target === 'all' => $root.'/all',
                    default => $crumb->url,
                };

                $out[] = new Crumb($name, $url, $crumb->type, $crumb->extra);
                continue;
            }

            // The tags page's own crumb, on the tags page, carries no link to
            // recognise it by: it is the last crumb of /tags.
            $onTagsPage = $crumb->url === null && $i === array_key_last($crumbs)
                && preg_match('#/tags/?$#', $event->request->getUri()->getPath());

            if ($this->isTagsCrumb($crumb) || $onTagsPage) {
                // Home already is the tags page, or the forum wants it out of
                // its trails (the tags page keeps its own crumb).
                if ($tagsIsHome || ($tagsNever && ! $onTagsPage)) {
                    continue;
                }

                if ($tagsLabel !== 'flarum-tags.ref.tags') {
                    $crumb = new Crumb($tagsLabel, $crumb->url, $crumb->type, $crumb->extra);
                }
            }

            $out[] = $crumb;
        }

        // Everywhere: a discussion's trail gains the tags page above its first
        // tag, as Waymark's own trail does. Only above a tag, never as
        // "Home › Tags › title".
        if (! $tagsIsHome && $this->settings->get('ernestdefoe-waymark.tags_crumb') === 'always'
            && preg_match('#/d/[^/]+#', $event->request->getUri()->getPath()) && count($out) > 2
            && ! array_filter($out, fn (Crumb $c) => $this->isTagsCrumb($c))) {
            array_splice($out, 1, 0, [new Crumb($tagsLabel, $root.'/tags')]);
        }

        // On the tags page itself, when that page is Home: one crumb, not
        // "Home › Tags" naming the same place twice.
        if ($tagsIsHome && preg_match('#/tags/?$#', $event->request->getUri()->getPath())) {
            $out = array_slice($out, 0, 1);
        }

        $this->addProfileTab($out, $event->request->getUri()->getPath(), $root);

        $event->trail->set($out);
    }

    /**
     * Only the Discussions tab: it is public and indexable. Security and the
     * rest are private to the member, and search engines never see them.
     *
     * @param list<Crumb> $crumbs
     */
    private function addProfileTab(array &$crumbs, string $path, string $home): void
    {
        if (! preg_match('#/u/([^/]+)(/discussions)?/?$#', $path, $m) || count($crumbs) < 2) {
            return;
        }

        $full = $this->settings->get('ernestdefoe-waymark.users_crumb') === 'full';
        $tab = isset($m[2]) ? 'core.ref.discussions' : ($full ? 'core.ref.posts' : null);

        if ($tab !== null) {
            $last = array_key_last($crumbs);
            $crumbs[$last] = new Crumb($crumbs[$last]->name, $home.'/u/'.$m[1], $crumbs[$last]->type, $crumbs[$last]->extra);
            $crumbs[] = new Crumb($this->translator->trans($tab));
        }

        // The full trail's Members crumb, only where it has somewhere to lead:
        // a crumb in the middle of a trail needs a link, and search engines
        // visit as guests, so the guest must be able to open the directory.
        if ($full && $this->extensions->isEnabled('fof-user-directory') && (new Guest())->can('seeUserList')) {
            array_splice($crumbs, 1, 0, [new Crumb($this->translator->trans('ernestdefoe-waymark.forum.members'), $home.'/users')]);
        }
    }

    private function isTagsCrumb(Crumb $crumb): bool
    {
        return $crumb->url !== null && (bool) preg_match('#/tags/?$#', $crumb->url);
    }
}
