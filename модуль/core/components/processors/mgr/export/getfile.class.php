<?php
class mSyncGetfilePrepareProcessor extends modObjectProcessor {

    private $name;
    public $languageTopics = array('msync:export');


    public function initialize() {
        $this->config = $this->modx->msync->config;
        $this->name = $this->getProperty('name');
        return true;
    }

    public function process() {
        if ($this->name=='') {
            return $this->failure($this->modx->lexicon('msync_export_err_no_name'));
        }


        $filename = $this->config['assetsPath'].'export/ms2_products_'.$this->name.'.csv';
        if(file_exists($filename)){
            $fileurl = $this->modx->getOption('site_url').'assets/components/msync/export/ms2_products_'.$this->name.'.csv';
        }

        return $this->success(array('file'=> $fileurl));
    }


}

return 'mSyncGetfilePrepareProcessor';