import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';
import { isHomeRoute } from './trails';

/**
 * Trails for linkrobins/wiki, which knows nothing of Waymark.
 *
 *   /wiki                 Home › Wiki
 *   /wiki?category=3      Home › Wiki › Guides
 *   /wiki/some-article    Home › Wiki › Guides › Some article
 *
 * Its categories are flat (no parents), and an article with none reads
 * Home › Wiki › Some article. Everything comes from the store: the article
 * page's own preload or fetch includes its category, so no request is added.
 * The routes only exist while the wiki is enabled, so this costs nothing
 * anywhere else.
 */

const wikiT = (key, fallback) => {
  const out = extractText(app.translator.trans(`linkrobins-wiki.forum.${key}`));
  return out && out.indexOf('linkrobins-wiki.') !== 0 ? out : fallback;
};

const on = () => app.forum.attribute('waymarkOther') !== false;

/** "Wiki", in the wiki's own words. Left out when the wiki is the forum's home. */
function wikiCrumb(link) {
  if (isHomeRoute('linkrobins-wiki.index')) return null;
  return { label: wikiT('nav', 'Wiki'), href: link ? app.route('linkrobins-wiki.index') : undefined };
}

function categoryCrumb(category, link) {
  return {
    label: category.name(),
    href: link ? app.route('linkrobins-wiki.index', { category: category.id() }) : undefined,
  };
}

/**
 * The article on screen, by the URL's last segment (its slug or id).
 *
 * 🚨 The location, not m.route.param: the wiki swaps an old slug or an id URL
 * for the current slug with history.replaceState, which Mithril's params never
 * see.
 */
function currentArticle() {
  const segment = decodeURIComponent(window.location.pathname.split('/').filter(Boolean).pop() || '');
  return app.store.all('linkrobins-wiki-articles').find((a) => a.slug() === segment || a.id() === segment) || null;
}

export const wiki = {
  'linkrobins-wiki.index'() {
    if (!on()) return null;
    const id = m.route.param('category');
    const q = (m.route.param('q') || '').toString().trim();

    if (q) return [wikiCrumb(true), { label: extractText(app.translator.trans('ernestdefoe-waymark.forum.search', { query: q })) }];
    if (!id) return [wikiCrumb(false)];
    if (id === 'none') return [wikiCrumb(true), { label: wikiT('index.uncategorised', 'Other') }];

    const category = app.store.all('linkrobins-wiki-categories').find((c) => c.id() === String(id) || c.slug() === String(id));
    return [wikiCrumb(true), category ? categoryCrumb(category, false) : null];
  },

  'linkrobins-wiki.show'() {
    if (!on()) return null;
    const article = currentArticle();
    if (!article) return null; // Still loading.

    const category = article.category();
    return [wikiCrumb(true), category ? categoryCrumb(category, true) : null, { label: article.title() }];
  },
};

/** Writing or editing: Home › Wiki › the page's own title ("New article", "Edit article"). */
wiki['linkrobins-wiki.compose'] = wiki['linkrobins-wiki.edit'] = () => {
  if (!on()) return null;
  const title = app.title && String(app.title).trim();
  return [wikiCrumb(true), title ? { label: title } : null];
};
