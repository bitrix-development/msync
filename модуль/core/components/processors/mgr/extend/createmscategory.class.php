<?php
/**
 * Overrides the modResourceCreateProcessor to provide custom processor functionality for the msCategory type
 *
 * @package mSync
 */

require_once MODX_CORE_PATH.'model/modx/modprocessor.class.php';
require_once MODX_CORE_PATH.'model/modx/processors/resource/create.class.php';
require_once MODX_CORE_PATH.'components/minishop2/processors/mgr/category/create.class.php';

class extendModCategoryCreateProcessor extends modResourceCreateProcessor
{

	public static function getInstance(modX &$modx, $className, $properties = array())
	{

		$classKey = !empty($properties['class_key']) ? $properties['class_key'] : 'modDocument';
		$object = $modx->newObject($classKey);

		$className = 'msCategoryDisableCacheCreateProcessor';
		$processor = new $className($modx, $properties);
		return $processor;
	}
}

class msCategoryDisableCacheCreateProcessor extends msCategoryCreateProcessor
{

	public function checkParentPermissions() { //FIX disable checkParentPermissions to add children
//		return parent::checkParentPermissions();
		return true;
	}

	public function prepareAlias() {  //FIX on dublicate, add id to alias
		if ($this->workingContext->getOption('ms2_category_id_as_alias')) {
			$alias = 'empty-resource-alias';
		}
		else {
			$alias = '';

			/* friendly url alias checks */
			$pageTitle = $this->getProperty('pagetitle');
			if ($this->workingContext->getOption('friendly_urls', false) && (!$this->getProperty('reloadOnly', false) || !empty($pageTitle))) {
				$alias = $this->getProperty('alias');

				/* auto assign alias */
				if (empty($alias) && $this->workingContext->getOption('automatic_alias', false)) {
					$alias = $this->object->cleanAlias($pageTitle);
				}
				if (empty($alias)) {
					$this->addFieldError('alias', $this->modx->lexicon('field_required'));
				} else {
					$duplicateContext = $this->workingContext->getOption('global_duplicate_uri_check', false) ? '' : $this->getProperty('context_key');
					$aliasPath = $this->object->getAliasPath($alias, $this->getProperties());
					$duplicateId = $this->object->isDuplicateAlias($aliasPath, $duplicateContext);
					if ($duplicateId) {
						$alias = $alias.'-'.$duplicateId;

						$err = $this->modx->lexicon('duplicate_uri_found', array(
							'id' => $duplicateId,
							'uri' => $aliasPath,
						));
						$this->addFieldError('uri', $err);
						if ($this->getProperty('uri_override', 0) !== 1) {
							$this->addFieldError('alias', $err);
						}
					}
				}
			}
		}
		$this->setProperty('alias', $alias);
		return $alias;
	}

	public function afterSave() {  //FIX disable "Updating resourceMap before OnDocSaveForm event" and disable lock
		if ($this->object->alias == 'empty-resource-alias') {
			$this->object->set('alias', $this->object->id);
			$this->object->save();
		}

//		$this->object->addLock();
		$this->setParentToContainer();
		$this->saveResourceGroups();
        $this->checkIfSiteStart();
		return true;
	}

	public function cleanup() { //FIX disable clearCache and disable lock
//		$this->object->removeLock();
//		$this->clearCache();
		return $this->success('', array('id' => $this->object->get('id')));
	}

    public function success($msg = '',$object = null) {
        $result = parent::success($msg, $object);
        unset($this->object);
        return $result;
    }

}

return 'extendModCategoryCreateProcessor';