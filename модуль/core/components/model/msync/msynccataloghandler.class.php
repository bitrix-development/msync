<?php

interface msyncCatalogInterface
{

    /* Initializes cart to context
     * Here you can load custom javascript or styles
     *
     * @param string $ctx Context for initialization
     *
     * @return boolean
     * */
    public function initialize($ctx = 'web');

    /* checkauth the catalog
     *
     * @return array|string $response
     * */
    public function checkauth();

    /* init the catalog
     *
     * @return array|string $response
     * */
    public function init();


    /* load catalog file
     *
     * @param string $filename The filename of catalog
     *
     * @param string $file The file of catalog
     *
     * @return array|string $response
     * */
    public function file($filename, $file);

    /* import catalog
     *
     * @param string $filename The filename of catalog
     *
     * @param string $file The file of catalog
     *
     * @return array|string $response
     * */
    public function import($filename, $file);

}


class msyncCatalogHandler implements msyncCatalogInterface
{
    /** @var modX $modx */
    public $modx;
    protected $options = array();
    protected $properties = array();
    protected $primaryProperties = array();
    protected $propertyFields = array('id', 'source', 'type', 'target', 'is_multiple', 'is_primary');
    protected $catalog;

    /** @var  mSync */
    protected $msync;

    /** @var  mSyncXmlReader */
    protected $xmlReader;

    protected $msOptionsPriceTarget;
    protected $msOptionsPriceSource;

    const FAIL_MSG = "failure\r\nPlease see errors in MODX log\r\n";

    public function __construct(mSync $msync, array $config = array())
    {
        $this->msync = &$msync;
        $this->xmlReader = $msync->getXmlReader();
        $this->modx = &$msync->modx;

        $this->config = array_merge(array(
            'temp_dir' => $this->modx->getOption('msync_assets_path', $config, $this->modx->getOption('assets_path') . 'components/msync/1c_temp/'),
            'price_by_feature_tv' => $this->modx->getOption('msync_price_by_feature_tv', null, ''),
            'catalog_root_id' => $this->modx->getOption('msync_catalog_root_id', $config, -1),
            'category_by_name' => $this->modx->getOption('msync_category_by_name', $config, false),
            'no_categories' => $this->modx->getOption('msync_no_categories', $config, false),
            'catalog_context' => $this->modx->getOption('msync_catalog_context', $config, 'web'),
            'user_id_import' => $this->modx->getOption('msync_user_id_import', $config, 1),
            'publish_default' => $this->modx->getOption('msync_publish_default', $config, 0),
            'time_limit' => $this->modx->getOption('msync_time_limit', $config, 60),
            'product_source' => $this->modx->getOption('ms2_product_source_default', $config, 1),
            'product_template' => $this->modx->getOption('msync_template_product_default', $config, ''),
            'category_template' => $this->modx->getOption('msync_template_category_default', $config, ''),
            'create_properties_tv' => $this->modx->getOption('msync_create_properties_tv', null, false),
            'create_prices_tv' => $this->modx->getOption('msync_create_prices_tv', null, false),
            'save_properties_to_tv' => $this->modx->getOption('msync_save_properties_to_tv', null, ''),
            'import_all_prices' => $this->modx->getOption('msync_import_all_prices', null, false),
            'publish_by_quantity' => $this->modx->getOption('msync_publish_by_quantity', null, false),
            'hidemenu_by_quantity' => $this->modx->getOption('msync_hidemenu_by_quantity', null, false),
            'remove_temp' => $this->modx->getOption('msync_remove_temp', null, true),
            'rewrite_import_files' => $this->modx->getOption('msync_rewrite_import_files', null, true),
            'only_offers' => $this->modx->getOption('msync_import_only_offers', null, false),
            'temp_count' => $this->modx->getOption('msync_import_temp_count', null, false),
        ), $config);

        $this->catalog = &$this->config['catalog'];
        $this->modx->lexicon->load('msync:catalog');

        if (empty($this->catalog) || !is_array($this->catalog)) {
            $this->catalog = array();
        }

        $this->config['start_time'] = microtime(true);
        $this->config['max_exec_time'] = min($this->config['time_limit'], @ini_get('max_execution_time'));
        if (empty($this->config['max_exec_time'])) $this->config['max_exec_time'] = 6;
        $this->modx->user = $this->modx->getObject('modUser', $this->config['user_id_import']);


        $this->options = array(
            'start' => (!isset($_SESSION['totalCategories'])) ? 1 : 0,
            'finish' => isset($_SESSION['importFinish']) ? $_SESSION['importFinish'] : 0,
            'lastCategory' => isset($_SESSION['lastCategory']) ? $_SESSION['lastCategory'] : 0,
            'totalCategories' => isset($_SESSION['totalCategories']) ? $_SESSION['totalCategories'] : 0,
            'lastProperty' => isset($_SESSION['lastProperty']) ? $_SESSION['lastProperty'] : -1,
            'totalProperties' => isset($_SESSION['totalProperties']) ? $_SESSION['totalProperties'] : 0,
            'lastImportProduct' => isset($_SESSION['lastImportProduct']) ? $_SESSION['lastImportProduct'] : 0,
            'lastProduct' => isset($_SESSION['lastProduct']) ? $_SESSION['lastProduct'] : 0,
            'totalProducts' => isset($_SESSION['totalProducts']) ? $_SESSION['totalProducts'] : 0,
        );
    }


    /* @inheritdoc} */
    public function initialize($ctx = 'web')
    {
        $this->initLog();
        return true;
    }

    /* @inheritdoc} */
    public function checkauth()
    {
        $this->log("Проверка авторизации ({$_SERVER['REQUEST_URI']})");

        if (!$this->checkCatalogRoot()) return self::FAIL_MSG;
        if (!$this->checkUser()) return self::FAIL_MSG;
        return 'success' . PHP_EOL . session_name() . PHP_EOL . session_id();
    }

    /* @inheritdoc} */
    public function init()
    {
        if ($this->config['remove_temp']) {
            $tmp_files = glob($this->config['temp_dir'] . '*.*');
            if (is_array($tmp_files)) {
                $count = count($tmp_files);
                $this->log("Удаление временных файлов: {$count} шт.");
                foreach ($tmp_files as $v) {
                    unlink($v);
                    $this->log("Файл {$v} удален.", 1);
                }
            }
            $this->modx->runProcessor('browser/directory/remove', array('dir' => $this->config['temp_dir'] . 'import_files'));
        }

        $this->resetSession();

        return 'zip=no' . PHP_EOL . 'file_limit=' . $this->modx->getOption('upload_maxsize', null, 1000000) . PHP_EOL;
    }

    /**
     * Очистка переменных сессии
     */
    public function resetSession()
    {
        $this->log("Переменные сессии очищены.");
        unset($_SESSION['last_1c_offer']
            , $_SESSION['importFinish']
            , $_SESSION['lastCategory']
            , $_SESSION['totalCategories']
            , $_SESSION['lastProperty']
            , $_SESSION['totalProperties']
            , $_SESSION['lastImportProduct']
            , $_SESSION['lastProduct']
            , $_SESSION['totalProducts']
            , $_SESSION['categories_mapping']
            , $_SESSION['properties_mapping']
            , $_SESSION['feature_mapping']
            , $_SESSION['price_mapping']
            , $_SESSION['importResources']
        );

        $_SESSION['feature_mapping'] = array();
        $_SESSION['importFileCount'] = 0;

        // Хранилище созданных и обновленных категорий и товаров
        if (!isset($_SESSION['importResources'])) {
            $_SESSION['importResources'] = array(
                'category' => array(
                    'created' => array(),
                    'updated' => array()
                ),
                'product' => array(
                    'created' => array(),
                    'updated' => array()
                )
            );
        }
    }

    /* @inheritdoc} */
    public function file($filename = '', $file)
    {
        if (!$filename) {
            $this->log('Ошибка импорта каталога, передано пустое имя файла (переменная filename)', 0, 1);
            return self::FAIL_MSG;
        }

        $this->log("Загрузка файла {$filename} началась.", 1);

        $this->log("Вызвано событие mSyncOnCatalogFileImport", 1);
        $response = $this->msync->invokeEvent('mSyncOnCatalogFileImport', array(
            'filename' => $filename
        ));

        $filename = $response['data']['filename'];
        unset($response);
        $filename = $this->config['temp_dir'] . $filename;

        mkdir(dirname($filename), 0777, true);
        $fileMode = $this->config['rewrite_import_files'] ? 'wb' : 'ab';
        $f = fopen($filename, $fileMode);
        fwrite($f, $file);
        fclose($f);

        $this->log("Файл {$filename} успешно загружен.", 1);
        $_SESSION['importFileCount']++;
        return 'success' . PHP_EOL;
    }

    /**
     * Проверка является ли импортом товаров
     * @param $filename
     * @return bool
     */
    protected function isImport($filename)
    {
        return (strpos($filename, 'import') === 0);
    }

    /**
     * Проверка является ли импортом предложений
     * @param $filename
     * @return bool
     */
    protected function isOffers($filename)
    {
        return (strpos($filename, 'offers') === 0);
    }

    /**
     * Подсчет полученных файлов
     */
    protected function checkLoadedFiles()
    {
        if (!isset($_SESSION['importFileCount'])) return;
        $this->log("Загружено файлов: {$_SESSION['importFileCount']}");
        unset($_SESSION['importFileCount']);
    }

    /**
     * Удаляет сломанные ссылки на категории
     */
    protected function deleteBrokenCategoryLinks() {
        $sql = "DELETE FROM {$this->modx->getTableName('mSyncCategoryData')} WHERE `category_id` IN 
          (SELECT id FROM (SELECT c.category_id AS id FROM {$this->modx->getTableName('mSyncCategoryData')} c 
          LEFT JOIN {$this->modx->getTableName('modResource')} r ON c.category_id=r.id WHERE r.id IS NULL OR c.uuid_1c='') AS c1);";
        $stmt = $this->modx->prepare($sql);
        $stmt->execute();
        $stmt->closeCursor();
    }

    /**
     * Удаляет сломанные ссылки на товары
     */
    protected function deleteBrokenProductLinks() {
        $sql = "DELETE FROM {$this->modx->getTableName('mSyncProductData')} WHERE `product_id` IN 
          (SELECT id FROM (SELECT c.product_id AS id FROM {$this->modx->getTableName('mSyncProductData')} c 
          LEFT JOIN {$this->modx->getTableName('modResource')} r ON c.product_id=r.id WHERE r.id IS NULL OR c.uuid_1c='') AS c1);";
        $stmt = $this->modx->prepare($sql);
        $stmt->execute();
        $stmt->closeCursor();
    }

    /**
     * Импорт товаров из XML файла
     * @param $filename
     * @return bool|int|string
     */
    protected function doImport($filename)
    {
        $this->log("Вызвано событие mSyncOnBeforeImport(catalog)", 1);
        $this->modx->invokeEvent('mSyncOnBeforeImport', array(
            'mode' => 'catalog',
            'filename' => $filename
        ));

        $this->deleteBrokenCategoryLinks();
        $this->deleteBrokenProductLinks();

        $this->checkLoadedFiles();

        $out = '';

        $step = $this->checkImportCatalogStep();
        $this->log("Начат шаг импорта {$step}", 1);
        switch ($step) {
            case 'importCategories':
                $out = $this->importCategories($filename);
                break;

            case 'prepareCategories':
                $out = $this->prepareCategories();
                break;

            case 'importProperties':
                $out = $this->importProperties($filename);
                break;

            case 'importProducts':
                $out = $this->importProducts($filename);
                break;

            case 'prepareProducts':
                $out = $this->prepareProducts();
                break;

            case 'finish':
                $out = $this->finish();
                break;
        }

        return $out;
    }


    /* @inheritdoc} */
    public function import($filename = '', $file)
    {
        if (!$filename) {
            $this->log('Ошибка импорта каталога, передано пустое имя файла (переменная filename)', 0, 1);
            return self::FAIL_MSG;
        }

        $this->config['start_time'] = microtime(true);
        $this->log("Начата обработка файла {$filename}", 1);

        $filename = basename($filename);

        if (!file_exists($this->config['temp_dir'] . $filename)) {
            $this->log('Ошибка импорта каталога, не существует файл ' . $this->config['temp_dir'] . $filename, 0, 1);
            return self::FAIL_MSG;
        }

        if ($this->isImport($filename)) {
            return $this->doImport($filename);
        }

        if ($this->isOffers($filename)) {
            return $this->doOffers($filename);
        }

        $this->log("Вызвано событие mSyncOnImportUnknownFile", 1);
        $fileManaged = $this->modx->invokeEvent('mSyncOnImportUnknownFile', array(
            'filename' => $filename
        ));

        if ($fileManaged) {
           return $fileManaged;
        } else {
            $this->log('Ошибка импорта каталога, неизвестный файл ' . $this->config['temp_dir'] . $filename, 0, 1);
            return self::FAIL_MSG;
        }
    }

    /**
     * Check step of import to catalog
     *
     * @return string
     */
    protected function checkImportCatalogStep()
    {
        if ($this->config['only_offers']) {
            $msg = 'Включена настройка "Импортировать только торговые предложения". Импортирование категорий и товаров пропущено.';
            $this->log($msg);
            return 'finish';
        }

        $start = $this->options['start'];
        $finish = $this->options['finish'];

        $lastCategory = $this->options['lastCategory'];
        $totalCategories = $this->options['totalCategories'];

        $lastProperty = $this->options['lastProperty'];
        $totalProperties = $this->options['totalProperties'];

        //$lastImportProduct = $this->options['lastImportProduct'];
        $lastProduct = $this->options['lastProduct'];
        $totalProducts = $this->options['totalProducts'];

        $this->log("Параметры определения текущего шага: start={$start}, finish={$finish},
            lastCategory={$lastCategory}, totalCategories={$totalCategories}, lastProperty={$lastProperty},
            totalProperties={$totalProperties}, lastProduct={$lastProduct}, totalProducts={$totalProducts}", 1);

        if ($start) return 'importCategories';
        if ($finish) return 'finish';

        if ($lastCategory < $totalCategories) return 'prepareCategories';

        if ($lastProperty < $totalProperties) return 'importProperties';

        if (!$totalProducts) return 'importProducts';

        if ($lastProduct < $totalProducts) return 'prepareProducts';

        return 'finish';
    }

    /**
     * Возвращает XML Reader
     * @param $filename
     * @param $search
     * @return XMLReader
     */
    protected function getXmlReader($filename, $search)
    {
        return $this->xmlReader->getXmlReader($this->config['temp_dir'].$filename, $search);
    }

    /**
     * Читает XML
     * @param XMLReader $reader
     * @return SimpleXMLElement
     */
    protected function readXml($reader)
    {
        return $this->xmlReader->readXml($reader);
    }

    /**
     * Возвращает обработанную строку из значения узла XML
     * @param SimpleXMLElement|SimpleXMLElement[] $value
     * @return string
     */
    protected function stringXml($value)
    {
        return $this->xmlReader->stringXml($value);
    }

    /**
     *  Возвращает JSON строку из массива
     * @param array|object $array
     * @return string
     */
    protected function jsonXml($array)
    {
        return $this->xmlReader->jsonXml($array);
    }

    /**
     * Import categories to temp table
     *
     * @param $filename
     *
     * @return string
     */
    protected function importCategories($filename)
    {
        if ($this->config['no_categories']) {
            $msg = 'Включена настройка "Не создавать категории". Импортирование категорий пропущено.';
            $this->log($msg);

            $_SESSION['totalCategories'] = $_SESSION['lastCategory'] = 1;
            return 'progress' . PHP_EOL . $msg . PHP_EOL;
        }

        $this->log("Начат импорт категорий из XML файла во временную таблицу БД. Таблица очищена.", 1);
        //clear temp category table
        $this->modx->exec("TRUNCATE TABLE {$this->modx->getTableName('mSyncCategoryTemp')}");

        $reader = $this->getXmlReader($filename, 'Группы');
        if ($reader->name == 'Группы') {
            $xml = $this->readXml($reader);
            $this->importCategory($xml);
        }
        $reader->close();

        $totalCategories = $this->getTotalCategories();
        $this->options['totalCategories'] = $_SESSION['totalCategories'] = $totalCategories;


        $this->log("Вызвано событие mSyncAfterImportCategories", 1);
        $this->modx->invokeEvent('mSyncAfterImportCategories', array(
            'total' => $totalCategories
        ));

        $msg = 'Категории выгружены во временную таблицу: ' . $totalCategories;
        $this->log($msg);
        return 'progress' . PHP_EOL . $msg . PHP_EOL;
    }

    /**
     * Import category to temp table
     *
     * @param $xml
     * @param int $parent_id
     * @param int $level
     */
    protected function importCategory($xml, $parent_id = 0, $level = 0)
    {
        if (!isset($xml->Группа)) return;

        foreach ($xml->Группа as $xml_group) {
            $data = array(
                'name' => $this->stringXml($xml_group->Наименование),
                'uuid' => $this->stringXml($xml_group->Ид),
                'parent_uuid' => $parent_id
            );

            $this->log("Вызвано событие mSyncOnBeforeImportCategory", 1);
            $response = $this->msync->invokeEvent('mSyncOnBeforeImportCategory', array(
                'xml' => $xml_group,
                'data' => $data,
                'level' => $level
            ));

            $data = $response['data']['data'];
            unset($response);

            $sql = "INSERT " . "INTO " . $this->modx->getTableName('mSyncCategoryTemp') . " (`name`, `uuid`, `parent_uuid`, `level`) VALUES
						('{$data['name']}', '{$data['uuid']}', '{$data['parent_uuid']}', '{$level}');";

            $stmt = $this->modx->prepare($sql);
            $stmt->execute();

            $this->log("Категория \"{$data['name']}\" (uuid = {$data['uuid']}, parent_uuid = {$data['parent_uuid']}, level = {$level}) выгружена во временную таблицу.", 1);
            if (isset($xml_group->Группы)) $this->importCategory($xml_group->Группы, $data['uuid'], $level + 1);
        }

    }

    /**
     * Get total num categories in temp table
     *
     * @return bool|int
     */
    protected function getTotalCategories()
    {
        return $this->modx->getCount('mSyncCategoryTemp');
    }

    /**
     * Возвращает временные данные категории
     * @param $limit
     * @param $offset
     * @return array
     */
    protected function getCategoryTempData($limit, $offset)
    {
        $q = $this->modx->newQuery('mSyncCategoryTemp');
        $q->select($this->modx->getSelectColumns('mSyncCategoryTemp', 'mSyncCategoryTemp', '', array('id', 'name', 'uuid', 'parent_uuid', 'level')));
        $q->sortby('level ASC, id', 'ASC');
        $q->limit($limit, $offset);

        if ($q->prepare() && $q->stmt->execute()) {
            $categoriesData = $q->stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $categoriesData = array();
        }

        return $categoriesData;
    }

    /**
     * Возвращает True, если время выполнения скрипта заканчивается
     * @return bool
     */
    protected function checkExecTime()
    {
        $exec_time = microtime(true) - $this->config['start_time'];
        return ($exec_time + 1 >= $this->config['max_exec_time']);
    }

    /**
     * Prepare categories
     *
     * @return string
     */
    protected function prepareCategories()
    {
        $lastCategory = $this->options['lastCategory'];
        $totalCategories = $this->options['totalCategories'];
        $firstCategory = $lastCategory+1;
        $this->log("Начата подготовка категорий начиная с {$firstCategory} из {$totalCategories}", 1);

        $categoriesData = $this->getCategoryTempData($this->config['temp_count'], $lastCategory);
        $this->log("Получено категорий из временной таблицы: " . count($categoriesData), 1);

        foreach ($categoriesData as $categoryData) {
            $this->prepareCategory($categoryData);

            $_SESSION['lastCategory'] = ++$lastCategory;
            if ($this->checkExecTime()) break;

        }

        $msg = 'Импортировано категорий ' . $lastCategory . ' из ' . $totalCategories;
        $this->log($msg);
        return 'progress' . PHP_EOL . $msg . PHP_EOL;
    }

    /**
     * Очищает ошибки MODX
     */
    protected function clearModxErrors()
    {
        $this->modx->error->message = null;
        $this->modx->error->errors = array();
        // fix потери юзера
        if (!$this->modx->user || !$this->modx->user->id) {
            $this->modx->user = $this->modx->getObject('modUser', $this->config['user_id_import']);
        }
    }

    /**
     * Возвращает данные привязки категории в MODX
     * @param array $categoryData
     * @return null|mSyncCategoryData
     */
    protected function getCategoryData($categoryData)
    {
        $uuid = $categoryData['uuid'];
        /** @var mSyncCategoryData $data */
        $data = $this->modx->getObject('mSyncCategoryData', array('uuid_1c' => $uuid));
        if (!$data && $this->config['category_by_name']) {
            $category = $this->modx->getObject('msCategory', array(
                'pagetitle' => $categoryData['name'],
                'parent' => $categoryData['parent_id'],
            ));
            if ($category) {
                // обрабатываем случай, когда категория с таким именем изменилась в 1С
                $data = $this->modx->getObject('mSyncCategoryData', array('category_id' => $category->get('id')));
                if ($data) {
                    $data->set('uuid_1c', $uuid);
                    $data->save();
                } else {
                    return $this->createCategoryData($category->get('id'), $uuid);
                }
            }
        }
        return $data;
    }

    /**
     * Обновляет данные в MODX по данным привязки
     * @param mSyncCategoryData $catData
     * @param int $parentId Идентификатор родительской категории
     * @param string $categoryName
     * @return mSyncCategoryData|null
     */
    protected function updateByCategoryData($catData, $categoryName, $parentId)
    {
        $categoryId = $catData->get('category_id');

        //if isset msCategory object, then update
        $category = $this->getCategory($categoryId);
        if ($category) {
            $this->log("Категория {$categoryId} найдена. Обновление с параметрами parentId={$parentId}, categoryName={$categoryName}.", 1);
            $this->updateMsCategory($parentId, $categoryId, $categoryName);
            return $catData;
        } //else create new msCategory object
        else {
            $this->log("Категория {$categoryId} не найдена. Создание новой категории с параметрами parentId={$parentId}, categoryName={$categoryName}.", 1);
            $categoryId = $this->createMsCategory($parentId, $categoryName);
            if (!$categoryId) return $catData;
            $newCatData = $this->createCategoryData($categoryId, $catData->get('uuid_1c'));
            $catData->remove();
            return $newCatData;
        }
    }

    /**
     * Создает новую категорию минишопа и возвращает привязку к ней
     * @param string $categoryName
     * @param string $uuid
     * @param int $parentId
     * @return mSyncCategoryData|null
     */
    protected function createByCategoryData($categoryName, $uuid, $parentId)
    {
        $this->log("Создание новой категории с параметрами parentId={$parentId}, categoryName={$categoryName}.", 1);
        $categoryId = $this->createMsCategory($parentId, $categoryName);
        if (!$categoryId) return null;

        return $this->createCategoryData($categoryId, $uuid);
    }

    /**
     * Создает новую привязку категории на сайте к категории в складе
     * @param int $categoryId
     * @param string $uuid
     * @return null|mSyncCategoryData
     */
    protected function createCategoryData($categoryId, $uuid)
    {
        $newCatData = $this->modx->newObject('mSyncCategoryData');
        $newCatData->set('category_id', $categoryId);
        $newCatData->set('uuid_1c', $uuid);
        $newCatData->save();
        return $newCatData;
    }

    /**
     * Возвращает категорию минишопа по id
     * @param $id
     * @return null|modResource
     */
    protected function getCategory($id)
    {
        return $this->modx->getObject('msCategory', $id);
    }

    /**
     * Prepare data to create or update miniShop2 category
     *
     * @param $categoryData
     */
    protected function prepareCategory($categoryData)
    {
        if (isset($_SESSION['categories_mapping'][$categoryData['uuid']])) {
            return; // Категория уже импортирована
        }

        $this->log("Начато обновление категории по временным данным " . print_r($categoryData, 1), 1);

        $parent_uuid = $categoryData['parent_uuid'];
        $parentId = isset($_SESSION['categories_mapping'][$parent_uuid])
            ? $_SESSION['categories_mapping'][$parent_uuid]
            : $this->config['catalog_root_id'];
        $categoryData['parent_id'] = $parentId;

        $this->clearModxErrors();

        $catData = $this->getCategoryData($categoryData);

        $this->log("Вызвано событие mSyncOnPrepareCategory для категории {$categoryData['name']} с uuid={$categoryData['uuid']}", 1);
        $response = $this->msync->invokeEvent('mSyncOnPrepareCategory', array(
            'data' => &$catData,
            'uuid' => $categoryData['uuid'],
            'name' => $categoryData['name'],
            'parent' => $parentId,
            'parentUuid' => $parent_uuid,
        ));

        $catData = $response['data']['data'];
        $categoryName = $response['data']['name'];
        $parentId = $response['data']['parent'];
        $categoryUuid = $catData ? $catData->get('uuid_1c') : $categoryData['uuid'];

        $newCatData = $catData
            ? $this->updateByCategoryData($catData, $categoryName, $parentId)
            : $this->createByCategoryData($categoryName, $categoryUuid, $parentId);

        if (!$newCatData) {
            $this->log("Не удалось создать привязку для параметров " . print_r($categoryData, 1), 0, 1);
            return;
        }
        $categoryId = $newCatData->get('category_id');
        $uuid = $newCatData->get('uuid_1c');
        $this->log("Создана привязка категории минишопа category_id={$categoryId} с uuid={$uuid}", 1);
        $category = $this->getCategory($categoryId);

        $this->log("Вызвано событие mSyncOnProductImport(category) для категории с id = {$categoryId}", 1);
        $this->modx->invokeEvent('mSyncOnProductImport', array(
            'mode' => 'category',
            'resource' => &$category,
            'properties' => array(),
            'data' => &$newCatData,
        ));

        $_SESSION['categories_mapping'][$uuid] = $categoryId;
        $this->log("В сессию сохранена привязка с category_id = {$categoryId} и uuid={$uuid}", 1);
    }

    /**
     * Create new miniShop2 category
     *
     * @param $parentId
     * @param $categoryName
     * @return bool|int
     */
    protected function createMsCategory($parentId, $categoryName)
    {
        $this->clearModxErrors();

        $processorProps = array(
            'class_key' => 'msCategory'
            , 'pagetitle' => $categoryName
            , 'parent' => $parentId
            , 'published' => $this->config['publish_default']
            , 'context_key' => $this->config['catalog_context']
            , 'template' => $this->config['category_template']
        );
        $response = $this->modx->runProcessor('mgr/extend/createmscategory', $processorProps, array('processors_path' => $this->config['processorsPath']));
        unset($processorProps);
        if (!$response->isError()) {
            $_SESSION['importResources']['category']['created'][] = $categoryId = $response->response['object']['id'];
            unset($response);
            return $categoryId;
        } else {
            $this->log("Ошибка создания категории каталога {$categoryName}: " . print_r($response->getResponse(), 1), 0, 1);
            unset($response);
            return false;
        }
    }

    /**
     * Update miniShop2 category
     *
     * @param $parentId
     * @param $categoryId
     * @param $categoryName
     */
    protected function updateMsCategory($parentId, $categoryId, $categoryName)
    {
        $this->clearModxErrors();

        $processorProps = array(
            'id' => $categoryId
            , 'class_key' => 'msCategory'
            , 'pagetitle' => $categoryName
            , 'context_key' => $this->config['catalog_context']
        );
        if ($parentId) $processorProps['parent'] = $parentId;
        $response = $this->modx->runProcessor('mgr/extend/updatemscategory', $processorProps, array('processors_path' => $this->config['processorsPath']));
        unset($processorProps);
        if (!$response->isError()) {
            $_SESSION['importResources']['category']['updated'][] = $categoryId;
        }
        unset($response);
    }

    /**
     * Импортирование свойств товаров из XML файла
     * @param $filename
     * @return string
     */
    protected function importProperties($filename)
    {
        $totalProperties = 0;
        $lastProperty = $this->options['lastProperty'];
        if ($lastProperty < 0) $lastProperty = 0;

        //read xml file
        $reader = $this->getXmlReader($filename, 'Свойства');

        if ($reader->name != 'Свойства') {
            $this->log("В файле товаров Свойства не найдены.");
            $reader->close();
            $_SESSION['lastProperty'] = 0;
            return 'progress';
        }

        $xml = $this->readXml($reader);
        if (isset($xml->Свойство)) {
            $totalProperties = count($xml->Свойство);


            foreach ($xml->Свойство as $xml_property) {
                if ($this->importProperty($xml_property)) {
                    $lastProperty++;
                }
            }
        }
        $reader->close();

        $this->log("Вызвано событие mSyncAfterImportProperties", 1);
        $this->modx->invokeEvent('mSyncAfterImportProperties', array(
            'xml' => $xml,
            'last' => $lastProperty,
            'total' => $totalProperties,
        ));

        $this->options['totalProperties'] = $_SESSION['totalProperties'] = $totalProperties;
        $this->options['lastProperty'] = $_SESSION['lastProperty'] = $lastProperty;

        $msg = 'Импортированы типы свойств: ' . $lastProperty;
        $this->log($msg);
        return 'progress' . PHP_EOL . $msg . PHP_EOL;
    }

    /**
     * Импорт свойства
     * @param SimpleXMLElement $xml_property
     * @return bool True, если свойство успешно сохранено
     */
    protected function importProperty($xml_property)
    {
        if (!isset($xml_property->Ид)) return false;

        $propertyId = $this->stringXml($xml_property->Ид);

        $property = array();

        if (isset($xml_property->Наименование)) $property['Наименование'] = $this->stringXml($xml_property->Наименование);

        $this->log("Импортирование свойства Ид={$propertyId} Наименование={$property['Наименование']}", 1);
        if (isset($xml_property->ВариантыЗначений) && !empty($xml_property->ВариантыЗначений)) {
            $property['Значения'] = array();
            $array = (array)$xml_property->ВариантыЗначений;
            $xml_property_values = is_array($array['Справочник']) ? $array['Справочник'] : array($array['Справочник']);
            foreach ($xml_property_values as $xml_property_val) {
                $property['Значения'][$this->stringXml($xml_property_val->ИдЗначения)] = $this->stringXml($xml_property_val->Значение);
            }
            $this->log("Получены значения свойства: " . print_r($property['Значения'], 1), 1);
        }


        if (empty($property)) return false;

        $_SESSION['properties_mapping'][$propertyId] = $property;

        return true;
    }

    /**
     * Import products to temp table
     *
     * @param $filename
     * @return string
     */
    protected function importProducts($filename)
    {
        // Последний товар, на котором остановились
        $lastImportProduct = intval($this->options['lastImportProduct']);
        $firstProduct = $lastImportProduct + 1;
        $this->log("Начат импорт товаров, начиная с номера " . $firstProduct, 1);

        if ($lastImportProduct == 0) {
            $this->modx->exec("TRUNCATE TABLE {$this->modx->getTableName('mSyncProductTemp')}");
            $this->log("Очищена временная таблица товаров в БД", 1);
        }

        $reader = $this->getXmlReader($filename, 'Товар');

        // Номер текущего товара
        $currentImportProduct = 0;

        $prodSql = array();
        while ($reader->name === 'Товар') {
            if ($currentImportProduct++ < $lastImportProduct) {
                $reader->next('Товар');
                continue;
            }

            $xml = $this->readXml($reader);
            $data = $this->importProduct($xml);

            $this->log("Вызвано событие mSyncOnBeforeImportProduct", 1);
            $response = $this->msync->invokeEvent('mSyncOnBeforeImportProduct', array(
                'xml' => $xml,
                'data' => $data
            ));

            $data = $response['data']['data'];
            unset($response);

            if (!isset($data['properties'])) {
                $data['properties'] = $this->jsonXml($data['characteristics']['properties']);
            }
            if (!isset($data['features'])) {
                $data['features'] = $this->jsonXml($data['characteristics']['features']);
            }

            $prodSql[] = "('{$data['name']}', '{$data['article']}', '{$data['manufacturer']}', '{$data['images']}', '{$data['bar_code']}', '{$data['description']}',
                    '{$data['features']}', '{$data['properties']}', '{$data['uuid']}', '{$data['parent_uuid']}', '{$data['status']}')";
            $this->log("Товар импортирован: " . print_r($data, 1), 1);


            $count = count($prodSql);
            $isTimeEnd = $this->checkExecTime();

            if ($count == 200 || $isTimeEnd) {
                $this->executeProdSql($prodSql);
                $this->log("Товары с {$firstProduct} по {$currentImportProduct} записаны в базу", 1);
                $firstProduct = $_SESSION['lastImportProduct'] + 1;
                $prodSql = array();
            }

            $lastImportProduct = $_SESSION['lastImportProduct'] = $currentImportProduct;

            if ($isTimeEnd) break;
            $reader->next('Товар');
        }
        $reader->close();

        // Сохраняем оставшиеся товары если есть
        if (count($prodSql) > 0) {
            $this->executeProdSql($prodSql);
            $this->log("Товары c {$firstProduct} по {$currentImportProduct} записаны в базу", 1);
        }

        if (!$isTimeEnd) {
            $totalProducts = $this->getTotalProducts();
            $this->options['totalProducts'] = $_SESSION['totalProducts'] = $totalProducts;
            if ($totalProducts == 0) {
                $this->log('Товары не найдены. Импорт завершен.', 1);
                $_SESSION['importFinish'] = 1;
            }

            $this->log("Вызвано событие mSyncAfterImportProducts", 1);
            $this->modx->invokeEvent('mSyncAfterImportProducts', array(
                'total' => $totalProducts,
                'last' => $lastImportProduct
            ));
        }

        $msg = 'Выгружено товаров во временную базу ' . $lastImportProduct;
        $this->log($msg);
        return 'progress' . PHP_EOL . $msg . PHP_EOL;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return array Временные данные товара
     */
    protected function importProduct($xml)
    {
        $prod = array();

        $prod['name'] = $this->stringXml($xml->Наименование);
        $prod['description'] = $this->stringXml($xml->Описание);

        //standart properties
        $prod['article'] = $this->stringXml($xml->Артикул);
        $prod['manufacturer'] = trim($this->stringXml($xml->Изготовитель));
        $prod['manufacturer'] = empty($prod['manufacturer']) && isset($xml->Изготовитель)
            ? trim($this->stringXml($xml->Изготовитель->Наименование))
            : $prod['manufacturer'];
        $prod['bar_code'] = $this->stringXml($xml->Штрихкод);

        //additional properties
        $prod['properties'] = array();
        if (isset($xml->ЗначенияСвойств)) {
            foreach ($xml->ЗначенияСвойств->ЗначенияСвойства as $xml_property) {
                $this->addComplexProperty($xml_property, $prod['properties']);
            }
        }

        if (isset($xml->СписокСвойствОписания)) {
            foreach ($xml->СписокСвойствОписания->СвойствоОписания as $xml_property) {
                $this->AddProperty($xml_property, $prod['properties']);
            }
        }

        if (isset($xml->ЗначенияРеквизитов)) {
            foreach ($xml->ЗначенияРеквизитов->ЗначениеРеквизита as $xml_property) {
                $this->AddProperty($xml_property, $prod['properties'], array('ВидНоменклатуры', 'ТипНоменклатуры'));
            }
        }

        if (isset($xml->ХарактеристикиТовара)) {
            foreach ($xml->ХарактеристикиТовара->ХарактеристикаТовара as $xml_property) {
                $this->AddProperty($xml_property, $prod['properties']);
            }
        }

        $array = (array)$xml;
        if (!isset($array["Картинка"])) {
            $array["Картинка"] = array();
        }
        if (!isset($array["ХарактеристикиТовара"])) {
            $array["ХарактеристикиТовара"] = array();
        }

        $prod['characteristics'] = array(
            'properties' => $prod['properties'],
            'features' => $array["ХарактеристикиТовара"]
        );
        $prod['properties'] = $this->jsonXml($prod['properties']);
        $prod['images'] = $this->jsonXml($array["Картинка"]);
        $prod['features'] = $this->jsonXml($array["ХарактеристикиТовара"]);


        $prod['uuid'] = $this->stringXml($xml->Ид);
        $prod['parent_uuid'] = $this->config['no_categories'] ? 0 : $this->stringXml($xml->Группы->Ид);
        $prod['status'] = $this->stringXml($xml->Статус);
        if (empty($prod['status']) && isset($xml->attributes()->Статус)) {
            $prod['status'] = $this->stringXml($xml->attributes()->Статус);
        }

        return $prod;
    }

    /**
     * Вставка товаров во временную таблицу
     * @param array $prodSql
     */
    protected function executeProdSql($prodSql)
    {
        $sql = "INSERT " . "INTO " . $this->modx->getTableName('mSyncProductTemp') .
            " (`name`, `article`, `manufacturer`, `images`, `bar_code`, `description`, `features`, `properties`, `uuid`, `parent_uuid`, `status`) VALUES
						   " . implode(',', $prodSql) . ";";

        $stmt = $this->modx->prepare($sql);
        $stmt->execute();
    }

    /**
     * Добавление свойства к товару
     * @param SimpleXMLElement $xml_property
     * @param array $prod_properties Массив свойств
     * @param array $no_need Список свойств, которые не нужно сохранять
     */
    protected function AddProperty($xml_property, &$prod_properties, $no_need = array())
    {
        $propertyName = $this->stringXml($xml_property->Наименование);
        $propertyVal = $this->stringXml($xml_property->Значение);
        if (in_array($propertyName, $no_need)) return;
        if (!isset($prod_properties[$propertyName])) $prod_properties[$propertyName] = $propertyVal;
    }

    protected function AddComplexProperty($xml_property, &$prod_properties)
    {
        $propertyId = $this->stringXml($xml_property->Ид);
        $propertyData = $_SESSION['properties_mapping'][$propertyId];
        $propertyName = $propertyData['Наименование'];
        $propertyVal = $this->stringXml($xml_property->Значение);
        if (isset($propertyData['Значения'])) $propertyVal = $propertyData['Значения'][$propertyVal];
        if (isset($prod_properties[$propertyName])) {
            $prod_properties[$propertyName] .= ',' . $propertyVal;
        } else {
            $prod_properties[$propertyName] = $propertyVal;
        }
    }

    /**
     * Get total num products in temp table
     * @return int
     */
    protected function getTotalProducts()
    {
        return $this->modx->getCount('mSyncProductTemp');
    }

    /**
     * Prepare products
     *
     * @return string
     */
    protected function prepareProducts()
    {
        $lastProduct = $this->options['lastProduct'];
        $totalProducts = $this->options['totalProducts'];
        $firstProduct = $lastProduct + 1;
        $this->log("Начата подготовка товаров, начиная с {$firstProduct} из {$totalProducts}", 1);

        $this->loadProperties();

        $productsData = $this->getProductTempData($this->config['temp_count'], $lastProduct);
        $this->log("Получено товаров из временной таблицы: " . count($productsData), 1);

        foreach ($productsData as $productData) {
            $this->prepareProduct($productData);
            $_SESSION['lastProduct'] = ++$lastProduct;
            if ($this->checkExecTime()) break;
        }

        $msg = 'Импортировано товаров ' . $lastProduct . ' из ' . $totalProducts;
        $this->log($msg);
        return 'progress' . PHP_EOL . $msg . PHP_EOL;
    }

    private function logMemory($msg = "") {
        $this->log("Memory ($msg): " . round(memory_get_usage()/1024) . "kB peak: " . round(memory_get_peak_usage()/1024) . 'kB');
    }

    /**
     * Возвращает временные данные товаров
     * @param int $limit
     * @param int $offset
     * @return array
     */
    protected function getProductTempData($limit, $offset)
    {
        $q = $this->modx->newQuery('mSyncProductTemp');
        $q->select($this->modx->getSelectColumns('mSyncProductTemp', 'mSyncProductTemp', ''));
        $q->sortby('mSyncProductTemp.id', 'ASC');
        $q->limit($limit, $offset);

        if ($q->prepare() && $q->stmt->execute()) {
            $productsData = $q->stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $productsData = array();
        }

        return $productsData;
    }

    /**
     * Подгружает соответствия свойств из БД
     */
    protected function loadProperties()
    {
        if (!empty($this->properties)) return;

        $q = $this->modx->newQuery('mSyncProductProperty', array('active' => 1));
        $q->select($this->modx->getSelectColumns('mSyncProductProperty', '', '',
            $this->propertyFields));
        if ($q->prepare() && $q->stmt->execute()) {
            $properties = $q->stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($properties as $val) $this->properties[$val['source']] = $val;
        }

        $this->log("Соответствия свойств подгружены: " . print_r($this->properties, 1), true);
    }

    /**
     * Из всех свойств отбирает ключевые
     * @param array $productData
     * @return array
     */
    protected function findPrimaryProperties($productData)
    {
        $primaryProperties = array();
        foreach ($this->properties as $source => $value) {
            if (!$value['is_primary'])  continue;

            $property = $value['target'];
            $prefix = '';
            if (in_array($property, array_keys($this->modx->getFields('msProductData')))) {
                $prefix = 'msProductData.';
            }
            if (in_array($property, array_keys($this->modx->getFields('msProduct')))) {
                $prefix = 'msProduct.';
            }
            if (isset($productData[$property])) {
                $primaryProperties[$prefix.$property] = $productData[$property];
            } else if (isset($productData['properties'][$property])) {
                $primaryProperties[$prefix.$property] = $productData['properties'][$property];
            }
        }
        return $primaryProperties;
    }

    /**
     * Возвращает идентификатор ресурса-категории по UUID
     * @param string $uuid
     * @return bool|int
     */
    protected function getCategoryId($uuid)
    {
        // По умолчанию - root
        $categoryId = $this->config['catalog_root_id'];

        if (isset($_SESSION['categories_mapping'][$uuid]))
            // если есть в сессии, берем оттуда
            $categoryId = $_SESSION['categories_mapping'][$uuid];
        else {
            // если нет, достаем из базы и запоминаем в сессию
            if (($categoryDataId = $this->getCategoryDataId($uuid)) !== false) {
                $_SESSION['categories_mapping'][$uuid] = $categoryId = $categoryDataId;
            }
        }

        return $categoryId;
    }

    /**
     * Возвращает из БД идентификатор ресурса-категории по UUID
     * @param $parentUuid
     * @return bool|int
     */
    protected function getCategoryDataId($parentUuid)
    {
        $q = $this->modx->newQuery('mSyncCategoryData', array('uuid_1c' => $parentUuid));
        $q->select('category_id');
        if ($this->modx->getCount('mSyncCategoryData', $q)) {
            if ($q->prepare() && $q->stmt->execute()) {
                return $q->stmt->fetch(PDO::FETCH_COLUMN);
            }
        }

        return false;
    }

    /**
     * Добавляет значение свойства в нужную группу, если оно есть
     * @param $data
     * @param $tv
     * @param $propertyName
     * @param $value
     * @return bool
     */
    protected function addProductData(&$data, &$tv, $propertyName, $value, $isStandard = false)
    {
        if (isset($this->properties[$propertyName])) {
            if ($this->properties[$propertyName]['is_multiple']) {
                $value = $this->propertyToArray($value);
            }

            $target = $this->properties[$propertyName]['target'];
            if (empty($target)) return false;
            if ($this->properties[$propertyName]['type'] == 1) {
                $data[$target] = $value;
                $resourceFields = array_merge(
                    array_keys($this->modx->getFields('msProduct')),
                    array_keys($this->modx->getFields('msProductData'))
                );

                if (!$isStandard && !in_array($target, $resourceFields)) {
                    $data['options-'.$target] = $value;
                }
            } elseif ($this->properties[$propertyName]['type'] == 2) {
                $tv[$target] = $value;
            }

            return true;
        }

        return false;
    }

    /**
     * Возвращает соотношение свойств по источнику
     * @param $source
     * @return null|mSyncProductProperty
     */
    protected function getProductPropertyBySource($source)
    {
        return $this->modx->getObject('mSyncProductProperty', array('source' => $source));
    }

    /**
     * Активирует соотношение свойств
     * @param mSyncProductProperty $property
     */
    protected function activateMsyncProperty($property)
    {
        $property->set('active', 1);
        $property->save();
        $this->properties[$property->get('source')] = $property->get($this->propertyFields);
    }

    /**
     * Создает новую текстовую TV по имени свойства
     * @param string $propertyName
     */
    protected function createTvProperty($propertyName)
    {
        $tv = $this->modx->newObject('modTemplateVar');
        $tv->fromArray(array(
                'name' => $propertyName,
                'caption' => $propertyName,
                'description' => '',
                'type' => 'text',
                'rank' => 10,
            )
        );
        $tv->save();

        if (!empty($this->config['product_template'])) {
            $tvTpl = $this->modx->newObject('modTemplateVarTemplate');
            $tvTpl->set('tmplvarid', $tv->get('id'));
            $tvTpl->set('templateid', $this->config['product_template']);
            $tvTpl->save();
        }
    }

    /**
     * Создает новое соотношение свойств в складе и минишопе
     * @param $propertyName
     */
    protected function createMsyncProperty($propertyName)
    {
        $propertyObjectArray = array(
            'source' => $propertyName,
            'type' => 2,
            'target' => $propertyName,
            'active' => 1,
            'default' => 0,
        );

        $propertyObject = $this->modx->newObject('mSyncProductProperty');
        $propertyObject->fromArray($propertyObjectArray);
        $propertyObject->save();

        $this->properties[$propertyName] = $propertyObject->get($this->propertyFields);
    }

    /**
     * Подготавливает свойства и разбивает их по категориям минишопа и ТВ
     * @param array $productData
     * @return array
     */
    protected function prepareProperties($productData)
    {
        $productAddData = array();
        $productAddTv = array();
        //prepare standard properties
        $productAddData['pagetitle'] = $productData['name'];
        $productAddData['uuid'] = $productData['uuid'];
        if (!empty($productData['description'])) $productAddData['content'] = $productData['description'];
        $this->addProductData($productAddData, $productAddTv, 'Изготовитель', $productData['manufacturer'], true);
        $this->addProductData($productAddData, $productAddTv, 'Артикул', $productData['article'], true);

        //prepare other properties
        $productProperties = json_decode($productData['properties'], 1);
        $productProperties['Ид'] = $productData['uuid'];
        foreach ($productProperties as $propertyName => $propertyValue) {
            $propertyExist = $this->addProductData($productAddData, $productAddTv, $propertyName, stripslashes($propertyValue));
            if ($propertyExist || !$this->config['create_properties_tv']) continue;

            $property = $this->getProductPropertyBySource($propertyName);
            if ($property) {
                $this->activateMsyncProperty($property);
                $this->addProductData($productAddData, $productAddTv, $propertyName, stripslashes($propertyValue));
            } else {
                $this->createTvProperty($propertyName);
                $this->createMsyncProperty($propertyName);
                $productAddTv[$propertyName] = $propertyValue;
            }
        }

        // create/update vendor
        if (isset($productAddData['vendor'])) {
            $productAddData['vendor'] = $this->updateMsProductVendor($productAddData['vendor']);
        }

        return array('data' => $productAddData, 'tv' => $productAddTv);
    }

    /**
     * Возвращает из БД идентификатор ресурса-товара по UUID
     * @param array $properties
     * @return bool|int
     */
    protected function getProductDataId($properties)
    {
        $productData = $properties['data'];
        $q = $this->modx->newQuery('mSyncProductData', array('uuid_1c' => $productData['uuid']));
        $q->select('product_id');
        if (!$this->config['only_offers'] && $this->modx->getCount('mSyncProductData', $q)) {
            if ($q->prepare() && $q->stmt->execute()) {
                return $q->stmt->fetch(PDO::FETCH_COLUMN);
            }
        } else {
            $this->log('Начат поиск ключевых параметров товара по данным: ' . print_r($productData, 1), 1);
            $primaryProperties = $this->findPrimaryProperties($productData);
            $this->log('Найдены ключевые параметры товара: ' . print_r($primaryProperties, 1), 1);
            if (count($primaryProperties) > 0) {
                $q = $this->modx->newQuery('msProduct');
                $q->leftJoin('msProductData', 'msProductData', 'msProduct.id=msProductData.id');
                $q->where($primaryProperties);
                $q->limit(1);
                $pr = $this->modx->getIterator('msProduct', $q);
                $pr->rewind();
                $product = $pr->current();
                if ($product) {
                    $productId = $product->get('id');
                    $this->log('Найден товар по ключевым параметрам с id=' . $productId, 1);
                    $this->createProductData($productId, $productData['uuid']);
                    return $productId;
                }
            }
        }

        return false;
    }

    /**
     * Prepare data to create or update miniShop2 product
     *
     * @param $productData
     */
    protected function prepareProduct($productData)
    {
        $this->log("Начато обновление товара по временным данным " . print_r($productData, 1), 1);
        $categoryId = $this->getCategoryId($productData['parent_uuid']);
        $this->clearModxErrors();
        $properties = $this->prepareProperties($productData);
        $this->log("Подготовлены свойства товара: " . print_r($properties, 1), 1);

        // Ищем товар
        $productMode = 'create';
        $productId = $this->getProductDataId($properties);

        $this->log("Вызвано событие mSyncOnPrepareProduct для товара с uuid={$productData['uuid']}", 1);
        $response = $this->msync->invokeEvent('mSyncOnPrepareProduct', array(
            'data' => $productData,
            'productId' => $productId,
            'parent' => $categoryId,
            'properties' => $properties,
        ));

        $productData = $response['data']['data'];
        $productId = $response['data']['productId'];
        $categoryId = $response['data']['parent'];
        $properties = $response['data']['properties'];
        $productAddData = $properties['data'];
        $productAddTv = $properties['tv'];
        unset($response);

        if ($productId) {
            $productMode = 'update';
            $success = $this->updateMsProduct($categoryId, $productId, $productAddData);
        } else {
            $productId = $this->createMsProduct($categoryId, $productAddData);
            $success = ($productId > 0);
            if ($productId) {
                $this->createProductData($productId, $productData['uuid']);
            }
        }

        if (!$success) {
            $this->log("Ошибка обновления товара uuid='{$productData['uuid']}'", 0, 1);
            return;
        }

        $this->uploadImagesMsProduct($productId, $productData['name'], $productData['images']);

        $product = $this->getProduct($productId);

        if (!$product) {
            $this->log("Не удалось получить ресурс с идентификатором {$productId}", 0, 1);
            return;
        }

        $this->saveProductProperty($product, 'Ид', $productData['uuid']);
        $this->setProductTvs($product, $productAddTv);
        $this->saveAllPropertiesToTv($product, $productData['properties']);
        $this->updateProductStatus($product, $productData['status'] == 'Удален');

        $this->log("Вызвано событие mSyncOnProductImport({$productMode}) для товара с id={$productId}", 1);
        $this->modx->invokeEvent('mSyncOnProductImport', array(
            'mode' => $productMode,
            'resource' => &$product,
            'properties' => json_decode($productData['properties'], 1),
            'data' => $productData,
        ));
        unset($categoryId, $properties, $productId, $productData, $productAddData, $productAddTv, $product, $productMode);
    }

    /**
     * Сохранение всех свойств в ТВ, если установлена такая настройка
     * @param modResource $product
     * @param array $properties
     * @return bool
     */
    protected function saveAllPropertiesToTv($product, $properties)
    {
        $tvName = $this->config['save_properties_to_tv'];
        if (empty($tvName) || empty($properties)) return false;
        $product->setTVValue($tvName, stripslashes($properties));
        return true;
    }

    /**
     * Если нужно - удаляем вариант или весь товар
     * @param modResource $product
     * @param bool $status
     */
    protected function updateProductStatus($product, $status)
    {
        $deleted = $product->get('deleted');
        if ($status && !$deleted) {
            $product->set('deleted', 1);
            $product->set('deletedby', $this->config['user_id_import']);
            $product->set('deletedon', time());
            $product->save();
            $this->log("Товар с идентификатором {$product->get('id')} помечен удаленным.", 1);
        } else if (!$status && $deleted) {
            $product->set('deleted', 0);
            $product->save();
            $this->log("Товар с идентификатором {$product->get('id')} восстановлен из корзины.", 1);
        }
    }

    /**
     * Возвращает товар минишопа
     * @param int $productId
     * @return null|modResource
     */
    protected function getProduct($productId)
    {
        return $this->modx->getObject('msProduct', array(
            'class_key' => 'msProduct',
            'id' => $productId
        ));
    }

    /**
     * Создает и возвращает привязку идентификатора товара в минишопе и на складе
     * @param int $productId
     * @param string $uuid
     * @return null|mSyncProductData
     */
    protected function createProductData($productId, $uuid)
    {
        $newProdData = $this->modx->newObject('mSyncProductData');
        $newProdData->set('product_id', $productId);
        $newProdData->set('uuid_1c', $uuid);
        $newProdData->save();

        return $newProdData;
    }

    /**
     * Считывает типы цен из XML
     * @param $filename
     */
    protected function readPriceTypes($filename)
    {
        $reader = $this->getXmlReader($filename, 'ТипыЦен');
        $xml = $this->readXml($reader);
        if ($xml) {
            foreach ($xml->ТипЦены as $priceType) {
                $priceId = $this->stringXml($priceType->Ид);
                if (isset($priceType->Наименование)) {
                    $_SESSION['price_mapping'][$priceId] = $this->stringXml($priceType->Наименование);
                }
            }
        }

        $reader->close();

        $this->log("Считано типов цен: " . count($_SESSION['price_mapping']));
        $this->log("Типы цен: " . print_r($_SESSION['price_mapping'], 1), 1);
    }

    protected function finish() {
        $this->log("Вызвано событие mSyncAfterImport", 1);
        $this->modx->invokeEvent('mSyncAfterImport', array(
            'totalProducts' => $this->options['totalProducts'],
            'totalCategories' => $this->options['totalCategories'],
            'importResources' => $_SESSION['importResources'],
        ));

        $this->clearCache();
        $out = 'success' . PHP_EOL . ' Выгружено категорий: ' . $this->options['totalCategories'] . PHP_EOL .
            ' Выгружено товаров: ' . $this->options['totalProducts'];
        $this->log($out);
        $this->log("Идентификаторы категорий и товаров в этом импорте: " . print_r($_SESSION['importResources'], 1), 1);
        return $out;
    }

    /**
     * Импорт торговых предложений из XML файла
     * @param string $filename
     * @return string
     */
    protected function doOffers($filename)
    {
        $this->log("Вызвано событие mSyncOnBeforeImport(offers)", 1);
        $this->modx->invokeEvent('mSyncOnBeforeImport', array(
            'mode' => 'offers',
            'filename' => $filename
        ));

        $last_offer = isset($_SESSION['last_1c_offer']) ? $_SESSION['last_1c_offer'] : 0;

        if ($last_offer == 0) {
            $this->readPriceTypes($filename);
        }

        $totalOffers = $this->countOffers($filename);

        $offers = $this->getXmlReader($filename, 'Предложение');
        $this->loadProperties();

        $this->setUpMsOptionsPrice();

        $this_offer_num = 0;

        while ($offers->name === 'Предложение') {
            if ($this_offer_num >= $last_offer) {
                $xml = $this->readXml($offers);

                //Остатки и цены
                $this->loadStock($xml);
                $_SESSION['last_1c_offer'] = $this_offer_num;

                if ($this->checkExecTime()) {
                    $msg = 'Выгружено ценовых предложений:' . $this_offer_num . ' из ' . $totalOffers;
                    $this->log($msg, 1);
                    return 'progress' . PHP_EOL . $msg . PHP_EOL;
                }
            }
            $offers->next('Предложение');
            $this_offer_num++;
        }
        $offers->close();

        $this->log("Вызвано событие mSyncAfterOffers", 1);
        $this->modx->invokeEvent('mSyncAfterOffers', array(
            'totalOffers' => $this_offer_num,
        ));

        $msg = 'Выгружено ценовых предложений: ' . $this_offer_num;
        $this->log($msg);
        unset($_SESSION['mSyncLogFile']);
        return 'success' . PHP_EOL . $msg . PHP_EOL;
    }

    /**
     * Считает общее кол-во предложений
     * @param string $filename
     * @return string
     */
    protected function countOffers($filename) {
        $offers = $this->getXmlReader($filename, 'Предложения');
        $xml = $this->readXml($offers);
        return $xml ? $xml->count() : 0;
    }

    /**
     * Настройка связи с компонентом msOptionsPrice
     */
    protected function setUpMsOptionsPrice()
    {
        $this->msOptionsPriceSource = false;
        $active = $this->modx->getOption('msoptionsprice_ms_op_active', null, false);
        if (!$active) return;
        $this->msOptionsPriceTarget = $this->modx->getOption('msoptionsprice_ms_op_options', null, false);
        if (!$this->msOptionsPriceTarget) return;

        foreach ($this->properties as $propertyArray) {
            $key = array_search($this->msOptionsPriceTarget, $propertyArray);
            if ($key === false) continue;

            $this->msOptionsPriceSource = $propertyArray['source'];
            break;
        }

        $this->log("Настроена связь с компонентом msOptionsPrice (source={$this->msOptionsPriceSource}, target={$this->msOptionsPriceSource})", 1);
    }

    /**
     * Create new miniShop2 product
     *
     * @param $categoryId
     * @param array $productAddData
     * @return bool|mixed
     */
    protected function createMsProduct($categoryId, $productAddData)
    {
        $this->clearModxErrors();

        $processorProps = array_merge(array(
            'class_key' => 'msProduct'
        , 'parent' => $categoryId
        , 'published' => $this->config['publish_default']
        , 'context_key' => $this->config['catalog_context']
        , 'source' => $this->config['product_source']
        , 'template' => $this->config['product_template']
        ), $productAddData);
        $response = $this->modx->runProcessor('mgr/extend/createmsproduct', $processorProps, array('processors_path' => $this->config['processorsPath']));
        unset($processorProps);
        if (!$response->isError()) {
            $_SESSION['importResources']['product']['created'][] = $productId = $response->response['object']['id'];
            unset($response);
            return $productId;
        } else {
            $this->log('Ошибка создания товара (' . $productAddData['pagetitle'] . '), импорт остановлен' . "\r\n" . print_r($response->getResponse(), 1), 0, 1);
            unset($response);
            return false;
        }
    }

    /**
     * Update miniShop2 product
     *
     * @param int $categoryId
     * @param int $productId
     * @param array $productAddData
     * @return bool|mixed
     */
    protected function updateMsProduct($categoryId, $productId, $productAddData)
    {
        $this->clearModxErrors();

        $processorProps = array_merge(array(
            'id' => $productId,
            'context_key' => $this->config['catalog_context'],
            'source' => $this->config['product_source']
        ), $productAddData);
        if (!empty($categoryId)) $processorProps['parent'] = $categoryId;

        $response = $this->modx->runProcessor('mgr/extend/updatemsproduct', $processorProps,
            array('processors_path' => $this->config['processorsPath']));
        unset($processorProps);
        if (!$response->isError()) {
            $_SESSION['importResources']['product']['updated'][] = $productId;
            unset($response);
            return true;
        } else {
            $this->log('Ошибка обновления товара (' . $productAddData['pagetitle'] . ' ' . $productId . '), импорт остановлен' .
                "\r\n" . print_r($response->getResponse(), 1), 0, 1);
            unset($response);
            return false;
        }
    }

    /**
     * Upload images to miniShop2 product
     *
     * @param $productId
     * @param $productName
     * @param $images
     */
    protected function uploadImagesMsProduct($productId, $productName, $images)
    {
        if (empty($images)) return;

        $this->log("Загрузка изображений товара ({$productName}, {$productId}):\r\n{$images}", 1);
        $images = (array)json_decode($images, true);

        foreach ($images as $image) {
            $this->clearModxErrors();

            //$image = basename($image);
            if (file_exists($this->config['temp_dir'] . $image)) {
                $file = array('id' => $productId, 'name' => $image, 'file' => $this->config['temp_dir'] . $image);

                $response = $this->modx->runProcessor('mgr/gallery/upload', $file, array('processors_path' => MODX_CORE_PATH . 'components/minishop2/processors/'));
                // не очень красиво, вероятно стоит проверять хеш
                if ($response->isError() && $response->getMessage() != $this->modx->lexicon('ms2_err_gallery_exists')) {
                    $this->log('Ошибка загрузки изображения товара (' . $productName . ' ' . $productId . "): \r\n" . print_r($response->getMessage(), 1), 0, 1);
                }
                unset($response);
            } else {
                $this->log('Ошибка загрузки изображения товара (' . $productName . ' ' . $productId . '), файл (' . $this->config['temp_dir'] . $image . ') не найден', 0, 1);
            }
            // Reset processor errors
            $this->modx->error->reset();
        }
    }

    /**
     * Add additional properties to miniShop2 product
     *
     * @param modResource $product
     * @param array $tvsArray
     * @return bool
     */
    protected function setProductTvs($product, $tvsArray = array())
    {
        if (empty($tvsArray)) return false;

        foreach ($tvsArray as $k => $v) {
            $product->setTVValue($k, $v);
        }

        return true;
    }

    /**
     * Return msVendor id
     *
     * @param string $vendorName
     * @return int
     */
    protected function updateMsProductVendor($vendorName = '')
    {
        $vendorId = 0;
        if (!$vendorName = trim($vendorName)) return $vendorId;

        if ($vendor = $this->modx->getObject('msVendor', array('name' => $vendorName))) {
            $vendorId = $vendor->get('id');
        } else {
            $vendor = $this->modx->newObject('msVendor');
            $vendor->fromArray(array(
                'name' => $vendorName
            ), '', true);
            $vendor->save();
            $vendorId = $vendor->get('id');
        }

        return $vendorId;
    }

    /**
     * fix param to array
     *
     * @param $param
     * @return array|string
     */
    protected function propertyToArray($param)
    {
        if (!trim($param)) return '';
        $paramArray = array_map('trim', explode(',', $param));
        return $paramArray;
    }

    /**
     * Возвращает привязку товара
     * @param $uuid
     * @return null|mSyncProductData
     */
    protected function getProductData($uuid)
    {
        return $this->modx->getObject('mSyncProductData', array('uuid_1c' => $uuid));
    }

    /**
     * Обновление остатков товара
     * @param $xml
     * @param modResource $product
     * @param $productFeatureExist
     */
    protected function updateQuantity($xml, $product, $productFeatureExist)
    {
        if (!isset($this->properties['Количество'])) {
            $this->log("Не настроено поле Количество для выгрузки остатков.", 1);
            return;
        }

        $quantity = floatval($xml->Количество);
        $quantityVal = $quantity;

        $feature = $this->getFeature($xml);
        if ($feature && $productFeatureExist) {
            if ($productFeatureExist) {
                $target = $this->properties['Количество']['target'];
                $type = $this->properties['Количество']['type'];
                $oldQuantity = $type == 1 ? $product->get($target) : $product->getTVValue($target);
                $oldQuantity = empty($oldQuantity) ? array() : (array)explode('||', $oldQuantity);
                $newQuantity = array_unique(array_merge($oldQuantity, array($feature . '==' . $quantity)));
                $quantity = $this->calcComplexQuantity($newQuantity);
                $quantityVal = implode('||', $newQuantity);
            } else {
                $quantityVal = $feature . '==' . $quantity;
            }
        }

        $this->saveProductProperty($product, 'Количество', $quantityVal);
        $this->publishByQuantity($product, $quantity);
        $this->hidemenuByQuantity($product, $quantity);
    }

    /**
     * Расчет остатков
     * @param $newQuantity
     * @return bool|int
     */
    protected function calcComplexQuantity($newQuantity)
    {
        if (!$this->config['publish_by_quantity']) return false;
        $quantity = 0;
        foreach ($newQuantity as $q) {
            $c = explode('==', $q);
            $quantity += $c[1];
        }
        return $quantity;
    }

    /**
     * Публикация товара в зависимости от остатков, если настроено
     * @param modResource $product
     * @param mixed $quantity
     */
    protected function publishByQuantity($product, $quantity)
    {
        if (!$this->config['publish_by_quantity'] || $quantity === false) return;

        $published = $product->get('published');
        $shouldBePublished = ($quantity > 0);
        if (($published && !$shouldBePublished) || (!$published && $shouldBePublished)) {
            $product->set('published', $shouldBePublished);
            $product->save();

            $msg = $shouldBePublished ? 'опубликован' : 'снят с публикации';
            $this->log("Товар {$msg} по остаткам: {$quantity} шт.", 1);
        }
    }

    /**
     * Добавление/удаление из меню товара в зависимости от остатков, если настроено
     * @param modResource $product
     * @param mixed $quantity
     */
    protected function hidemenuByQuantity($product, $quantity)
    {
        if (!$this->config['hidemenu_by_quantity'] || $quantity === false) return;

        $hideMenu = $product->get('hidemenu');
        $shouldBePublished = ($quantity > 0);
        if (($hideMenu && $shouldBePublished) || (!$hideMenu && !$shouldBePublished)) {
            $product->set('hidemenu', !$shouldBePublished);
            $product->save();

            $msg = $shouldBePublished ? 'добавлен в меню' : 'убран из меню';
            $this->log("Товар {$msg} по остаткам: {$quantity} шт.", 1);
        }
    }

    /**
     * @param $productId
     * @return bool
     */
    protected function isProductFeatureExist($productId)
    {
        $productFeatureExist = true;
        if (!in_array($productId, $_SESSION['feature_mapping'])) {
            $productFeatureExist = false;
            array_push($_SESSION['feature_mapping'], $productId);
        }

        return $productFeatureExist;
    }

    /**
     * Импортирование всех цен согласно связям
     * @param $xml
     * @param $product
     * @return string Первая цена
     */
    protected function importAllPrices($xml, $product)
    {
        $selectedPrice = 0;
        $c = 0;
        if (count($xml->Цены) == 0) return 0;

        foreach ($xml->Цены->Цена as $price) {
            $priceTypeId = $this->stringXml($price->ИдТипаЦены);
            $priceTypeName = isset($_SESSION['price_mapping'][$priceTypeId]) ? $_SESSION['price_mapping'][$priceTypeId] : '';

            if (empty($priceTypeName)) {
                $this->log("Тип цены {$priceTypeId} не найден.", 0, 1);
                continue;
            }

            if (!isset($this->properties[$priceTypeName])) {
                $this->log("Не настроена связь для типа цены {$priceTypeName}.", 0, 1);
                if (!$this->config['create_prices_tv']) continue;
                $this->createPriceTv($priceTypeName);
            }

            $priceSum = $this->stringXml($price->ЦенаЗаЕдиницу);
            if ($c == 0) $selectedPrice = $priceSum;

            $this->saveProductProperty($product, $priceTypeName, $priceSum);

            ++$c;
        }

        return $selectedPrice;
    }

    /**
     * Создание TV под цену
     * @param string $priceTypeName
     */
    protected function createPriceTv($priceTypeName) {
        $this->createTvProperty($priceTypeName);
        $this->createMsyncProperty($priceTypeName);
        $this->log("Создана новая TV {$priceTypeName} для соответствующей цены.", 1);
    }

    /**
     * @param modResource $product
     * @param string $propertyName
     * @param mixed $value
     * @param bool $productFeatureExist
     */
    protected function saveProductProperty($product, $propertyName, $value, $productFeatureExist = false)
    {
        if (!isset($this->properties[$propertyName])) return; // соответствие свойства не установлено
        $target = $this->properties[$propertyName]['target'];
        $type = $this->properties[$propertyName]['type'];

        $logMsg = "Свойство {$propertyName} ({$target}) товара {$product->get('pagetitle')} ({$product->get('id')}) обновлено значением {$value}.";

        if ($type == 1 && !$productFeatureExist) {
            $optionKeys = $product->loadData()->getOptionKeys();
            if (in_array($target, $optionKeys)) {
                $options = $product->get('options');
                $options[$target] = $value;
                $product->set('options', $options);
            } else {
                $product->set($target, $value);
            }

            $product->save();
            $this->log($logMsg, 1);
        }

        if ($type == 2) {
            $product->setTVValue($target, $value);
            $this->log("{$logMsg} Значение записано в TV {$target}.", 1);
        }
    }

    /**
     * Импортируем только первую цену
     * @param $xml
     * @param $product
     * @param $productFeatureExist
     * @return mixed
     */
    protected function importFirstPrice($xml, $product, $productFeatureExist)
    {

        if (!isset($this->properties['Цена'])) {
            $this->log("Не настроена связь Цена товара", 0, 1);
            return false;
        }

        $price = count($xml->Цены) > 0 ? $this->xmlReader->stringXml($xml->Цены->Цена[0]->ЦенаЗаЕдиницу) : 0;

        if (empty($price)) {
            $this->log("Торговое предложение не содержит цену за единицу (id={$product->get('id')})", 0, 1);
            return false;
        }

        $this->saveProductProperty($product, 'Цена', $price, $productFeatureExist);

        return $price;
    }

    /**
     * Возвращает характеристику
     * @param $xml
     * @return string
     */
    protected function getFeature($xml)
    {
        $feature = $this->stringXml($xml->ХарактеристикиТовара->ХарактеристикаТовара->Значение);
        if (!$feature) {
            $feature = $this->stringXml($xml->Характеристика);
        }

        return $feature;
    }

    /**
     * Сохранение цен с учетом характеристики
     * @param $product modResource
     * @param $feature string Характеристика
     * @param $productFeatureExist
     * @param $selectedPrice
     */
    protected function updatePriceByFeature($product, $feature, $productFeatureExist, $selectedPrice)
    {
        if (empty($feature) || empty($this->config['price_by_feature_tv'])) return;


        if ($productFeatureExist) {
            $oldFeaturePrice = $product->getTVValue($this->config['price_by_feature_tv']);
            $oldFeaturePrice = (array)explode('||', $oldFeaturePrice);

            $product->setTVValue($this->config['price_by_feature_tv'], implode('||', array_merge(array($feature . '==' . $selectedPrice), $oldFeaturePrice)));
        } else {
            $product->setTVValue($this->config['price_by_feature_tv'], $feature . '==' . $selectedPrice);
        }

    }

    /**
     * Сохранение цен
     * @param $xml
     * @param modResource $product
     * @param $productFeatureExist
     */
    protected function updatePrice($xml, $product, $productFeatureExist)
    {
        $selectedPrice = $this->config['import_all_prices']
            ? $this->importAllPrices($xml, $product)
            : $this->importFirstPrice($xml, $product, $productFeatureExist);

        $feature = $this->getFeature($xml);

        $this->updatePriceByFeature($product, $feature, $productFeatureExist, $selectedPrice);

        $this->updateMsOptionsPrice($product, $feature, $productFeatureExist, $selectedPrice);
    }

    /**
     * Обработка одного торгового предложения
     * @param $xml
     */
    protected function loadStock($xml)
    {
        $uuid_offer = (string)$xml->Ид;
        $uuid_1c = (array)explode('#', $uuid_offer);
        $uuid = $uuid_1c[0];

        $this->log("Вызвано событие mSyncBeforeProductOffers для предложения с uuid={$uuid_offer}", 1);
        $response = $this->msync->invokeEvent('mSyncBeforeProductOffers', array(
            'uuid' => $uuid,
            'uuid_offer' => $uuid_offer,
            'xml' => $xml,
        ));
        $uuid = $response['data']['uuid'];
        unset($response);

        if (!$uuid) return; // пропускаем предложение

        if ($this->config['only_offers']) {
            $productData = array(
                'uuid' => $uuid,
                'name' => (string)$xml->Наименование,
                'article' => (string)$xml->Артикул,
            );
            $properties = $this->prepareProperties($productData);
            $product_id = $this->getProductDataId($properties);
        } else {
            $prodData = $this->getProductData($uuid);
            if (!$prodData) {
                $this->log("Привязка к товару для торгового предложения с uuid={$uuid} не найдена.", 0, 1);
                return;
            }

            $product_id = $prodData->get('product_id');
        }

        $product = $this->getProduct($product_id);
        if (!$product) {
            $this->log("Товар с идентификатором {$product_id} для торгового предложения с uuid={$uuid} не найден.", 0, 1);
            return;
        }

        /** @var mSyncOfferData $offerMaker */
        $offerMaker = $this->modx->newObject('mSyncOfferData');
        $offer = $offerMaker->saveOffer($this->xmlReader, $xml, $uuid_offer, $product->get('id'), $this->properties);

        $productFeatureExist = $this->isProductFeatureExist($product_id);
        $this->updateQuantity($xml, $product, $productFeatureExist);
        $this->updatePrice($xml, $product, $productFeatureExist);

        $this->log("Вызвано событие mSyncOnProductOffers для товара с id={$product_id}", 1);
        $this->modx->invokeEvent('mSyncOnProductOffers', array(
            'resource' => &$product,
            'offer' => &$offer,
            'xml' => $xml,
        ));
    }

    /**
     * Импорт цены в msOptionsPrice
     * @param modResource $product
     * @param $feature
     * @param $productFeatureExist
     * @param $selectedPrice
     */
    protected function updateMsOptionsPrice($product, $feature, $productFeatureExist, $selectedPrice)
    {
        if (empty($feature) || !$this->msOptionsPriceTarget || !$this->msOptionsPriceSource) return;
        $source = $this->properties[$this->msOptionsPriceSource];
        if (!isset($source)) return;

        if ($this->msOptionsPriceTarget == 'size') {
            $price = $productFeatureExist
                ? array_unique(array_merge($product->get('size'), $this->propertyToArray($feature)))
                : $this->propertyToArray($feature);
        } else {
            $price = $feature;
        }

        $this->saveProductProperty($product, $this->msOptionsPriceSource, $price);

        //add price to product $selectedPrice
        $product->setProperty($feature, $selectedPrice, 'msoptionsprice');
        $product->save();
        $this->log("Цена сохранена в msOptionsPrice (feature={$feature}, price={$price}, selectedPrice={$selectedPrice})", 1);
    }

    /**
     * Проверка настройки каталога для выгрузки
     * @return bool
     */
    protected function checkCatalogRoot()
    {
        $catalog_root_id = $this->config['catalog_root_id'];
        if ($catalog_root_id === -1) {
            $this->log('Ошибка импорта каталога, не настроен параметр "Id категории каталога" (msync_catalog_root_id)', 0, 1);
            return false;
        }
        $this->log("Id каталога = {$catalog_root_id}", 1);
        return true;
    }

    /**
     * Проверка настройки каталога для выгрузки
     * @return bool
     */
    protected function checkUser()
    {
        if (!$this->modx->user) {
            $this->log("Ошибка импорта каталога, не найден пользователь, от имени которого будет производиться импорт
                (msync_user_id_import={$this->config['user_id_import']})", 0, 1);
            return false;
        }
        $this->log("Id пользователя = {$this->config['user_id_import']}", 1);
        return true;
    }

    /**
     * Очистка кеша MODX
     * @return bool
     */
    public function clearCache()
    {
        $this->modx->cacheManager->refresh();
        return true;
    }

    protected function initLog() {
        if (!isset($_SESSION['mSyncLogFile'])) {
            $_SESSION['mSyncLogFile'] = 'import_' . date('y-m-d_His');
        }
    }

    /**
     * @param string $string Строка лога
     * @param bool|false $isDebug True, если данные только для дебага
     * @param bool $modxLogError True, если надо записать в лог ошибок MODX
     */
    protected function log($string, $isDebug = false, $modxLogError = false)
    {
        $this->msync->logFile($_SESSION['mSyncLogFile'], $string, $isDebug, $modxLogError);
    }

}