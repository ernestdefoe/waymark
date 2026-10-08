<?php

namespace Ernestdefoe\Waymark\Tests\integration\api;

use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ForumAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-waymark');
    }

    private function forum(): array
    {
        $response = $this->send($this->request('GET', '/api'));

        $this->assertSame(200, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true)['data']['attributes'];
    }

    #[Test]
    public function a_fresh_forum_gets_the_defaults()
    {
        $forum = $this->forum();

        $this->assertSame('below', $forum['waymarkPosition']);
        $this->assertSame('home', $forum['waymarkHomeLabel']);
        $this->assertSame('collapse', $forum['waymarkMobile']);
        $this->assertSame('plain', $forum['waymarkStyle']);
        $this->assertSame('home', $forum['waymarkHomeTarget']);
        $this->assertSame('', $forum['waymarkHomeText']);
        $this->assertSame('', $forum['waymarkTagsText']);
        $this->assertSame('tags', $forum['waymarkTagsCrumb']);
        $this->assertSame('short', $forum['waymarkUsersCrumb']);

        foreach (['waymarkDiscussions', 'waymarkTags', 'waymarkUsers', 'waymarkMessages', 'waymarkOther'] as $key) {
            $this->assertTrue($forum[$key], $key);
        }
    }

    #[Test]
    public function saved_settings_reach_the_forum()
    {
        $this->setting('ernestdefoe-waymark.home_text', 'Forum');
        $this->setting('ernestdefoe-waymark.tags_crumb', 'always');
        $this->setting('ernestdefoe-waymark.users_crumb', 'full');
        $this->setting('ernestdefoe-waymark.show_users', '0');

        $forum = $this->forum();

        $this->assertSame('Forum', $forum['waymarkHomeText']);
        $this->assertSame('always', $forum['waymarkTagsCrumb']);
        $this->assertSame('full', $forum['waymarkUsersCrumb']);
        $this->assertFalse($forum['waymarkUsers'], 'A switch stored as "0" is off, not a truthy string');
    }

    #[Test]
    public function an_unknown_choice_falls_back_to_the_default()
    {
        $this->setting('ernestdefoe-waymark.tags_crumb', 'sometimes');
        $this->setting('ernestdefoe-waymark.users_crumb', 'everything');

        $forum = $this->forum();

        $this->assertSame('tags', $forum['waymarkTagsCrumb']);
        $this->assertSame('short', $forum['waymarkUsersCrumb']);
    }
}
