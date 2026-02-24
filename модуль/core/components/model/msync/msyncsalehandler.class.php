<?php

interface msyncSaleInterface
{

    /* Initializes order to context
     * Here you can load custom javascript or styles
     * @param string $ctx Context for initialization
     * @return boolean
     * */
    public function initialize($ctx = 'web');

    /* checkauth the catalog
     * @return array|string $response
     * */
    public function checkauth();

    /* init the catalog
     * @return array|string $response
     * */
    public function init();

    /* query the catalog
     * @return array|string $response
     * */
    public function query();

    /* success the catalog
     *
     * @return array|string $response
     * */
    public function success();

    /* import catalog
     *
     * @param string $filename The filename of catalog
     * @param string $file The file of catalog
     * @return array|string $response
     * */
    public function file($filename, $file);
}


class msyncSaleHandler implements msyncSaleInterface
{
    /* @var modX $modx */
    public $modx;
    protected $config;
    protected $sale, $orders;
    protected $properties;

    function __construct(mSync & $msync, array $config = array())
    {
        $this->msync = &$msync;
        $this->modx = &$msync->modx;

        $this->config = array_merge(array(
            'temp_dir' => $this->modx->getOption('msync_assets_path', $config, $this->modx->getOption('assets_path') . 'components/msync/1c_temp/'),
            'accept_status_id' => $this->modx->getOption('msync_order_accept_status_id'),
            'catalog_currency' => $this->modx->getOption('msync_catalog_currency'),
            'last_sync' => $this->modx->getOption('msync_last_orders_sync'),
            'delay_time' => $this->modx->getOption('msync_orders_delay_time', null, 30)
        ), $config);

        $this->sale = &$this->config['sale'];
        $this->modx->lexicon->load('msync:sale');

        if (empty($this->sale) || !is_array($this->sale)) {
            $this->sale = array();
        }
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
        return 'success' . PHP_EOL . session_name() . PHP_EOL . session_id();
    }

    /* @inheritdoc} */
    public function init()
    {
        $this->log("Начата выгрузка заказов: " . date('d-m-Y H:i:s'));

        unset($_SESSION['sale_order_ids']);
        $_SESSION['sale_order_ids'] = array();
        $_SESSION['msync_full_export'] = 1;
        return 'zip=no' . PHP_EOL . 'file_limit=1000000' . PHP_EOL;
    }

    /* @inheritdoc} */
    public function query()
    {
        $this->loadProperties();

        $no_spaces = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
        <КоммерческаяИнформация ВерсияСхемы="2.04" ДатаФормирования="' . date('Y-m-d') . '"></КоммерческаяИнформация>';

        $xml = new SimpleXMLElement ($no_spaces);

        $lastSync = $this->getLastSyncTime();
        $this->orders = $this->modx->getCollection('msOrder', array("`createdon` >= '{$lastSync}' OR `updatedon` >= '{$lastSync}'"));
        $this->log("Выбраны заказы, начиная с времени {$lastSync}: " .  count($this->orders));
        foreach ($this->orders as $order) {
            $this->prepareOrder($xml, $order);
        }

        $this->modx->invokeEvent('mSyncOnSalesExport', array(
            'xml' => &$xml,
            'orders' => $this->orders
        ));
        $this->log("Вызвано событие mSyncOnSalesExport", 1);

        $out = $xml->asXML();
        $out = mb_convert_encoding($out, 'cp1251', 'UTF-8');
        $out = str_replace('<?xml version="1.0" encoding="utf-8" standalone="yes"?>', '<?xml version="1.0" encoding="windows-1251" standalone="yes"?>', $out);

        if (!isset($_SESSION['msync_full_export'])) {
            $this->log("Все заказы обработаны");
            unset($_SESSION['mSyncLogFileOrders']);
        }
        return $out;
    }

    /* @inheritdoc} */
    public function success()
    {
        $this->orders = $this->modx->getCollection('msOrder', array('status' => 1));

        /** @var xPDOObject $order */
        foreach ($this->orders as $order) {
            if ($this->config['accept_status_id'] != '' && isset($_SESSION['sale_order_ids'][$order->get('id')])) {
                $orderId = $order->get('id');
                $status = $this->config['accept_status_id'];
                $order->set('status', $status);
                $order->save();

                $this->modx->invokeEvent('msOnChangeOrderStatus', array(
                    'order' => $order,
                    'status' => $status,
                ));
                $this->log("Заказу {$orderId} установлен статус {$status}", 1);
            }
        }
        $lastSync = $this->modx->getObject('modSystemSetting', 'msync_last_orders_sync');
        $lastSync->set('value', date("Y-m-d H:i:s"));
        $lastSync->save();
        $this->msync->clearCache();

        $this->log("Заказы обработаны: " . count($this->orders));
        unset($_SESSION['mSyncLogFileOrders']);
        return 'success' . PHP_EOL . session_name() . PHP_EOL . session_id() . PHP_EOL . date("Y-m-d H:i:s");
    }

    /* @inheritdoc} */
    public function file($filename = '', $file)
    {
        if ($filename) {
            $filename = basename($filename);

            $f = fopen($this->config['temp_dir'] . $filename, 'ab');
            fwrite($f, $file);
            fclose($f);
            return 'success' . PHP_EOL;
        }
        return 'failure' . PHP_EOL;
    }

    /**
     * Подгружает соответствия свойств из БД
     */
    protected function loadProperties()
    {
        if (!empty($this->properties)) return;

        $q = $this->modx->newQuery('mSyncProductProperty', array('active' => 1));
        $q->select($this->modx->getSelectColumns('mSyncProductProperty', '', '', array('id', 'source', 'type', 'target')));
        if ($q->prepare() && $q->stmt->execute()) {
            $properties = $q->stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($properties as $val) $this->properties[$val['source']] = $val;
        }

        $this->log("Соответствия свойств подгружены: " . print_r($this->properties, 1), true);
    }

    /**
     * Достает имя свойства по ключу в MODX
     * @param string $key
     * @return string
     */
    protected function getPropertyByOption($key) {
        $value = '';
        foreach ($this->properties as $name => $property) {
            if ($property['target'] == $key) {
                $value = $name;
                break;
            }
        }
        return $value;
    }

    /**
     * @param SimpleXMLElement $xml
     * @param xPDOObject $order
     */
    protected function prepareOrder(&$xml, $order)
    {
        $order_ext = $this->extendOrder($order);
        $order_date = explode(' ', $order_ext['createdon']);

        $this->log("Обрабатывается заказ ({$order_date}):\r\n" . print_r($order_ext, 1), 1);

        $doc = $xml->addChild("Документ");
        $this->addChildren($doc, array(
            "Ид" => $order_ext['id'],
            "Номер" => $order_ext['num'],
            "Дата" => $order_date[0],
            "ХозОперация" => "Заказ товара",
            "Роль" => "Продавец",
            "Курс" => "1",
            "Валюта" => $this->config['catalog_currency'],
            "Сумма" => $order_ext['cost'],
            "Время" => $order_date[1],
            "Комментарий" => htmlspecialchars($order_ext['address.comment']),
        ));

        // Контрагенты
        $agents = $doc->addChild('Контрагенты');
        $agent = $agents->addChild('Контрагент');

        $this->addChildren($agent, array(
            "Ид" => $order_ext['userId'],
            "Наименование" => htmlspecialchars($order_ext['user.fullname']),
            "Роль" => "Покупатель",
            "ПолноеНаименование" => htmlspecialchars($order_ext['user.fullname'])
        ));

        // Доп параметры
        $addr = $agent->addChild('АдресРегистрации');
        $this->addChildren($addr, array(
            'Вид' => 'Адрес доставки',
            'Представление' => $order_ext['address.full']
        ));

        $this->addTypeValue($addr, 'АдресноеПоле', 'Страна', !empty($order_ext['address.country']) ? $order_ext['address.country'] : 'RU');
        $this->addTypeValue($addr, 'АдресноеПоле', 'Почтовый индекс', $order_ext['address.index']);
        $this->addTypeValue($addr, 'АдресноеПоле', 'Регион', $order_ext['address.region']);
        $this->addTypeValue($addr, 'АдресноеПоле', 'Город', $order_ext['address.city']);
        $this->addTypeValue($addr, 'АдресноеПоле', 'Улица', $order_ext['address.street']);
        $this->addTypeValue($addr, 'АдресноеПоле', 'Дом', $order_ext['address.building']);
        $this->addTypeValue($addr, 'АдресноеПоле', 'Квартира', $order_ext['address.room']);

        $contacts = $agent->addChild('Контакты');
        $this->addTypeValue($contacts, 'Контакт', 'Телефон', $order_ext['address.phone']);
        $this->addTypeValue($contacts, 'Контакт', 'Почта', $order_ext['user.email']);

        //products
        $products = $doc->addChild('Товары');
        foreach ($order_ext['goods'] as $purchase) {
            $product = $products->addChild('Товар');
            $this->addChildren($product, array(
                "Ид" => $purchase['uuid_1c'],
                "ИдКаталога" => $purchase['parent_uuid_1c'],
                "Наименование" => htmlspecialchars($purchase['name']),
                "Артикул" => $purchase['article'],
                "Штрихкод" => $purchase['barcode'],
            ));

            $baseUnit = $product->addChild("БазоваяЕдиница", $purchase['base_unit']['Единица']);
            $baseUnit->addAttribute('Код', $purchase['base_unit']['Код']);
            $baseUnit->addAttribute('НаименованиеПолное', $purchase['base_unit']['НаименованиеПолное']);
            $baseUnit->addAttribute('МеждународноеСокращение', $purchase['base_unit']['МеждународноеСокращение']);

            $this->addChildren($product, array(
                "ЦенаЗаЕдиницу" => $purchase['price'],
                "Количество" => $purchase['count'],
                "Сумма" => $purchase['cost'],
            ));

            $details = array(
                "ВидНоменклатуры" => "Товар",
                "ТипНоменклатуры" => "Товар",
            );
            if (count($purchase['options']) > 0) {
                $features = $product->addChild('ХарактеристикиТовара');
                foreach ($purchase['options'] as $key => $value) {
                    $feature = $features->addChild('ХарактеристикаТовара');
                    $featureName = $this->getPropertyByOption($key);
                    $feature->addChild('Наименование', $featureName);
                    $feature->addChild('Значение', $value);
                    $details[$featureName] = $value;
                }
            }
            $this->addDetails($product, $details);
        }

        // Доставка @TODO cделать опциональным separate_delivery не существует
        if (!$order->separate_delivery) {
            $delivery = $products->addChild('Товар');
            $this->addChildren($delivery, array(
                "Ид" => 'ORDER_DELIVERY',
                "Наименование" => 'Доставка',
                "ЦенаЗаЕдиницу" => $order_ext['delivery_cost'],
                "Количество" => 1,
                "Сумма" => $order_ext['delivery_cost']
            ));

            $this->addDetails($delivery, array(
                "ВидНоменклатуры" => "Услуга",
                "ТипНоменклатуры" => "Услуга",
            ));
        }

        // Статус
        $this->addDetails($doc, array(
            "Статус заказа" => $order_ext['statusName'],
            "Способ оплаты" => $order_ext['payment.name'],
            "Способ доставки" => $order_ext['delivery.name'],
            "Адрес доставки" => $order_ext['address.full']
        ));

        if ($order_ext['status'] == 1) $_SESSION['sale_order_ids'][$order_ext['id']] = $order_ext['id'];
    }

    /**
     * Получить идентификатор предложения
     * @param int $productId
     * @param array $options
     * @return mSyncOfferData
     */
    protected function getOffer($productId, $options) {
        $query = $this->modx->newQuery('mSyncOfferData');
        foreach ($options as $key => $value) {
            $query->rightJoin('mSyncOfferOption', $key, array(
                "`mSyncOfferData`.`id`=`{$key}`.`offer_id`",
                "`{$key}`.`option`='{$key}'",
                "`{$key}`.`value`='{$value}'",
            ));
        }
        $query->where(array('mSyncOfferData.data_id' => $productId));
        /**
         * @var mSyncOfferData $offer
         */
        $offer = $this->modx->getObject('mSyncOfferData', $query);
        if ($offer) return $offer;
        return null;
    }

    /**
     * @param xPDOObject $order
     * @return array
     */
    protected function extendOrder($order)
    {
        $pls_order = $order->toArray();

        if ($pls_profile = $order->getOne('UserProfile')) {
            $pls_profile = $pls_profile->toArray('user.');
        } else $pls_profile = array();

        if ($pls_address = $order->getOne('Address')) {
            $pls_address = $pls_address->toArray('address.');
        } else $pls_address = array();

        if ($pls_delivery = $order->getOne('Delivery')) {
            $pls_delivery = $pls_delivery->toArray('delivery.');
        } else $pls_delivery = array();

        if ($pls_payment = $order->getOne('Payment')) {
            $pls_payment = $pls_payment->toArray('payment.');
        } else $pls_payment = array();

        $order_ext = array_merge($pls_order, $pls_profile, $pls_address, $pls_delivery, $pls_payment);
        if ($pls_status = $order->getOne('Status')) {
            $order_ext['statusName'] = $pls_status->get('name');
        }

        $products = $order->getMany('Products');
        foreach ($products as $product) {
            if ($data = $this->prepareProduct($product)) {
                $order_ext['goods'][] = $data;
            }
        }

        $order_ext['address.full'] = '';
        if (!empty($order_ext['address.index'])) $order_ext['address.full'] .= 'Индекс ' . htmlspecialchars($order_ext['address.index']);
        if (!empty($order_ext['address.region'])) $order_ext['address.full'] .= ', регион ' . htmlspecialchars($order_ext['address.region']);
        if (!empty($order_ext['address.city'])) $order_ext['address.full'] .= ', город ' . htmlspecialchars($order_ext['address.city']);
        if (!empty($order_ext['address.metro'])) $order_ext['address.full'] .= ', метро ' . htmlspecialchars($order_ext['address.metro']);
        if (!empty($order_ext['address.street'])) $order_ext['address.full'] .= ', улица ' . htmlspecialchars($order_ext['address.street']);
        if (!empty($order_ext['address.building'])) $order_ext['address.full'] .= ', дом ' . htmlspecialchars($order_ext['address.building']);
        if (!empty($order_ext['address.room'])) $order_ext['address.full'] .= ', кв ' . htmlspecialchars($order_ext['address.room']);
        if (!empty($order_ext['address.full'])) $order_ext['address.comment'] .= "\r\nАдрес доставки: " . $order_ext['address.full'];

        $order_ext['userId'] = !empty($order_ext['user.internalKey']) ? $order_ext['user.internalKey'] : '0#' . $order_ext['user.email'];

        $response = $this->msync->invokeEvent('mSyncOnBeforeSalesExport', array(
            'order_ext' => $order_ext,
            'order' => $order
        ));

        $order_ext = $response['data']['order_ext'];

        $this->log("Вызвано событие mSyncOnBeforeSalesExport для заказа " . $order->get('id'), 1);

        return $order_ext;
    }

    /**
     * @param modResource $product
     * @return bool|array
     */
    protected function prepareProduct($product)
    {
        $prod_ext = $product->getOne('Product');
        if (!$prod_ext) return false;
        $prodData = $this->modx->getObject('mSyncProductData', array('product_id' => $prod_ext->get('id')));
        $parentData = $this->modx->getObject('mSyncCategoryData', array('category_id' => $prod_ext->get('parent')));

        $data = $product->toArray();
        $offer = $this->getOffer($prod_ext->get('id'), $data['options']);
        $data['name'] = $prod_ext->get('pagetitle');
        $data['article'] = $prod_ext->get('article');
        $data['uuid_1c'] = $offer ? $offer->get('uuid_1c') : ($prodData ? $prodData->get('uuid_1c') : '');
        $data['parent_uuid_1c'] = $parentData ? $parentData->get('uuid_1c') : '';
        $data['base_unit'] = array('Единица' => 'шт');
        if ($offer) {
            $data['name'] = $offer->get('name');
            $data['article'] = $offer->get('article');

            $data['base_unit'] = json_decode(stripslashes($offer->get('base_unit')), true);
            $data['barcode'] = $offer->get('barcode');
        }

        if (!isset($data['options']['color'])) $data['options']['color'] = '';
        if (!isset($data['options']['size'])) $data['options']['size'] = '';

        return $data;
    }

    /**
     * @param SimpleXMLElement $xml
     * @param array $childData
     */
    protected function addChildren(&$xml, $childData)
    {
        foreach ($childData as $key => $value) {
            $xml->addChild($key, $value);
        }
    }

    /**
     * @param SimpleXMLElement $xml
     * @param string $name
     * @param string $type
     * @param string $value
     * @param bool|true $skipEmpty
     * @return bool|SimpleXMLElement
     */
    protected function addTypeValue(&$xml, $name, $type, $value, $skipEmpty = true)
    {
        if ($skipEmpty && empty($value)) return false;

        $field = $xml->addChild($name);
        $field->addChild('Тип', $type);
        $field->addChild('Значение', $value);

        return $field;
    }

    /**
     * @param SimpleXMLElement $xml
     * @param array $details
     */
    protected function addDetails(&$xml, $details)
    {
        $requisites = $xml->addChild("ЗначенияРеквизитов");
        foreach ($details as $name => $value) {
            $det = $requisites->addChild("ЗначениеРеквизита");
            $det->addChild("Наименование", $name);
            $det->addChild("Значение", $value);
        }
    }

    /**
     * @return bool|string
     */
    protected function getLastSyncTime() {
        return date("Y-m-d H:i:s", strtotime($this->config['last_sync']) - $this->config['delay_time']);
    }

    protected function initLog() {
        if (!isset($_SESSION['mSyncLogFileOrders'])) {
            $_SESSION['mSyncLogFileOrders'] = 'orders_' . date('y-m-d_His');
        }
    }

    /**
     * @param string $string Строка лога
     * @param bool|false $isDebug True, если данные только для дебага
     * @param bool $modxLogError True, если надо записать в лог ошибок MODX
     */
    protected function log($string, $isDebug = false, $modxLogError = false)
    {
        $this->msync->logFile($_SESSION['mSyncLogFileOrders'], $string, $isDebug, $modxLogError);
    }
}