<?php
/**
 * Overrides the modResourceUpdateProcessor to provide custom processor functionality for the msCategory type
 *
 * @package mSync
 */

require_once MODX_CORE_PATH.'model/modx/modprocessor.class.php';
require_once MODX_CORE_PATH.'model/modx/processors/resource/update.class.php';
require_once MODX_CORE_PATH.'components/minishop2/processors/mgr/category/update.class.php';

class extendModCategoryUpdateProcessor extends modResourceUpdateProcessor
{

	public static function getInstance(modX &$modx, $className, $properties = array())
	{
		$object = $modx->getObject('modResource',$properties['id']);
        $objArray = $object ? $object->toArray() : array();
        $properties = array_merge($objArray, $properties);

		$processor = new msCategoryDisableCacheUpdateProcessor($modx, $properties);
		return $processor;
	}
}

class msCategoryDisableCacheUpdateProcessor extends msCategoryUpdateProcessor
{
	public function clearCache()
	{
		return;
	}

    public function success($msg = '',$object = null) {
        $result = parent::success($msg, $object);
        unset($this->object);
        return $result;
    }
}

return 'extendModCategoryUpdateProcessor';