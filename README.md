# Waymark

Breadcrumbs for Flarum 2. A single line at the top of every page says where you
are and how you got there, the way traditional forums always have:

`Home › SEC › Alabama › East Carolina at Alabama`

Every crumb but the last is a link, so going back up a level is one click.

![A discussion with its trail: Home › SEC › Alabama › East Carolina at Alabama](screenshots/discussion.png)

## Where it shows

| Page | Trail |
|---|---|
| A tag | Home › Tags › SEC |
| A child tag | Home › Tags › SEC › Alabama |
| A discussion | Home › SEC › Alabama › *the discussion* |
| The tags page | Home › Tags |
| Searching inside a tag | Home › Tags › SEC › Search: “bama” |
| A profile | Home › *name* |
| A profile tab | Home › *name* › Discussions |
| Messages | Home › Messages › *the conversation* |
| Settings, notifications | Home › Settings |
| Any other page | Home › *the page's title*, e.g. Home › Pick'em |

The home page itself has no trail. **Home always links to your forum's home
page, whatever that is.** If your home is the tags page, the "Tags" crumb is
left out, so a tag reads Home › SEC › Alabama.

A discussion follows its **primary** tags: its child tag and that tag's parent.
Secondary tags describe a discussion; they aren't where it lives.

![A child tag's page: Home › Tags › SEC › Alabama](screenshots/tag.png)

<img src="screenshots/phone.png" alt="On a phone: Home › … › Alabama › East Carolina at Alabama" width="390">

On a phone a long trail shortens to Home › … › Alabama › *the discussion*, and
tapping … shows the rest.

## Settings

Admin → Waymark:

- **Position:** below the page header (hero) or above it.
- **First crumb:** "Home", or your forum's title.
- **On phones:** shorten the trail, or hide it.
- **Show a trail on:** discussions, tags, profiles, messages and every other
  page, each switched separately.

It uses your theme's own text and link colours, so there's nothing to style.

![Waymark's settings](screenshots/settings.png)

## With FoF SEO

[FoF SEO](https://github.com/FriendsOfFlarum/seo) already tells search engines
a breadcrumb trail for each page. When it's installed, Waymark keeps the two in
step, so search results and your pages say the same thing:

- the first crumb uses the same word ("Home", or your forum's title)
- "Tags" is translated, and left out when the tags page is your home page
- a profile's Discussions tab gets its crumb

Waymark doesn't need FoF SEO. Without it, there's simply nothing to keep in step.

## For extension developers

Give your route its own trail. Return the crumbs after Home, the last one being
the page itself, or `null` for no trail:

```js
app.waymark?.register('my-extension.page', () => [
  { label: 'Section', href: app.route('my-extension.section') },
  { label: 'This page' },
]);
```

Pages that use Flarum's `PageStructure` get the trail placed for them. A page
that renders its own layout can draw it itself:

```js
{app.waymark?.render([{ label: 'Articles', href: '/c/articles' }, { label: title }])}
```

`render` returns nothing on the home page, so it's safe to call everywhere.
[Page Builder](https://github.com/ernestdefoe/page-builder) does this for its
pages, and swaps its "← Back" link for the trail when Waymark is installed.

## Installation

```bash
composer require ernestdefoe/waymark
php flarum cache:clear
```

Then enable **Waymark** in the admin panel.

## Updating

```bash
composer update ernestdefoe/waymark
php flarum cache:clear
```

## Licence

MIT.
