<?php
/**
 * The main manager controller for mSync.
 *
 * @package msync
 */
class mSyncIndexManagerController extends modExtraManagerController {
	/** @var mSync $mSync */
	public $mSync;

	public function initialize() {
        $corePath = $this->modx->getOption('msync.core_path', null, $this->modx->getOption('core_path') . 'components/msync/');
        $this->mSync = $this->modx->getService('msync', 'mSync', $corePath . 'model/msync/', array(
            'core_path' => $corePath
        ));
		parent::initialize();
	}

    public function getPageTitle()
    {
        return $this->modx->lexicon('msync');
    }

    public function getLanguageTopics()
    {
        return array('msync:default', 'msync:api', 'msync:category', 'msync:product', 'msync:export');
    }

    public function loadCustomCssJs()
    {
        $this->modx->regClientCSS($this->mSync->config['cssUrl'].'mgr/main.css');
        $this->modx->regClientStartupScript($this->mSync->config['jsUrl'].'mgr/msync.js');
        $this->modx->regClientStartupHTMLBlock('<script type="text/javascript">
		Ext.onReady(function() {
			mSync.config = '.$this->modx->toJSON($this->mSync->config).';
			mSync.config.connector_url = "'.$this->mSync->config['connectorUrl'].'";
            Ext.Ajax.timeout = '.@ini_get('max_execution_time').'000;
		});
		</script>');

        $login = $this->modx->getOption('msync_1c_sync_login', null, '');
        $pass = $this->modx->getOption('msync_1c_sync_pass', null, '');
        $link = $this->mSync->config['commercMlLink'] . "?http_auth=htauth:" . base64_encode($login . ":" . $pass) . "&type=sale&mode=query";

        $this->modx->regClientStartupHTMLBlock('
			<script type="text/javascript">
			// <![CDATA[
				Ext.onReady(function() {
					mSync.config.sales_link = "' . $link . '";
				});
			// ]]>
	        </script>');

        $this->modx->regClientStartupScript($this->modx->getOption('manager_url') . 'assets/modext/util/multiuploaddialog.js');
        $this->modx->regClientStartupScript($this->mSync->config['jsUrl'] . 'mgr/widgets/config.panel.js');
        $this->modx->regClientStartupScript($this->mSync->config['jsUrl'] . 'mgr/widgets/sync.panel.js');
        $this->modx->regClientStartupScript($this->mSync->config['jsUrl'] . 'mgr/widgets/property.grid.js');
        $this->modx->regClientStartupScript($this->mSync->config['jsUrl'] . 'mgr/widgets/home.panel.js');
        $this->modx->regClientStartupScript($this->mSync->config['jsUrl'] . 'mgr/sections/home.js');
    }


    public function getTemplateFile()
    {
        return $this->mSync->config['templatesPath'] . 'home.tpl';
    }
}
