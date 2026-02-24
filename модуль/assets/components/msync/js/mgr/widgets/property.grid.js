mSync.grid.Property = function(config) {
    config = config || {};

    Ext.applyIf(config,{
        id: 'msync-grid-property'
        ,url: mSync.config.connector_url
        ,baseParams: {
            action: 'mgr/property/getlist'
        }
        ,fields: ['id','source','type','target','active','default','is_multiple','is_primary']
        ,autoHeight: true
        ,paging: true
        ,remoteSort: true
        ,autosave: true
        ,columns: [
            {header: _('msync_id'),dataIndex: 'id',width: 50, sortable: true}
            ,{header: _('msync_source'),dataIndex: 'source',width: 150, sortable: true}
            ,{header: _('msync_type'),dataIndex: 'type',width: 100, renderer: this.renderType, sortable: true}
            ,{header: _('msync_target'),dataIndex: 'target',width: 150, sortable: true}
            ,{header: _('msync_is_multiple'),dataIndex: 'is_multiple',width: 100, renderer: this.renderBool, sortable: true}
            ,{header: _('msync_is_primary'),dataIndex: 'is_primary',width: 100, renderer: this.renderBool, sortable: true}
            ,{header: _('msync_active'),dataIndex: 'active',width: 100, renderer: this.renderBool, sortable: true}
        ]
        ,tbar: [{
            text: _('msync_btn_create')
            ,handler: this.createProperty
            ,scope: this
        }]
    });
    mSync.grid.Property.superclass.constructor.call(this,config);
};

Ext.extend(mSync.grid.Property,MODx.grid.Grid,{
    windows: {}
    ,getMenu: function(grid,index) {
        var m = [];
        m.push({
            text: _('msync_menu_update')
            ,handler: this.updateProperty
        });
        var record = grid.store.getAt(index);
        if(record.data.default!=1){
            m.push('-');
            m.push({
                text: _('msync_menu_remove')
                ,handler: this.removeProperty
            });
        }
         this.addContextMenuItem(m);
    }
    ,renderType: function(value) {
        return value===1 ? _('msync_type_db') : _('msync_type_tv');
    }
    ,renderBool: function(value, a, b, c) {
        return value ? _('msync_active_yes') : _('msync_active_no');
    }
    ,createProperty: function(btn,e) {
        if (!this.windows.createProperty) {
            this.windows.createProperty = MODx.load({
                xtype: 'msync-window-property-create'
                ,fields: this.getPropertyFields({})
                ,listeners: {
                    success: {fn:function() { this.refresh(); },scope:this}
                }
            });
        }
        this.windows.createProperty.fp.getForm().reset();
        this.windows.createProperty.show(e.target);
//        Ext.getCmp('msync-property-type_desc-create').getEl().dom.innerText = '';
    }

    ,updateProperty: function(btn,e) {
        if (!this.menu.record || !this.menu.record.id) return false;
        var r = this.menu.record;

        this.windows.updateProperty = MODx.load({
            xtype: 'msync-window-property-update'
            ,record: r
            ,fields: this.getPropertyFields(r)
            ,listeners: {
                 success: {fn:function() { this.refresh(); },scope:this}
            }
        });

        this.windows.updateProperty.fp.getForm().reset();
        this.windows.updateProperty.fp.getForm().setValues(r);
        this.windows.updateProperty.show(e.target);
//        Ext.getCmp('msync-property-type_desc-update').getEl().dom.innerText = r.type ? _('ms2_link_'+r.type+'_desc') : '';
    }

    ,removeProperty: function(btn,e) {
        if (!this.menu.record) return false;

        MODx.msg.confirm({
            title: _('msync_menu_remove') + '"' + this.menu.record.source + '"?'
            ,text: _('msync_menu_remove_confirm')
            ,url: this.config.url
            ,params: {
                action: 'mgr/property/remove'
                ,id: this.menu.record.id
            }
            ,listeners: {
                success: {fn:function(r) {this.refresh();}, scope:this}
            }
        });
    }
    ,getPropertyFields: function(property) {
        return [
            {xtype: 'hidden',name: 'id'}
            ,{xtype: 'hidden',name: 'default'}
            ,{xtype: 'textfield',fieldLabel: _('msync_source'), name: 'source', allowBlank: false, anchor: '99%',  disabled: !!property.default,
                style: { marginTop: '10px'}, labelStyle: 'margin-top:10px'}
            ,{xtype: 'msync-combo-property-type',fieldLabel: _('msync_type'), name: 'type', allowBlank: false, anchor: '99%',
            listeners: {
                'select': function(combo, record) {
                    var isTv = record.id == '2';
                    var form = combo.ownerCt.getForm();
                    var primaryField = form.findField('is_primary');
                    var multipleField = form.findField('is_multiple');
                    primaryField.setDisabled(isTv);
                    multipleField.setDisabled(isTv);
                    if (isTv) {
                        primaryField.setValue(false);
                        multipleField.setValue(false);
                    }
                }
            }}
            ,{xtype: 'textfield',fieldLabel: _('msync_target'), name: 'target', allowBlank: false, anchor: '99%'},
            {
                layout: 'column',
                defaults: {
                    columnWidth: 0.33
                },
                items: [
                    {xtype: 'xcheckbox',boxLabel: _('msync_is_multiple'), name: 'is_multiple', inputValue: 0, checked: 0, disabled: property.type == '2'}
                    ,{xtype: 'xcheckbox',boxLabel: _('msync_is_primary'), name: 'is_primary', inputValue: 0, checked: 0, disabled: property.type == '2'}
                    ,{xtype: 'xcheckbox',boxLabel: _('msync_active'), name: 'active', inputValue: 1, checked: 1}
                ]}
            ,
        ];
    }
});

Ext.reg('msync-grid-property',mSync.grid.Property);



mSync.window.createProperty = function(config) {
    config = config || {};

    this.ident = 'meuitem-create-'+Ext.id()+'-window';
    Ext.applyIf(config,{
        title: _('msync_menu_create')
        ,id: this.ident
        ,width: 600
        ,labelAlign: 'left'
        ,labelWidth: 300
        ,url: mSync.config.connector_url
        ,action: 'mgr/property/create'
        ,fields: config.fields
        ,keys: [{key: Ext.EventObject.ENTER,shift: true,fn: function() {this.submit() },scope: this}]
    });
    mSync.window.createProperty.superclass.constructor.call(this,config);
};
Ext.extend(mSync.window.createProperty,MODx.Window);
Ext.reg('msync-window-property-create',mSync.window.createProperty);

mSync.window.updateProperty = function(config) {
    config = config || {};

    this.ident = 'meuitem-update-'+Ext.id()+'-window';
    Ext.applyIf(config,{
        title: _('msync_menu_update')
        ,id: this.ident
        ,width: 600
        ,labelAlign: 'left'
        ,labelWidth: 300
        ,url: mSync.config.connector_url
        ,action: 'mgr/property/update'
        ,fields: config.fields
        ,keys: [{key: Ext.EventObject.ENTER,shift: true,fn: function() {this.submit() },scope: this}]
    });
    mSync.window.updateProperty .superclass.constructor.call(this,config);
};
Ext.extend(mSync.window.updateProperty ,MODx.Window);
Ext.reg('msync-window-property-update',mSync.window.updateProperty);

mSync.combo.PropertyType = function(config) {
    config = config || {};
    Ext.applyIf(config,{
        store: new Ext.data.ArrayStore({
            id: 0
            ,fields: ['type','display']
            ,data: [
                ['1',_('msync_type_db')]
                ,['2',_('msync_type_tv')]
            ]
        })
        ,mode: 'local'
        ,displayField: 'display'
        ,valueField: 'type'
        ,hiddenName: 'type'

    });
    mSync.combo.PropertyType.superclass.constructor.call(this,config);
};
Ext.extend(mSync.combo.PropertyType,MODx.combo.ComboBox);
Ext.reg('msync-combo-property-type',mSync.combo.PropertyType);