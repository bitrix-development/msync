<?php

/**
 * The base class for mSync.
 *
 * @package msync
 */
class mSync
{
    /* @var modX $modx */
    public $modx;
    /* @var mSyncControllerRequest $request */
    protected $request;
    public $initialized = array();

    /* @var msyncCatalogInterface $catalog */
    public $catalog;
    /* @var msyncSaleInterface $sale */
    public $sale;


    function __construct(modX &$modx, array $config = array())
    {
        $this->modx =& $modx;

        $corePath = $this->modx->getOption('msync_core_path', $config, $this->modx->getOption('core_path') . 'components/msync/');
        $assetsUrl = $this->modx->getOption('msync_assets_url', $config, $this->modx->getOption('assets_url') . 'components/msync/');
        $connectorUrl = $assetsUrl . 'connector.php';

        $this->config = array_merge(array(
            'assetsUrl' => $assetsUrl
        , 'cssUrl' => $assetsUrl . 'css/'
        , 'jsUrl' => $assetsUrl . 'js/'
        , 'imagesUrl' => $assetsUrl . 'images/'
        , 'connectorUrl' => $connectorUrl
        , 'corePath' => $corePath
        , 'logsPath' => $corePath . "logs/"
        , 'assetsPath' => $this->modx->getOption('assets_path') . 'components/msync/'
        , 'modelPath' => $corePath . 'model/'
        , 'chunksPath' => $corePath . 'elements/chunks/'
        , 'templatesPath' => $corePath . 'elements/templates/'
        , 'chunkSuffix' => '.chunk.tpl'
        , 'snippetsPath' => $corePath . 'elements/snippets/'
        , 'processorsPath' => $corePath . 'processors/'
        , 'debug' => $this->modx->getOption('msync_debug')
        , 'commercMlLink' => MODX_SITE_URL . 'assets/components/msync/1c_exchange.php'
        ), $config);

        $this->modx->addPackage('msync', $this->config['modelPath']);
        $this->modx->lexicon->load('msync:default');
    }


    /**
     * Initializes mSync into different contexts.
     *
     * @access public
     * @param string $ctx The context to load. Defaults to web.
     */
    public function initialize($ctx = 'web', $scriptProperties = array())
    {
        $this->config = array_merge($this->config, $scriptProperties);
        $this->config['ctx'] = $ctx;
        if (!empty($this->initialized[$ctx])) {
            return true;
        }
        switch ($ctx) {
            case 'mgr':
                break;
            case 'web':
                require_once dirname(__FILE__) . '/msynccataloghandler.class.php';
                $catalog_class = $this->modx->getOption('msync_catalog_handler_class', null, 'msyncCatalogHandler');
                if ($catalog_class != 'msyncCatalogHandler') {
                    $this->loadCustomClasses($catalog_class);
                }
                if (!class_exists($catalog_class)) {
                    $catalog_class = 'msyncCatalogHandler';
                }

                $this->catalog = new $catalog_class($this, $this->config);
                if (!($this->catalog instanceof msyncCatalogInterface) || $this->catalog->initialize($ctx) !== true) {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not initialize mSync catalog handler class: "' . $catalog_class . '"');
                    return false;
                }

                require_once dirname(__FILE__) . '/msyncsalehandler.class.php';
                $sale_class = $this->modx->getOption('msync_sale_handler_class', null, 'msyncSaleHandler');
                if ($sale_class != 'msyncSaleHandler') {
                    $this->loadCustomClasses($sale_class);
                }
                if (!class_exists($sale_class)) {
                    $sale_class = 'msyncSaleHandler';
                }

                $this->sale = new $sale_class($this, $this->config);
                if (!($this->sale instanceof msyncSaleInterface) || $this->sale->initialize($ctx) !== true) {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not initialize mSync sale handler class: "' . $sale_class . '"');
                    return false;
                }
                break;
            default:
                /* if you wanted to do any generic frontend stuff here.
                 * For example, if you have a lot of snippets but common code
                 * in them all at the beginning, you could put it here and just
                 * call $msync->initialize($modx->context->get('key'));
                 * which would run this.
                 */
                break;
        }
    }

    private function loadCustomClasses($className)
    {
        require_once dirname(__FILE__) . "/{$className}.class.php";
    }

    protected function getLogFile($fileName)
    {
        if (!$fileName) {
            $fileName = 'errors_' . date('y-m-d_His');
        }
        return "{$this->config['logsPath']}{$fileName}.txt";
    }

    /**
     * @param string $logFile Файл лога
     * @param string $string Строка лога
     * @param bool|false $isDebug True, если данные только для дебага
     * @param bool $modxLogError True, если надо записать в лог ошибок MODX
     */
    public function logFile($logFile, $string, $isDebug = false, $modxLogError = false)
    {
        if ($modxLogError) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[mSync] '.$string);
        }

        if ($isDebug && !$this->config['debug']) return;
        $t = microtime(true);
        $t = round($t - floor($t), 3) * 1000;

        $file = fopen($this->getLogFile($logFile), "a");
        if (!$file) return;
        fwrite($file, date("d.m.y H:i:s.") . $t . "  " . $string ."\r\n");
		fclose($file);
	}

    /**
     * @param string $string Строка лога
     * @param bool|false $isDebug True, если данные только для дебага
     * @param bool $modxLogError True, если надо записать в лог ошибок MODX
     */
    public function log($string, $isDebug = false, $modxLogError = false)
    {
        $this->logFile($_SESSION['mSyncLogFile'], $string, $isDebug, $modxLogError);
	}

    /**
     * Write REST log
     * @return void
     */
    public function restLog($name, $request)
    {
        $file = fopen($this->config['logsPath'] . date("dmy_His") . '_' . round(microtime(1) * 1000) . '_' . $name . ".txt", "w");
        if ($file) {
            fputs($file, print_r($request, true));
        }
        fclose($file);
    }

    /**
     * Shorthand for original modX::invokeEvent() method with some useful additions.
     * Взято из miniShop2 by Василий Наумкин
     *
     * @param $eventName
     * @param array $params
     * @param $glue
     *
     * @return array
     */
    public function invokeEvent($eventName, array $params = array(), $glue = '<br/>')
    {
        if (isset($this->modx->event->returnedValues)) {
            $this->modx->event->returnedValues = null;
        }

        $response = $this->modx->invokeEvent($eventName, $params);
        if (is_array($response) && count($response) > 1) {
            foreach ($response as $k => $v) {
                if (empty($v)) {
                    unset($response[$k]);
                }
            }
        }

        $message = is_array($response) ? implode($glue, $response) : trim((string)$response);
        if (isset($this->modx->event->returnedValues) && is_array($this->modx->event->returnedValues)) {
            $params = array_merge($params, $this->modx->event->returnedValues);
        }

        return array(
            'success' => empty($message),
            'message' => $message,
            'data' => $params,
        );
    }

    /**
     * Clearing cache
     * @return void
     */
    public function clearCache($context = null)
    {
        $this->modx->cacheManager->refresh();
    }

    public function clearLogs() {
        $tmp_files = glob($this->config['logsPath'] . '*.*');
        if (is_array($tmp_files)) {
            foreach ($tmp_files as $v) {
                unlink($v);
            }
        }
    }

    public function utf_json_encode($arr)
    {
        return preg_replace_callback(
            '/\\\u([0-9a-fA-F]{4})/', function($_m) {
                return mb_convert_encoding("&#" . intval($_m[1], 16) . ";", "UTF-8", "HTML-ENTITIES");
            },
            json_encode($arr)
        );
    }

    /**
     * Возвращает XMLReader
     * @return mSyncXmlReader
     */
    public function getXmlReader() {
        require_once dirname(__FILE__) . '/msyncxmlreader.class.php';
        return new mSyncXmlReader($this);
    }
}