mSync.panel.Sync = function (config) {
    config = config || {};
    var msync_import = localStorage.getItem('msync_import') || 'import.xml';
    var msync_offers = localStorage.getItem('msync_offers') || 'offers.xml';
    var msync_export_limit = localStorage.getItem('msync_export_limit') || '500';

    Ext.applyIf(config, {
        id: 'msync_sync_form'
        , xtype: 'form'
        , layout: 'form'
        , cls: 'form-with-labels'
        , border: false
        , labelWidth: 180
        , buttonAlign: 'left'
        , items: [
            {
                xtype: 'hidden'
                , name: 'shop'
                , value: 'minishop'
                , id: 'modx-resource-parent-hidden-cmp' + this.ident_imp
            }
            , {
                html: _('msync_export_msg')
                , border: false
                , bodyCssClass: 'panel-desc'
                , bodyStyle: 'margin-bottom: 10px'
            }
            , {
                xtype: 'textfield',
                id: 'msync_export_limit',
                fieldLabel: _('msync_export_limit'),
                name: 'limit',
                value: msync_export_limit,
                maxLength: 255,
                allowBlank: false,
                width: 300

            }
            , {
                xtype: "button"
                , id: 'msync_button_export_catalog'
                , text: _('msync_sync_export_button')
                , style: {marginTop: '15px'}
                , handler: function (a) {
                    var limit = Ext.getCmp('msync_export_limit').getValue()-0;
                    if (!limit) {
                        limit = 500;
                    }
                    localStorage.setItem('msync_export_limit', limit);
                    Ext.getCmp('msync-panel-cmp').exportCatalog(0, 0, '', limit);
                }
                , scope: this
            }
            , {
                html: '<div class="export-result"></div>'
                , border: false
                , id: 'export-result'
                , bodyStyle: 'margin-top: 15px'
            }
            , {
                html: _('msync_import_msg')
                , border: false
                , bodyCssClass: 'panel-desc'
                , bodyStyle: 'margin-bottom: 15px'
            },
            {
                layout: 'column',
                cls: 'x-toolbar',
                bodyStyle: 'margin-bottom: 15px',
                style: {
                    backgroundColor: 'transparent'
                },
                defaults: {layout: 'form', border: false},
                items: [{
                    columnWidth: 0.5,
                    items: [{
                        xtype: 'textfield',
                        id: 'msync-import-filename',
                        fieldLabel: _('msync_import_filename'),
                        anchor: '100%',
                        border: false,
                        value: msync_import,
                        labelStyle: 'font-size: 13px; line-height:20px; text-align: center'
                    }]
                }, {
                    columnWidth: 0.5,
                    items: [{
                        xtype: 'textfield',
                        id: 'msync-offers-filename',
                        fieldLabel: _('msync_offers_filename'),
                        anchor: '100%',
                        border: false,
                        value: msync_offers,
                        labelStyle: 'font-size: 13px; line-height:20px; text-align: center'
                    }]
                }]
            } , {
                xtype: 'button',
                text: _('msync_1c_upload_files'),
                style: {marginRight: '15px'},
                handler: this.uploadFiles
                ,scope: this
            }, {
                xtype: 'button',
                text: _('msync_1c_manual_import'),
                style: {marginRight: '15px'},
                handler: this.manualImport,
                scope: this
            }
            , {
                xtype: 'button',
                text: _('msync_show_sales_xml'),
                handler: this.showSales,
                scope: this
            }
        ]
    });
    mSync.panel.Sync.superclass.constructor.call(this, config);
};
Ext.extend(mSync.panel.Sync, MODx.FormPanel, {
    manualImport: function () {
        Ext.getCmp('msync_sync_form').getEl().mask(_('msync_manual_import'), 'x-mask-loading');

        this.makeRequest({action: 'mgr/import/process', mode: "init"}, this.makeImportStep);
    },
    makeImportStep: function (step) {
        var that = this;
        var importFile = Ext.getCmp('msync-import-filename').getValue();
        var offersFile = Ext.getCmp('msync-offers-filename').getValue();
        if (importFile === '' && offersFile === '') return;
        if (step !== "import" && step !== "offers") {
            localStorage.setItem('msync_import', importFile);
            localStorage.setItem('msync_offers', offersFile);
        }
        step = (step === 'offers' || importFile === '') ? 'offers' : 'import';
        if (step === 'offers' && offersFile === '') {
            setTimeout(function() {
                that.getEl().unmask();
            }, 2000);
            return;
        }

        var filename = step === "offers" ? offersFile : importFile;
        this.makeRequest({
            action: 'mgr/import/process', mode: "import", filename: filename
        }, function (r) {
            that.getEl().unmask();
            that.getEl().mask(r['object']['result'], 'x-mask-loading');
            if (r['message'] === 'progress') {
                that.makeImportStep(step);
            } else if (r['message'] === 'success' && step === "import") {
                that.makeImportStep("offers");
            } else {
                setTimeout(function() {
                    that.getEl().unmask();
                }, 2000);
            }

        }, function (r) {
            console.log('fail', r);
            that.getEl().unmask();
        });
    },
    makeRequest: function (params, success, failure) {
        MODx.Ajax.request({
            url: mSync.config.connector_url
            , params: params
            , method: 'GET'
            , listeners: {
                success: {fn: success, scope: this},
                failure: {fn: failure, scope: this}
            }
        });
    }
    ,uploadFiles: function(btn,e) {
        if (!this.uploader) {
            this.uploader = new MODx.util.MultiUploadDialog.Dialog({
                url: mSync.config.connector_url
                ,base_params: {
                    action: 'mgr/import/upload'
                    ,path: MODx.config.assets_url + 'components/msync/1c_temp'
                    ,wctx: MODx.ctx || ''
                }
                ,cls: 'ext-ux-uploaddialog-dialog modx-upload-window'
            });
        }
        this.uploader.show(btn);
    }
    ,showSales: function() {
        var link = document.createElement("a");
        link.style.display = 'none';
        document.body.appendChild(link);
        if(link.download !== undefined) {
            link.setAttribute('href', mSync.config.sales_link);
            link.setAttribute('download', 'sales.xml');
            link.click();
            document.body.removeChild(link);
        }
        else {
            window.open(mSync.config.sales_link, '_blank');
        }


    }
});
Ext.reg('msync-sync-panel', mSync.panel.Sync);