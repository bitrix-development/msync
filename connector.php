<?php
/**
 * mSync Connector
 *
 * @package msync
 */
require_once dirname(dirname(dirname(dirname(__FILE__)))).'/config.core.php';
require_once MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php';
require_once MODX_CONNECTORS_PATH.'index.php';

$corePath = $modx->getOption('msync_core_path',null,$modx->getOption('core_path').'components/msync/');
require_once $corePath.'model/msync/msync.class.php';
$modx->msync = new mSync($modx);

$modx->lexicon->load('msync:default');

/* handle request */
$path = $modx->getOption('processorsPath',$modx->msync->config,$corePath.'processors/');
$modx->request->handleRequest(array(
    'processors_path' => $path,
    'location' => '',
));