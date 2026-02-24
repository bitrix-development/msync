<?php
class propertyRemoveProcessor extends modObjectRemoveProcessor {
	public $checkRemovePermission = true;
	public $classKey = 'mSyncProductProperty';
	public $languageTopics = array('msync');

	public function beforeRemove() {
		return parent::beforeRemove();
	}

}
return 'propertyRemoveProcessor';