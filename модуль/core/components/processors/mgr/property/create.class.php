<?php
class propertyCreateProcessor extends modObjectCreateProcessor {
	public $classKey = 'mSyncProductProperty';
	public $languageTopics = array('msync:properties');
	public $permission = 'new_document';

	public function beforeSet() {
		if ($this->modx->getObject('mSyncProductProperty',array('source' => $this->getProperty('source')))) {
			$this->modx->error->addField('source', $this->modx->lexicon('msync_prop_source_err_ae'));
		}
		return !$this->hasErrors();
	}

}

return 'propertyCreateProcessor';