<?php

namespace Ernestdefoe\Waymark\Tests\integration\forum;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Locale\LocaleManager;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * With FoF SEO enabled, its breadcrumb list (the one search engines read) is
 * corrected to say what Waymark's visible trail says, and Waymark publishes
 * none of its own.
 */
class AgreeWithWaymarkTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'fof-seo', 'ernestdefoe-waymark');

        $this->setting('forum_title', 'Garage Talk');

        $this->prepareDatabase([
            User::class => [['joined_at' => Carbon::now()] + $this->normalUser()],
            Tag::class => [
                ['id' => 1, 'name' => 'Cars', 'slug' => 'cars', 'position' => 0, 'parent_id' => null],
                ['id' => 2, 'name' => 'Ford', 'slug' => 'ford', 'position' => 0, 'parent_id' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Mustang restoration', 'slug' => 'mustang-restoration', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>One</p></t>', 'created_at' => Carbon::now()],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 1, 'tag_id' => 2],
            ],
        ]);
    }

    /** @return list<array{0: string, 1: ?string}> name and URL of each crumb in the page's one breadcrumb list */
    private function trail(string $path): array
    {
        // The test harness enables extensions without their onEnable() step,
        // so the translation catalogue cached on disk lacks their strings, as
        // it would on a forum that never cleared its cache. Load them.
        $locales = $this->app()->getContainer()->make(LocaleManager::class);
        $locales->addTranslations('en', __DIR__.'/../../../vendor/flarum/tags/locale/en.yml');
        $locales->addTranslations('en', __DIR__.'/../../../locale/en.yml');
        $locales->clearCache();

        $response = $this->send($this->request('GET', $path));
        $this->assertSame(200, $response->getStatusCode(), $path);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getBody(), $blocks);

        $lists = [];
        foreach ($blocks[1] as $json) {
            $data = json_decode($json, true);
            foreach ($data['@graph'] ?? [$data] as $node) {
                if (($node['@type'] ?? null) === 'BreadcrumbList') {
                    $lists[] = $node;
                }
            }
        }

        $this->assertCount(1, $lists, 'FoF SEO\'s list alone: Waymark adds no second one');

        return array_map(fn ($item) => [$item['name'], $item['item']['url'] ?? null], $lists[0]['itemListElement']);
    }

    #[Test]
    public function the_root_crumb_is_called_home_as_on_the_visible_trail()
    {
        $this->assertSame([
            ['Home', 'http://localhost/'],
            ['Cars', 'http://localhost/t/cars'],
            ['Ford', 'http://localhost/t/ford'],
            ['Mustang restoration', null],
        ], $this->trail('/d/1-mustang-restoration'));
    }

    #[Test]
    public function the_root_crumb_keeps_the_forum_title_when_the_forum_chose_it()
    {
        $this->setting('ernestdefoe-waymark.home_label', 'title');

        $this->assertSame('Garage Talk', $this->trail('/d/1-mustang-restoration')[0][0]);
    }

    #[Test]
    public function the_tags_crumb_takes_the_forums_word_for_it()
    {
        $this->setting('ernestdefoe-waymark.tags_text', 'Topics');

        $this->assertSame([
            ['Home', 'http://localhost/'],
            ['Topics', 'http://localhost/tags'],
            ['Cars', 'http://localhost/t/cars'],
            ['Ford', null],
        ], $this->trail('/t/ford'));
    }

    #[Test]
    public function the_tags_crumb_is_dropped_where_the_tags_page_is_home()
    {
        $this->setting('default_route', '/tags');

        $this->assertSame(['Home', 'Cars', 'Ford'], array_column($this->trail('/t/ford'), 0));
    }

    #[Test]
    public function a_discussion_gains_the_tags_crumb_when_the_forum_asks_for_it_everywhere()
    {
        $this->setting('ernestdefoe-waymark.tags_crumb', 'always');

        $this->assertSame([
            ['Home', 'http://localhost/'],
            ['Tags', 'http://localhost/tags'],
            ['Cars', 'http://localhost/t/cars'],
            ['Ford', 'http://localhost/t/ford'],
            ['Mustang restoration', null],
        ], $this->trail('/d/1-mustang-restoration'));
    }

    #[Test]
    public function home_pointed_at_the_tags_page_leads_there()
    {
        $this->setting('ernestdefoe-waymark.home_target', 'tags');

        $this->assertSame([
            ['Home', 'http://localhost/tags'],
            ['Cars', 'http://localhost/t/cars'],
            ['Ford', null],
        ], $this->trail('/t/ford'));
    }

    #[Test]
    public function the_full_profile_trail_names_the_posts_tab()
    {
        $this->setting('ernestdefoe-waymark.users_crumb', 'full');

        $this->assertSame([
            ['Home', 'http://localhost/'],
            ['normal', 'http://localhost/u/normal'],
            ['Posts', null],
        ], $this->trail('/u/normal'));
    }
}
