mSync.panel.Home = function (config) {
    config = config || {};
    var msync_active_tab = localStorage.getItem('msync_active_tab');
    Ext.apply(config, {
        id: 'msync-panel-cmp'
        , border: false
        , baseCls: 'modx-formpanel'
        , items: [{
            html: '<h2>' + _('msync') + '</h2><p>' + _('msync_menu_desc') + '</p>'
            , border: false
            , cls: 'modx-page-header container'
        }, {
            xtype: 'modx-tabs'
            , bodyStyle: 'padding: 10px'
            , defaults: {border: false, autoHeight: true}
            , border: true
            , activeItem: 0
            ,listeners: {
                 'afterrender': {fn: function(tabs){
                         if (msync_active_tab) {
                             tabs.setActiveTab(msync_active_tab);
                         }
                     }, scope:this},
                'tabchange': {fn:function(tabs, activeTab) {
                        var tabId = activeTab.getId();
                        localStorage.setItem('msync_active_tab', tabId);
                    },scope:this}
            }
            , hideMode: 'offsets'
            , items: [
                {
                    id: 'msync_1c_properties_tab'
                    , title: _('msync_1c_properties')
                    , defaults: {autoHeight: true}
                    , items: [{
                    html: '<p>' + _('msync_1c_properties_intro') + '</p>'
                    , border: false
                    , bodyCssClass: 'panel-desc'
                    , bodyStyle: 'margin-bottom: 10px'
                }, {
                    xtype: 'msync-grid-property'
                    , preventRender: true
                }]
                }
                , {
                    id: 'msync_1c_config_tab'
                    , title: _('msync_1c_config')
                    , defaults: {autoHeight: true}
                    , items: [{
                        xtype: 'msync-config-panel'
                        , preventRender: true
                    }]
                }
                , {
                    id: 'msync_sync_catalog_tab'
                    , title: _('msync_sync_catalog')
                    , defaults: {autoHeight: true}
                    , items: [{
                        xtype: 'msync-sync-panel'
                        , preventRender: true
                    }]
                }
            ]
        }
            , {
                html: _('msync_copyright')
                , border: false
                , bodyStyle: 'background-color: transparent !important; margin:10px; text-align:right;'
            }]
    });
    mSync.panel.Home.superclass.constructor.call(this, config);
};
Ext.extend(mSync.panel.Home, MODx.Panel, {
    exportCatalog: function (start, total, name, limit) {
        if (typeof(start) == 'undefined') var start = 0;
        if (typeof(total) == 'undefined') var total = 0;
        if (typeof(name) == 'undefined') var name = '';
        if (typeof(limit) == 'undefined') var limit = 500;

        var sync_form = Ext.getCmp('msync_sync_form');

        sync_form.getEl().mask(_('msync_export_prepare') + (total > 0 ? ' (' + start + ' ' + _('msync_export_prepare_from') + ' ' + total + ')' : ''), 'x-mask-loading');

        MODx.Ajax.request({
            url: mSync.config.connector_url
            , params: {
                action: 'mgr/export/prepare', start: start, total: total, name: name, limit: limit
            }
            , method: 'POST'
            , listeners: {
                success: {
                    fn: function (r) {
                        sync_form.getEl().unmask();
                        if (r.message.stop == 0) {
                            start += limit;
                            Ext.getCmp('msync-panel-cmp').exportCatalog(start, r.message.total, r.message.name, limit);
                        } else {
                            sync_form.getEl().mask(_('msync_export_prepare_ok'), 'x-mask-loading');
                            Ext.getCmp('msync-panel-cmp').getExportFile(r.message.name);
                        }
                    }, scope: this
                }
                , failure: {
                    fn: function (r) {
                        sync_form.getEl().unmask();
                    }, scope: this
                }
            }
        });
    }
    , getExportFile: function (name) {
        var sync_form = Ext.getCmp('msync_sync_form');

        sync_form.getEl().mask(_('msync_export_file'), 'x-mask-loading');

        var resultPanel = Ext.getCmp('export-result');
        resultPanel.body.update('');

        MODx.Ajax.request({
            url: mSync.config.connector_url
            , params: {
                action: 'mgr/export/getfile', name: name
            }
            , method: 'POST'
            , listeners: {
                success: {
                    fn: function (r) {
                        sync_form.getEl().unmask();
                        resultPanel.body.update(_('msync_export_download') + ' <a href="' + r.message.file + '" target="_blank">' + r.message.file + '</a>');
                    }, scope: this
                }
                , failure: {
                    fn: function (r) {
                        sync_form.getEl().unmask();
                    }, scope: this
                }
            }
        });
    }
});
Ext.reg('msync-panel-home', mSync.panel.Home);
