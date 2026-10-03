import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import PageStructure from 'flarum/forum/components/PageStructure';
import Trail, { homeHref } from './components/Trail';
import { builtIn, fallback } from './trails';

/** Trails registered by other extensions, by route name. They win over ours. */
const registered = new Map();

function isHome() {
  try {
    return m.route.get().split('?')[0].replace(/\/+$/, '') === '';
  } catch (e) {
    return false;
  }
}

/**
 * The crumbs for the page being shown, after Home — or null for none.
 *
 * 🚨 No trail on the home page, whatever page that is. A trail that says only
 * "Home" while you are at home tells you nothing, and FoF SEO publishes none
 * there either.
 */
function crumbsFor(name) {
  if (isHome() && !(app.search && app.search.state && app.search.state.params && app.search.state.params().q)) return null;

  try {
    if (registered.has(name)) return registered.get(name)(name);
    if (builtIn[name]) return builtIn[name](name);
    if (name && /^user(\.|$)/.test(name)) return builtIn.user(name);
    return fallback();
  } catch (e) {
    // A trail is never worth breaking the page it sits on — but say why.
    if (app.forum.attribute('debug')) console.warn('Waymark:', e);
    return null;
  }
}

function trailFor(crumbs) {
  const list = (crumbs || []).filter(Boolean);
  return list.length ? <Trail crumbs={list} /> : null;
}

/**
 * The API for other extensions, as `app.waymark`:
 *
 *   app.waymark.register('my.route', () => [{ label: 'Section', href: '/s' }, { label: 'Page' }]);
 *
 * gives a route its own trail (return null for none). A page that does not use
 * Flarum's PageStructure — Page Builder's pages, for one — draws it itself:
 *
 *   {app.waymark && app.waymark.render([{ label: 'Articles', href: '/c/articles' }, { label: title }])}
 *
 * `render` returns nothing on the home page, so a page can call it everywhere.
 */
const api = {
  register(name, resolver) {
    registered.set(name, resolver);
  },
  render(crumbs) {
    if (isHome()) return null;
    if (app.forum.attribute('waymarkOther') === false) return null;
    return trailFor(crumbs);
  },
  /** The crumbs Waymark would show for a route (after Home), or null. */
  crumbsFor,
  homeHref,
  Trail,
};

app.initializers.add('ernestdefoe-waymark', () => {
  app.waymark = api;

  /*
   * One hook for most of the forum: the discussion list and tag pages, search,
   * discussions, profiles and their tabs, /posts, /tags and messages all render
   * through PageStructure. Between the hero and the content by default (50 —
   * the hero is 100, the content 10); above the hero if the forum chose that.
   */
  extend(PageStructure.prototype, 'mainItems', function (items) {
    const name = app.current && app.current.get('routeName');
    const trail = trailFor(crumbsFor(name));
    if (!trail) return;

    items.add('waymark', trail, app.forum.attribute('waymarkPosition') === 'above' ? 150 : 50);
  });
});

export { api as waymark };
