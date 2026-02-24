<?php

class mSyncXmlReader
{

    /* @var mSync $msync */
    private $msync;

    function __construct(&$msync)
    {
        $this->msync =& $msync;

    }

    /**
     * Возвращает XML Reader
     * @param $filename
     * @param $search
     * @return XMLReader
     */
    public function getXmlReader($filename, $search)
    {
        //read xml file
        $reader = new XMLReader;
        $success = $reader->open($filename);
        if (!$success) {
            $this->msync->logFile(null, "Невозможно считать файл {$filename}. Возможно он содержит ошибки XML.", 0, 1);
        }

        //search categories
        while ($reader->read() && $reader->name !== $search) ;
        return $reader;
    }

    /**
     * Читает XML
     * @param XMLReader $reader
     * @return SimpleXMLElement
     */
    public function readXml($reader)
    {
        $outerXml = $reader->readOuterXML();
        return $outerXml ? new SimpleXMLElement($outerXml) : null;
    }

    /**
     * Возвращает обработанную строку из значения узла XML
     * @param SimpleXMLElement|SimpleXMLElement[] $value
     * @return string
     */
    public function stringXml($value)
    {
        return isset($value) ? addslashes((string)$value) : '';
    }

    /**
     *  Возвращает JSON строку из массива
     * @param array|object $array
     * @return string
     */
    public function jsonXml($array)
    {
        return addslashes($this->msync->utf_json_encode((array)$array));
    }

}