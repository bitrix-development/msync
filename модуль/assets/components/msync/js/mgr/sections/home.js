Ext.onReady(function() {
	MODx.load({ xtype: 'msync-page-home'});
});

mSync.page.Home = function(config) {
	config = config || {};
	Ext.applyIf(config,{
		components: [{
			xtype: 'msync-panel-home'
			,renderTo: 'msync-panel-home-div'
		}]
	}); 
	mSync.page.Home.superclass.constructor.call(this,config);
};
Ext.extend(mSync.page.Home,MODx.Component);
Ext.reg('msync-page-home',mSync.page.Home);