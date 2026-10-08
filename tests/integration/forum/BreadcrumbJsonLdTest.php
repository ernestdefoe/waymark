<?php

namespace Ernestdefoe\Waymark\Tests\integration\forum;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Locale\LocaleManager;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The trail as search engines read it, when FoF SEO is not there to publish
 * one: a schema.org BreadcrumbList in the page head.
 */
class BreadcrumbJsonLdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-waymark');

        $this->setting('forum_title', 'Garage Talk');

        $this->prepareDatabase([
            Tag::class => [
                ['id' => 1, 'name' => 'Cars', 'slug' => 'cars', 'position' => 0, 'parent_id' => null],
                ['id' => 2, 'name' => 'Ford', 'slug' => 'ford', 'position' => 0, 'parent_id' => 1],
                ['id' => 3, 'name' => 'Classic', 'slug' => 'classic', 'position' => null, 'parent_id' => null],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Mustang restoration', 'slug' => 'mustang-restoration', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
                ['id' => 2, 'title' => 'Barn finds', 'slug' => 'barn-finds', 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()],
                ['id' => 3, 'title' => 'Ends here </script><script>alert(1)</script>', 'slug' => 'ends-here', 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>One</p></t>', 'created_at' => Carbon::now()],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Two</p></t>', 'created_at' => Carbon::now()],
                ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Three</p></t>', 'created_at' => Carbon::now()],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 1, 'tag_id' => 2],
                ['discussion_id' => 1, 'tag_id' => 3],
                ['discussion_id' => 2, 'tag_id' => 3],
            ],
        ]);
    }

    private function html(string $path): string
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

        return (string) $response->getBody();
    }

    /** @return list<array{0: string, 1: string}>|null name and URL of each crumb, or null when there is no list */
    private function trail(string $html): ?array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);

        $lists = array_values(array_filter(
            array_map(fn ($json) => json_decode($json, true), $blocks[1]),
            fn ($data) => ($data['@type'] ?? null) === 'BreadcrumbList'
        ));

        $this->assertLessThanOrEqual(1, count($lists), 'One breadcrumb list per page, never two');

        if (! $lists) {
            return null;
        }

        $items = $lists[0]['itemListElement'];
        $this->assertSame(range(1, count($items)), array_column($items, 'position'));

        return array_map(fn ($item) => [$item['name'], $item['item']], $items);
    }

    #[Test]
    public function a_discussion_is_traced_through_its_child_tag_and_parent()
    {
        $this->assertSame([
            ['Home', 'http://localhost/'],
            ['Cars', 'http://localhost/t/cars'],
            ['Ford', 'http://localhost/t/ford'],
            ['Mustang restoration', 'http://localhost/d/1-mustang-restoration'],
        ], $this->trail($this->html('/d/1-mustang-restoration')));
    }

    #[Test]
    public function a_secondary_tag_is_not_where_a_discussion_lives()
    {
        $this->assertSame([
            ['Home', 'http://localhost/'],
            ['Barn finds', 'http://localhost/d/2-barn-finds'],
        ], $this->trail($this->html('/d/2-barn-finds')));
    }

    #[Test]
    public function a_tag_page_is_traced_through_the_tags_page()
    {
        $this->assertSame([
            ['Home', 'http://localhost/'],
            ['Tags', 'http://localhost/tags'],
            ['Cars', 'http://localhost/t/cars'],
            ['Ford', 'http://localhost/t/ford'],
        ], $this->trail($this->html('/t/ford')));
    }

    #[Test]
    public function the_tags_crumb_is_left_out_where_the_tags_page_is_home()
    {
        $this->setting('default_route', '/tags');

        $this->assertSame(
            ['Home', 'Cars', 'Ford'],
            array_column($this->trail($this->html('/t/ford')), 0)
        );
    }

    #[Test]
    public function the_forum_chooses_the_words()
    {
        $this->setting('ernestdefoe-waymark.home_label', 'title');
        $this->setting('ernestdefoe-waymark.tags_text', 'Topics');
        $this->setting('ernestdefoe-waymark.tags_crumb', 'always');

        $this->assertSame(
            ['Garage Talk', 'Topics', 'Cars', 'Ford', 'Mustang restoration'],
            array_column($this->trail($this->html('/d/1-mustang-restoration')), 0)
        );
    }

    #[Test]
    public function a_custom_name_for_home_beats_the_forum_title()
    {
        $this->setting('ernestdefoe-waymark.home_label', 'title');
        $this->setting('ernestdefoe-waymark.home_text', 'Forum');

        $this->assertSame('Forum', $this->trail($this->html('/t/cars'))[0][0]);
    }

    #[Test]
    public function home_can_lead_to_the_tags_page()
    {
        $this->setting('ernestdefoe-waymark.home_target', 'tags');

        $this->assertSame([
            ['Home', 'http://localhost/tags'],
            ['Cars', 'http://localhost/t/cars'],
        ], $this->trail($this->html('/t/cars')), 'Home is the tags page, so there is no separate Tags crumb');
    }

    #[Test]
    public function a_title_cannot_close_the_script_block()
    {
        $html = $this->html('/d/3-ends-here');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame('Ends here </script><script>alert(1)</script>', $this->trail($html)[1][0]);
    }

    #[Test]
    public function no_discussion_trail_where_the_forum_switched_it_off()
    {
        $this->setting('ernestdefoe-waymark.show_discussions', '0');

        $this->assertNull($this->trail($this->html('/d/1-mustang-restoration')));
    }

    #[Test]
    public function no_tag_trail_where_the_forum_switched_it_off()
    {
        $this->setting('ernestdefoe-waymark.show_tags', '0');

        $this->assertNull($this->trail($this->html('/t/ford')));
    }

    #[Test]
    public function the_tags_page_has_no_trail()
    {
        $this->assertNull($this->trail($this->html('/tags')));
    }
}
