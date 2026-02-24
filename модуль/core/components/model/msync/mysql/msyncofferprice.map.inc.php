<?php
$xpdo_meta_map['mSyncOfferPrice']= array (
  'package' => 'msync',
  'version' => '1.1',
  'table' => 'msync_offer_prices',
  'extends' => 'xPDOSimpleObject',
  'tableMeta' => 
  array (
    'engine' => 'MyISAM',
  ),
  'fields' => 
  array (
    'offer_id' => 0,
    'price_name' => '',
    'price_id' => '',
    'presentation' => '',
    'value' => 0.0,
    'currency' => '',
    'unit' => '',
    'factor' => 0.0,
  ),
  'fieldMeta' => 
  array (
    'offer_id' => 
    array (
      'dbtype' => 'integer',
      'attributes' => 'unsigned',
      'precision' => '10',
      'phptype' => 'integer',
      'null' => false,
      'default' => 0,
    ),
    'price_name' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
    ),
    'price_id' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
    ),
    'presentation' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
    ),
    'value' => 
    array (
      'dbtype' => 'decimal',
      'precision' => '12,2',
      'phptype' => 'string',
      'null' => false,
      'default' => 0.0,
    ),
    'currency' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
    ),
    'unit' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
    ),
    'factor' => 
    array (
      'dbtype' => 'decimal',
      'precision' => '12,2',
      'phptype' => 'float',
      'null' => false,
      'default' => 0.0,
    ),
  ),
  'indexes' => 
  array (
    'offer' => 
    array (
      'alias' => 'offer',
      'primary' => false,
      'unique' => false,
      'type' => 'BTREE',
      'columns' => 
      array (
        'offer_id' => 
        array (
          'length' => '',
          'collation' => 'A',
          'null' => false,
        ),
      ),
    ),
  ),
  'aggregates' => 
  array (
    'OfferData' => 
    array (
      'class' => 'mSyncOfferData',
      'local' => 'offer_id',
      'foreign' => 'id',
      'cardinality' => 'one',
      'owner' => 'foreign',
    ),
  ),
);
