<?php

class propertyUpdateProcessor extends modObjectUpdateProcessor {
	public $classKey = 'mSyncProductProperty';
	public $languageTopics = array('msync');
	public $permission = 'edit_document';

	public function beforeSet() {
		if ($this->modx->getObject('mSyncProductProperty',array('source' => $this->getProperty('source'), 'id:!=' => $this->getProperty('id') ))) {
			$this->modx->error->addField('source', $this->modx->lexicon('msync_err_ae'));
		}
		return parent::beforeSet();
	}

}

return 'propertyUpdateProcessor';