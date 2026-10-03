import app from 'flarum/admin/app';

const t = (key) => app.translator.trans(`ernestdefoe-waymark.admin.${key}`);

app.initializers.add('ernestdefoe-waymark', () => {
  app.registry
    .for('ernestdefoe-waymark')
    .registerSetting({
      setting: 'ernestdefoe-waymark.position',
      type: 'select',
      label: t('position_label'),
      help: t('position_help'),
      options: { below: t('position_below'), above: t('position_above') },
      default: 'below',
    })
    .registerSetting({
      setting: 'ernestdefoe-waymark.style',
      type: 'select',
      label: t('style_label'),
      help: t('style_help'),
      options: { plain: t('style_plain'), tabs: t('style_tabs') },
      default: 'plain',
    })
    .registerSetting({
      setting: 'ernestdefoe-waymark.home_label',
      type: 'select',
      label: t('home_label'),
      help: t('home_help'),
      options: { home: t('home_home'), title: t('home_title') },
      default: 'home',
    })
    .registerSetting({
      setting: 'ernestdefoe-waymark.mobile',
      type: 'select',
      label: t('mobile_label'),
      help: t('mobile_help'),
      options: { collapse: t('mobile_collapse'), hide: t('mobile_hide') },
      default: 'collapse',
    })
    .registerSetting(() => <h3 className="WaymarkAdmin-heading">{t('pages_heading')}</h3>)
    .registerSetting({ setting: 'ernestdefoe-waymark.show_discussions', type: 'boolean', label: t('show_discussions') })
    .registerSetting({ setting: 'ernestdefoe-waymark.show_tags', type: 'boolean', label: t('show_tags') })
    .registerSetting({ setting: 'ernestdefoe-waymark.show_users', type: 'boolean', label: t('show_users') })
    .registerSetting({ setting: 'ernestdefoe-waymark.show_messages', type: 'boolean', label: t('show_messages') })
    .registerSetting({ setting: 'ernestdefoe-waymark.show_other', type: 'boolean', label: t('show_other'), help: t('show_other_help') });
});
