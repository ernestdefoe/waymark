import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';
import { homeHref, homeIsDiscussions, homeIsTags, tagsLabel } from './components/Trail';

const t = (key, params) => extractText(app.translator.trans(`ernestdefoe-waymark.forum.${key}`, params));
const on = (attr) => app.forum.attribute(attr) !== false;

/**
 * The built-in trails, by route name.
 *
 * Each returns the crumbs AFTER Home, or null for "no trail on this page".
 * They agree with FoF SEO's breadcrumbs where both exist: a discussion follows
 * its primary tag lineage only, and gets no "Tags" crumb.
 */

/** A route that is the forum's home, where Flarum serves it at "/". */
export function isHomeRoute(name) {
  try {
    return app.route(name) === homeHref();
  } catch (e) {
    return false;
  }
}

/**
 * 🚨 A parent that was never loaded is `false`, not `null`.
 *
 * Model relationships answer `false` when the relation is absent from the
 * payload. Treating only null as "no parent" walks into a boolean.
 */
function parentOf(tag) {
  try {
    const p = tag && tag.parent && tag.parent();
    return p || null;
  } catch (e) {
    return null;
  }
}

function tagCrumb(tag) {
  return { label: tag.name(), href: app.route.tag(tag) };
}

/** The tags page crumb, unless the tags page IS the home page. */
function tagsCrumb() {
  // The tags page IS Home when it is the forum's root, or when Home was
  // pointed at it: either way, a second crumb for the same place is noise.
  if (!app.routes.tags || isHomeRoute('tags') || homeIsTags()) return null;
  return { label: tagsLabel(), href: app.route('tags') };
}

function lineage(tag) {
  const chain = [];
  let cur = tag;
  let guard = 0;
  while (cur && guard++ < 10) {
    chain.unshift(cur);
    cur = parentOf(cur);
  }
  return chain;
}

function searchQuery() {
  try {
    const params = app.search && app.search.state && app.search.state.params ? app.search.state.params() : {};
    return (params && params.q) || '';
  } catch (e) {
    return '';
  }
}

function searchCrumb(q) {
  return q ? { label: t('search', { query: q }) } : null;
}

/**
 * The primary tag lineage of a discussion: its child tag and that tag's
 * parent, or its primary tag alone. Secondary tags (no position, no parent)
 * describe a discussion; they are not where it lives.
 */
function primaryLineage(discussion) {
  let tags = [];
  try {
    tags = (discussion.tags() || []).filter(Boolean);
  } catch (e) {
    return [];
  }

  const child = tags.find((tag) => parentOf(tag));
  if (child) return lineage(child);

  const primary = tags.find((tag) => typeof tag.position === 'function' && tag.position() !== null);
  return primary ? [primary] : [];
}

export const builtIn = {
  index() {
    if (!on('waymarkOther')) return null;
    const q = searchQuery();

    // /all on a forum whose home it is: an alias of Home, not a page under it.
    if (isHomeRoute('index')) return q ? [searchCrumb(q)] : null;

    // 🚨 Home pointed at /all on a forum whose front page is something else
    // (a blog): /all is still a page of its own and keeps a trail. Treating
    // it as Home left the discussions list, the page those forums browse
    // from, with no trail at all. A search needs only Home before it, which
    // already leads here.
    if (homeIsDiscussions()) return [q ? searchCrumb(q) : { label: t('all_discussions') }];

    return [{ label: t('all_discussions'), href: q ? app.route('index') : undefined }, searchCrumb(q)];
  },

  tag() {
    if (!on('waymarkTags')) return null;
    const tag = app.currentTag && app.currentTag();
    if (!tag) return null; // Still loading.

    const q = searchQuery();
    const chain = lineage(tag).map(tagCrumb);
    if (!q) chain[chain.length - 1].href = undefined;

    return [tagsCrumb(), ...chain, searchCrumb(q)];
  },

  tags() {
    if (!on('waymarkTags')) return null;
    // Home already is the tags page: a trail of one crumb is no trail.
    if (homeIsTags()) return null;
    return [{ label: tagsLabel() }];
  },

  discussion() {
    if (!on('waymarkDiscussions')) return null;
    const discussion = app.current.get('discussion');
    if (!discussion) return null;

    return [...primaryLineage(discussion).map(tagCrumb), { label: discussion.title() }];
  },

  user(name) {
    if (!on('waymarkUsers')) return null;
    const user = app.current.get('user');
    if (!user) return null;

    const person = { label: user.displayName(), href: app.route.user(user) };
    const tab = name === 'user' || name === 'user.posts' ? null : userTab(name);

    return tab ? [person, { label: tab }] : [{ label: person.label }];
  },

  settings() {
    return on('waymarkOther') ? [{ label: t('settings') }] : null;
  },

  notifications() {
    return on('waymarkOther') ? [{ label: t('notifications') }] : null;
  },

  posts() {
    return on('waymarkOther') ? [{ label: t('posts') }] : null;
  },

  messages() {
    return on('waymarkMessages') ? [{ label: t('messages') }] : null;
  },

  dialog() {
    if (!on('waymarkMessages')) return null;
    const title = app.title && String(app.title).trim();
    const messages = t('messages');

    return title && title !== messages ? [{ label: messages, href: app.route('messages') }, { label: title }] : [{ label: messages }];
  },
};

builtIn['discussion.near'] = builtIn.discussion;
builtIn['dialog.message'] = builtIn.dialog;

/**
 * The name of a profile tab.
 *
 * Core's tabs have names Waymark knows; an extension's tab (badges, likes…) is
 * read from the profile's own navigation, so it says exactly what the tab says.
 */
function userTab(name) {
  const known = { 'user.discussions': 'discussions', 'user.security': 'security' };
  if (known[name]) return t(known[name]);

  const active = document.querySelector('.UserPage .sideNav li.active .Button-label, .UserPage .sideNav .active .Button-label');
  return active ? active.textContent.trim() : null;
}

/**
 * Any other page: its own title, if it has one that is not just the forum's.
 * An extension's page (a pick'em, a gallery) gets "Home › Pick'em" without
 * knowing Waymark exists.
 */
export function fallback() {
  if (!on('waymarkOther')) return null;

  const title = app.title && String(app.title).trim();
  if (!title || title === app.forum.attribute('title')) return null;

  return [{ label: title }];
}
