/* ===== Sidebar panel (free version) ===== */
(function (wp) {
  if (!wp || !wp.plugins || !wp.editPost) return;

  const { __, sprintf } = wp.i18n;

  var el = wp.element.createElement;
  var registerPlugin = wp.plugins.registerPlugin;
  var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
  var Button = wp.components.Button;
  var Spinner = wp.components.Spinner;
  var Notice = wp.components.Notice;
  var useState = wp.element.useState;
  var select = wp.data.select;

  var BOOT = window.STB_BOOT || {};

  function convertOrRevert(action, setBusy, setNotice) {
    setBusy(true);
    var id = select('core/editor').getCurrentPostId();
    var form = new FormData();
    form.append('action', action === 'convert' ? 'stb_convert' : 'stb_revert');
    form.append('post_id', id);
    form.append('stb_convert_nonce_field', BOOT.nonce || '');

    fetch(BOOT.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success) {
          setNotice({
            status: 'success',
            msg: (res.data && res.data.message) || __('Done.', 'shortcode-to-blocks')
          });
          window.location.reload();
        } else {
          setNotice({
            status: 'error',
            msg: (res && res.data) || __('Request failed.', 'shortcode-to-blocks')
          });
        }
      })
      .catch(function () {
        setNotice({
          status: 'error',
          msg: __('Network error.', 'shortcode-to-blocks')
        });
      })
      .finally(function () { setBusy(false); });
  }

  function countVCShortcodes() {
    try {
      var content = select('core/editor').getEditedPostContent() || '';
      var match = content.match(/\[vc_[^\]]*\]/g);
      return match ? match.length : 0;
    } catch (e) {
      return null;
    }
  }

  var Panel = function () {
    var _busy = useState(false), busy = _busy[0], setBusy = _busy[1];
    var _notice = useState(null), notice = _notice[0], setNotice = _notice[1];

    var hasBackup = !!BOOT.hasBackup;
    var hasVC = !!BOOT.hasVC;
    var isPro = !!BOOT.isPro;
    var vcCount = countVCShortcodes();

    return el(
      PluginDocumentSettingPanel,
      {
        name: 'stb-panel',
        title: __('Shortcode → Blocks', 'shortcode-to-blocks'),
        className: 'stb-panel'
      },

      notice && el(
        Notice,
        {
          status: notice.status,
          isDismissible: true,
          onRemove: function () { setNotice(null); }
        },
        notice.msg
      ),

      el(
        'p',
        { className: 'components-help' },
        __('Convert WPBakery shortcodes to native Gutenberg blocks.', 'shortcode-to-blocks')
      ),

      el(
        'ul',
        { className: 'stb-summary', style: { margin: '0 0 12px', paddingLeft: '18px' } },
        el(
          'li',
          null,
          hasVC
            ? __('WPBakery content detected', 'shortcode-to-blocks')
            : __('No WPBakery shortcodes detected', 'shortcode-to-blocks')
        ),
        el(
          'li',
          null,
          hasBackup
            ? __('Backup available', 'shortcode-to-blocks')
            : __('No backup stored yet', 'shortcode-to-blocks')
        ),
        vcCount !== null && el(
          'li',
          null,
          sprintf(
            /* translators: %d: Number of WPBakery shortcodes detected in the content. */
            __('Shortcodes found: %d', 'shortcode-to-blocks'),
            vcCount
          )
        )
      ),

      el(
        'div',
        { className: 'stb-actions', style: { display: 'flex', gap: '8px', marginBottom: '4px' } },
        hasVC && el(
          Button,
          {
            variant: 'primary',
            onClick: function () { convertOrRevert('convert', setBusy, setNotice); },
            disabled: busy,
          },
          busy ? el(Spinner, null) : __('Convert to blocks', 'shortcode-to-blocks')
        ),

        hasBackup && el(
          Button,
          {
            variant: 'secondary',
            isDestructive: true,
            onClick: function () {
              if (window.confirm(__('Revert to original WPBakery content?', 'shortcode-to-blocks'))) {
                convertOrRevert('revert', setBusy, setNotice);
              }
            },
            disabled: busy,
          },
          busy ? el(Spinner, null) : __('Revert to backup', 'shortcode-to-blocks')
        )
      ),

      !isPro && el(
        'p',
        { className: 'components-help', style: { marginTop: 8, fontStyle: 'italic' } },
        el(
          'a',
          {
            href: 'https://shortcodetoblocks.com/',
            target: '_blank',
            rel: 'noopener'
          },
          __('Upgrade to Pro', 'shortcode-to-blocks')
        ),
        __(' for batch conversion, advanced shortcodes, logs & more.', 'shortcode-to-blocks')
      )
    );
  };

  registerPlugin('stb-panel', { render: Panel });
})(window.wp);

/* ===== Post status row ===== */
(function (wp) {
  if (!wp || !wp.editPost || !wp.plugins) return;

  const { __ } = wp.i18n;

  var el = wp.element.createElement;
  var registerPlugin = wp.plugins.registerPlugin;
  var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
  var Button = wp.components.Button;
  var Spinner = wp.components.Spinner;
  var useState = wp.element.useState;
  var select = wp.data.select;
  var BOOT = window.STB_BOOT || {};

  var StatusInfo = function () {
    var _use = useState(false), busy = _use[0], setBusy = _use[1];
    var hasVC = !!BOOT.hasVC;
    var hasBackup = !!BOOT.hasBackup;
    var mode = hasVC ? 'convert' : (hasBackup ? 'revert' : null);
    if (!mode) return null;

    function run(m) {
      setBusy(true);
      var id = select('core/editor').getCurrentPostId();
      var form = new FormData();
      form.append('action', m === 'convert' ? 'stb_convert' : 'stb_revert');
      form.append('post_id', id);
      form.append('stb_convert_nonce_field', BOOT.nonce || '');

      fetch(BOOT.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.success) {
            window.location.reload();
          } else if (wp.data && wp.data.dispatch) {
            wp.data.dispatch('core/notices').createNotice(
              'error',
              (res && res.data) ? String(res.data) : __('Action failed', 'shortcode-to-blocks'),
              { type: 'snackbar' }
            );
          }
        })
        .finally(function () { setBusy(false); });
    }

    return el(
      PluginPostStatusInfo,
      { className: 'stb-status' },
      el(
        'strong',
        null,
        hasVC
          ? __('WPBakery content detected', 'shortcode-to-blocks')
          : __('Backup available', 'shortcode-to-blocks')
      ),
      el(
        'div',
        { style: { marginTop: 6 } },
        el(
          Button,
          { variant: 'secondary', onClick: function () { run(mode); }, disabled: busy },
          busy
            ? el(Spinner, null)
            : (mode === 'convert'
              ? __('Convert now', 'shortcode-to-blocks')
              : __('Revert to backup', 'shortcode-to-blocks'))
        )
      )
    );
  };

  registerPlugin('stb-status', { render: StatusInfo });
})(window.wp);