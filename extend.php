<?php

use Ernestdefoe\Waymark\Seo\AgreeWithWaymark;
use Flarum\Extend;

$extenders = [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Settings())
        ->default('ernestdefoe-waymark.position', 'below')
        ->default('ernestdefoe-waymark.home_label', 'home')
        ->default('ernestdefoe-waymark.mobile', 'collapse')
        ->default('ernestdefoe-waymark.show_discussions', true)
        ->default('ernestdefoe-waymark.show_tags', true)
        ->default('ernestdefoe-waymark.show_users', true)
        ->default('ernestdefoe-waymark.show_messages', true)
        ->default('ernestdefoe-waymark.show_other', true)
        ->serializeToForum('waymarkPosition', 'ernestdefoe-waymark.position')
        ->serializeToForum('waymarkHomeLabel', 'ernestdefoe-waymark.home_label')
        ->serializeToForum('waymarkMobile', 'ernestdefoe-waymark.mobile')
        ->serializeToForum('waymarkDiscussions', 'ernestdefoe-waymark.show_discussions', 'boolval')
        ->serializeToForum('waymarkTags', 'ernestdefoe-waymark.show_tags', 'boolval')
        ->serializeToForum('waymarkUsers', 'ernestdefoe-waymark.show_users', 'boolval')
        ->serializeToForum('waymarkMessages', 'ernestdefoe-waymark.show_messages', 'boolval')
        ->serializeToForum('waymarkOther', 'ernestdefoe-waymark.show_other', 'boolval'),
];

/*
 * 🚨 FoF SEO is optional, never a dependency.
 *
 * When it is installed it already publishes a breadcrumb trail to search
 * engines, and that trail should say what the visible one says. When it is
 * not, there is nothing on the server to agree with, and Waymark does no
 * server work at all.
 */
if (class_exists(\FoF\Seo\Event\BuildingBreadcrumb::class)) {
    $extenders[] = (new Extend\Event())->listen(\FoF\Seo\Event\BuildingBreadcrumb::class, AgreeWithWaymark::class);
}

return $extenders;
