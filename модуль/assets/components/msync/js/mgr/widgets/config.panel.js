mSync.panel.Conf = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        id: 'msync-panel-config'
        , xtype: 'form'
        , layout: 'form'
        , cls: 'form-with-labels'
        , url: mSync.config.connector_url
        , baseParams: {
            action: 'mgr/config/save',
            id: config.user
        }
        , border: false
        , labelAlign: 'top'
        , defaults: {
            border: false,
            msgTarget: 'under'
        }
        , items: [
            {
                xtype: 'textfield',
                fieldLabel: _('msync_1c_link'),
                name: '1c_link',
                value: mSync.config.commercMlLink,
                readOnly: true,
                width: 600
            }, {
                xtype: 'textfield',
                fieldLabel: _('msync_1c_login'),
                name: 'username',
                value: MODx.config['msync_1c_sync_login'],
                maxLength: 255,
                readOnly: true,
                width: 300
            }, {
                xtype: 'textfield',
                fieldLabel: _('msync_1c_pass'),
                name: 'password',
                value: MODx.config['msync_1c_sync_pass'],
                maxLength: 255,
                readOnly: true,
                width: 300
            }, {
                html: '<p style="margin-top:10px">' + _('setting_msync_last_orders_sync') + ': ' +
                    MODx.config['msync_last_orders_sync'] + '</p>'
            }, {
                xtype: 'button',
                text: _('msync_clear_log'),
                style: {marginRight: '15px', marginTop: '15px'},
                handler: this.clearLog
                ,scope: this
            }]
    });
    mSync.panel.Conf.superclass.constructor.call(this, config);
};
Ext.extend(mSync.panel.Conf, MODx.FormPanel, {
    clearLog: function() {
        var that = this;
        Ext.getCmp('msync-panel-config').getEl().mask(_('msync_logs_clearing'), 'x-mask-loading');
        MODx.Ajax.request({
            url: mSync.config.connector_url
            , params: {
                action: 'mgr/import/clearlog'
            }
            , method: 'GET'
            , listeners: {
                success: {fn: function () {
                        that.getEl().unmask();
                    }, scope: this},
                failure: {fn: function (r) {
                        console.log('fail', r);
                        that.getEl().unmask();
                    }, scope: this}
            }
        });
    }
});
Ext.reg('msync-config-panel', mSync.panel.Conf);