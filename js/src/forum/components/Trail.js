import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';

const t = (key, params) => app.translator.trans(`ernestdefoe-waymark.forum.${key}`, params);

/**
 * The trail itself: Home › … › where you are.
 *
 *   <Trail crumbs={[{ label, href? }, …]} />
 *
 * `crumbs` excludes Home, which is always first. The last crumb is the page
 * being read: it is never a link and carries aria-current.
 *
 * 🚨 On a phone the middle collapses to "…", not the ends.
 *
 * A trail four or five crumbs deep wraps onto three lines at 375px, pushing
 * the page down for something nobody reads that closely on a phone. Home and
 * the last two crumbs — where you are and the step above it — are what a
 * visitor actually uses; the rest open on a tap.
 */
export default class Trail extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.expanded = false;
  }

  view() {
    const crumbs = [home(), ...(this.attrs.crumbs || [])].filter((c) => c && c.label);

    if (crumbs.length < 2) return null;

    const last = crumbs.length - 1;
    // Collapsible: everything between Home and the last two.
    const hidden = (i) => i > 0 && i < last - 1;
    const canCollapse = crumbs.some((c, i) => hidden(i));

    const mobile = app.forum.attribute('waymarkMobile') === 'hide' ? ' Waymark--hideOnPhone' : '';
    const style = app.forum.attribute('waymarkStyle') === 'tabs' ? ' Waymark--tabs' : '';

    return (
      <nav className={'Waymark' + style + mobile + (this.expanded ? ' is-expanded' : '')} aria-label={t('label')}>
        {/* 🚨 Page-container as well as container: themes resize the page's
            content through .Page-container (Bespoke drops core's fixed width
            for its own max-width), so a plain .container trail sat 40px off
            the content under it on every Bespoke forum. Sharing the class
            shares whatever the theme does to it. */}
        <div className="container Page-container Waymark-inner">
          <ol className="Waymark-list">{this.items(crumbs, last, hidden, canCollapse)}</ol>
        </div>
      </nav>
    );
  }
}

/**
 * 🚨 Every child keyed, none left null.
 *
 * Mithril refuses a list where some children have keys and others do not, and
 * a `null` slot for the "…" button on a short trail is a child without one. It
 * throws, Flarum swallows the error, and the trail is simply never drawn — on
 * every page, with nothing in the console. So the list is built, not mapped.
 */
Trail.prototype.items = function (crumbs, last, hidden, canCollapse) {
  const out = [];

  crumbs.forEach((crumb, i) => {
    if (i === 1 && canCollapse) {
      out.push(
        <li className="Waymark-more" key="more">
          <button
            type="button"
            className="Waymark-moreButton"
            aria-label={t('show_all')}
            title={t('show_all')}
            onclick={() => {
              this.expanded = true;
            }}
          >
            …
          </button>
        </li>
      );
    }

    out.push(
      <li key={'c' + i} className={'Waymark-crumb' + (hidden(i) ? ' Waymark-crumb--middle' : '')}>
        {i === last || !crumb.href ? (
          <span className="Waymark-current" aria-current={i === last ? 'page' : undefined}>
            {crumb.label}
          </span>
        ) : (
          <Link href={crumb.href} className="Waymark-link">
            {crumb.label}
          </Link>
        )}
      </li>
    );
  });

  return out;
};

/** The root crumb: "Home", or the forum's title if the forum chose that. */
export function home() {
  const label = app.forum.attribute('waymarkHomeLabel') === 'title' ? app.forum.attribute('title') : t('home');

  return { label, href: homeHref() };
}

/**
 * The forum's root.
 *
 * 🚨 Always "/", whatever the home page is. Flarum serves the configured home
 * (the tags page, a Page Builder page) at the root, so "/tags" on such a forum
 * is an alias of Home rather than a page of its own.
 */
export function homeHref() {
  return (app.forum.attribute('basePath') || '') + '/';
}
