<?php

class mSyncOfferData extends xPDOSimpleObject
{

    /**
     * @var mSyncXmlReader $xmlReader
     */
    private $xmlReader;
    /**
     * @var array $properties
     */
    private $properties;

    /**
     * @param mSyncXmlReader $xmlReader
     * @param SimpleXMLElement $xml
     * @param string $uuid_offer
     * @param integer $data_id
     * @param array $properties
     */
    public function saveOffer($xmlReader, $xml, $uuid_offer, $data_id, $properties)
    {
        $this->xmlReader = $xmlReader;
        $this->properties = $properties;

        /**
         * @var mSyncOfferData $offer
         */
        $offer = $this->xpdo->getObject('mSyncOfferData', array('uuid_1c' => $uuid_offer, 'data_id' => $data_id));
        if (!$offer) {
            $offer = $this->xpdo->newObject('mSyncOfferData', array(
                'data_id' => $data_id,
                'uuid_1c' => $uuid_offer,
            ));
        } else {
            /** @var xPDOSimpleObject $opt */
            $callback = function($opt) { return $opt->get('id'); };

            $options = $offer->getMany('Options');
            $options_ids = array_map($callback, $options);
            if (count($options_ids) > 0) {
                $this->xpdo->removeCollection('mSyncOfferOption', array('id:IN' => $options_ids));
            }


            $prices = $offer->getMany('Prices');
            $prices_ids = array_map($callback, $prices);
            if (count($prices_ids) > 0) {
                $this->xpdo->removeCollection('mSyncOfferPrice', array('id:IN' => $prices_ids));
            }
        }

        $features = $this->makeFeatures($xml);
        $prices = $this->makePrices($xml);

        $offer->set('article', $xmlReader->stringXml($xml->Артикул));
        $offer->set('name', $xmlReader->stringXml($xml->Наименование));
        $offer->set('base_unit', $this->makeBaseUnit($xml->БазоваяЕдиница));
        $offer->set('price', count($prices) > 0 ? $prices[0]->get('value') : 0);
        $offer->set('count', (float)$xmlReader->stringXml($xml->Количество));

        $offer->addMany($features, 'Options');
        $offer->addMany($prices, 'Prices');
        if ($offer->save()) return $offer;

        return false;
    }

    /**
     * @param SimpleXMLElement $baseUnit
     */
    protected function makeBaseUnit($baseUnit)
    {
        if (!$baseUnit) return '{}';
        $unit = (array) $baseUnit->attributes();

        $unit = $unit['@attributes'];
        $value = isset($baseUnit->Пересчет) ? $baseUnit->Пересчет->Единица: $baseUnit;
        $unit['Единица'] = $this->xmlReader->stringXml($value);
        $unit['Коэффициент'] = isset($baseUnit->Пересчет) ? (float)$this->xmlReader->stringXml($baseUnit->Пересчет->Коэффициент) : 1;
        return $this->xmlReader->jsonXml($unit);
    }

    /**
     * @param SimpleXMLElement $xml
     */
    protected function makeFeatures($xml)
    {
        $features = array();
        if ($xml->ХарактеристикиТовара) {
            foreach ($xml->ХарактеристикиТовара->ХарактеристикаТовара as $feature) {
                $featureName = $this->xmlReader->stringXml($feature->Наименование);
                $featureLink = $this->properties[$featureName];
                $option = isset($featureLink) ? $featureLink['target'] : $featureName;
                $features[] = $this->xpdo->newObject('mSyncOfferOption', array(
                    'option' => $option,
                    'value' => $this->xmlReader->stringXml($feature->Значение),
                ));
            }
        }
        return $features;
    }

    /**
     * @param SimpleXMLElement $xml
     */
    protected function makePrices($xml)
    {
        $prices = array();
        if ($xml->Цены) {
            foreach ($xml->Цены->Цена as $price) {
                $priceId = $this->xmlReader->stringXml($price->ИдТипаЦены);
                $priceName = isset($_SESSION['price_mapping'][$priceId]) ? $_SESSION['price_mapping'][$priceId] : '';
                $prices[] = $this->xpdo->newObject('mSyncOfferPrice', array(
                    'price_name' => $priceName,
                    'price_id' => $priceId,
                    'presentation' => $this->xmlReader->stringXml($price->Представление),
                    'value' => (float)$this->xmlReader->stringXml($price->ЦенаЗаЕдиницу),
                    'currency' => $this->xmlReader->stringXml($price->Валюта),
                    'unit' => $this->xmlReader->stringXml($price->Единица),
                    'factor' => (float)$this->xmlReader->stringXml($price->Коэффициент),
                ));
            }
        }
        return $prices;
    }

}