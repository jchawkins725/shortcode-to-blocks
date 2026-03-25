/* ===== Sidebar panel (free version) ===== */
(function (wp) {
  if (!wp || !wp.plugins || !wp.editPost) return;

  var el = wp.element.createElement;
  var registerPlugin = wp.plugins.registerPlugin;
  var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
  var Button = wp.components.Button;
  var Spinner = wp.components.Spinner;
  var Notice = wp.components.Notice;
  var ButtonGroup = wp.components.ButtonGroup;
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
          setNotice({ status: 'success', msg: (res.data && res.data.message) || 'Done.' });
          window.location.reload();
        } else {
          setNotice({ status: 'error', msg: (res && res.data) || 'Request failed.' });
        }
      })
      .catch(function () { setNotice({ status: 'error', msg: 'Network error.' }); })
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
      { name: 'stb-panel', title: 'Shortcode → Blocks', className: 'stb-panel' },

      notice && el(Notice, { status: notice.status, isDismissible: true, onRemove: function () { setNotice(null); } }, notice.msg),

      el('p', { className: 'components-help' }, 'Convert WPBakery shortcodes to native Gutenberg blocks.'),

      el('ul', { className: 'stb-summary', style: { margin: '0 0 12px', paddingLeft: '18px' } },
        el('li', null, hasVC ? 'WPBakery content detected' : 'No WPBakery shortcodes detected'),
        el('li', null, hasBackup ? 'Backup available' : 'No backup stored yet'),
        vcCount !== null && el('li', null, 'Shortcodes found: ' + vcCount)
      ),

      el('div', { className: 'stb-actions', style: { display: 'flex', gap: '8px', marginBottom: '4px' } },
        hasVC && el(Button, {
          variant: 'primary',
          onClick: function () { convertOrRevert('convert', setBusy, setNotice); },
          disabled: busy,
        }, busy ? el(Spinner, null) : 'Convert to blocks'),

        hasBackup && el(Button, {
          variant: 'secondary',
          isDestructive: true,
          onClick: function () {
            if (window.confirm('Revert to original WPBakery content?')) {
              convertOrRevert('revert', setBusy, setNotice);
            }
          },
          disabled: busy,
        }, busy ? el(Spinner, null) : 'Revert to backup')
      ),

      !isPro && el('p', { className: 'components-help', style: { marginTop: 8, fontStyle: 'italic' } },
        el('a', { href: 'https://www.jonathanchawkins.com/shortcode-to-blocks-pro/', target: '_blank', rel: 'noopener' }, 'Upgrade to Pro'),
        ' for batch conversion, advanced shortcodes, logs & more.'
      )
    );
  };

  registerPlugin('stb-panel', { render: Panel });
})(window.wp);

/* ===== Post status row ===== */
(function (wp) {
  if (!wp || !wp.editPost || !wp.plugins) return;

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
              'error', (res && res.data) ? String(res.data) : 'Action failed', { type: 'snackbar' }
            );
          }
        })
        .finally(function () { setBusy(false); });
    }

    return el(PluginPostStatusInfo, { className: 'stb-status' },
      el('strong', null, hasVC ? 'WPBakery content detected' : 'Backup available'),
      el('div', { style: { marginTop: 6 } },
        el(Button, { variant: 'secondary', onClick: function () { run(mode); }, disabled: busy },
          busy ? el(Spinner, null) : (mode === 'convert' ? 'Convert now' : 'Revert to backup')
        )
      )
    );
  };

  registerPlugin('stb-status', { render: StatusInfo });
})(window.wp);
