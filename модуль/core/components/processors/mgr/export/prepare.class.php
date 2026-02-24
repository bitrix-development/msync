<?php

class mSyncExportPrepareProcessor extends modObjectProcessor
{

    private $start, $total, $name, $limit;
    private $products = array();
    public $fields = array();
    public $languageTopics = array('msync:export');


    public function initialize()
    {
        $this->config = $this->modx->msync->config;
        $this->start = intval($this->getProperty('start'));
        $this->total = intval($this->getProperty('total'));
        $this->limit = $this->getProperty('limit', 500);
        $this->name = $this->getProperty('name');
        if (trim($this->name) == '') {
            $this->name = date('d-m-Y_H-i');
        }

        $q = $this->modx->newQuery('msProduct', array('class_key' => 'msProduct', 'deleted' => 0));
        $this->total = $this->modx->getCount('msProduct', $q);

        $q->limit($this->limit, $this->start);

        $this->products = $this->modx->getCollection('msProduct', $q);
        return true;
    }

    public function process()
    {
        if (count($this->products) == 0) {
            return $this->failure($this->modx->lexicon('msync_export_err_no_product'));
        }

        $stop = 0;

        $filename = $this->config['assetsPath'] . 'export/ms2_products_' . $this->name . '.csv';
        setlocale(LC_ALL, "ru_RU");

        if ($this->start == 0) {

            $targetDir = $this->config['assetsPath'] . 'export';
            if (is_dir($targetDir) && ($dir = opendir($targetDir))) {
                while (($file = readdir($dir)) !== false) {
                    $tmpfilePath = $targetDir . '/' . $file;
                    @unlink($tmpfilePath);
                }
                closedir($dir);
            }
        }

        $this->fields = array(
            "Группы" => ''
        , "Код" => ''
        , "Наименование" => ''
        , "Внешний код" => ''
        , "Артикул" => ''
        , "Единица измерения" => ''
        , "Цена продажи" => ''
        , "Валюта (Цена продажи)" => ''
        , "Закупочная цена" => ''
        , "Валюта (Закупочная цена)" => ''
        , "Неснижаемый остаток" => ''
        , "Штрихкод EAN13" => ''
        , "Штрихкод EAN8" => ''
        , "Штрихкод Code128" => ''
        , "Описание" => ''
        , "Минимальная цена" => ''
        , "Страна" => ''
        , "НДС" => ''
        , "Поставщик" => ''
        , "Архивный" => ''
        , "Вес" => ''
        , "Объем" => ''
        );

        $strings = array();
        foreach ($this->products as $product) {

            $this->fields["Группы"] = implode('/', array_reverse($this->getParents($product->get('parent'))));
            $this->fields["Код"] = $product->get('id');
            $this->fields["Наименование"] = $product->get('pagetitle');
            $this->fields["Внешний код"] = $product->get('id');
            $this->fields["Артикул"] = $product->get('article');
            $this->fields["Единица измерения"] = 'шт';
            $this->fields["Цена продажи"] = $product->get('price');
            $this->fields["Валюта (Цена продажи)"] = 'руб';
            $this->fields["Описание"] = $product->get('introtext');
            $this->fields["Страна"] = $product->get('made_in');
            $this->fields["Архивный"] = 'нет';
            $this->fields["Вес"] = intval($product->get('weight'));
            $this->fields["Объем"] = 0;

            $response = $this->modx->msync->invokeEvent('mSyncOnCsvExport', array(
                'fields' => &$this->fields,
                'product' => &$product,
                'filename' => $filename,
                'start' => $this->start,
                'export' => &$this
            ));

            $this->fields = $response['data']['fields'];

            $strings[] = $this->fields;
        }

        $this->saveHead($filename);

        $csv = fopen($filename, 'a');
        foreach ($strings as $fields) {
            fputcsv($csv, $fields, ';');
        }
        fclose($csv);

        if ($this->start + $this->limit > $this->total) {
            $stop = 1;
            $csv = file_get_contents($filename);
            $csv = mb_convert_encoding($csv, 'windows-1251', 'UTF-8');
            file_put_contents($filename, $csv);
        }
        return $this->success(array('total' => $this->total, 'stop' => $stop, 'name' => $this->name));
    }

    private function saveHead($filename) {
        if ($this->start == 0) {

            $targetDir = $this->config['assetsPath'] . 'export';
            if (is_dir($targetDir) && ($dir = opendir($targetDir))) {
                while (($file = readdir($dir)) !== false) {
                    $tmpfilePath = $targetDir . '/' . $file;
                    @unlink($tmpfilePath);
                }
                closedir($dir);
            }


            $head = array_keys($this->fields);
            $csv = fopen($filename, 'c');
            fputcsv($csv, $head, ';');
            fclose($csv);
        }
    }

    public function getParents($parentId, $parent_group = array())
    {

        if ($parentId == 0) return $parent_group;

        $q = $this->modx->newQuery('modResource', array('id' => $parentId));
        $q->select('pagetitle, parent, class_key');

        if ($this->modx->getCount('modResource', $q)) {
            if ($q->prepare() && $q->stmt->execute()) {
                $parentRes = $q->stmt->fetch(PDO::FETCH_ASSOC);

                $parentClass = $parentRes['class_key'];

                if (!empty($parentClass) && $parentClass == 'msCategory') {
                    $parent_group[] = $parentRes['pagetitle'];
                    $parent_group += $this->getParents($parentRes['parent'], $parent_group);
                }
            }
        }

        return $parent_group;
    }


}

return 'mSyncExportPrepareProcessor';