var mSync = function(config) {
	config = config || {};
	mSync.superclass.constructor.call(this,config);
};
Ext.extend(mSync,Ext.Component,{
	page:{},window:{},grid:{},tree:{},panel:{},combo:{},config: {},view: {}
});
Ext.reg('msync',mSync);

mSync = new mSync();