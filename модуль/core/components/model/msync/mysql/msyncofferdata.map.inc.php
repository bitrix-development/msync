<?php
$xpdo_meta_map['mSyncOfferData']= array (
  'package' => 'msync',
  'version' => '1.1',
  'table' => 'msync_offers',
  'extends' => 'xPDOSimpleObject',
  'tableMeta' => 
  array (
    'engine' => 'MyISAM',
  ),
  'fields' => 
  array (
    'data_id' => 0,
    'uuid_1c' => '',
    'article' => '',
    'barcode' => '',
    'name' => '',
    'base_unit' => NULL,
    'price' => 0.0,
    'count' => 0.0,
  ),
  'fieldMeta' => 
  array (
    'data_id' => 
    array (
      'dbtype' => 'integer',
      'attributes' => 'unsigned',
      'precision' => '10',
      'phptype' => 'integer',
      'null' => false,
      'default' => 0,
    ),
    'uuid_1c' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '74',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
    ),
    'article' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
    ),
    'barcode' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
    ),
    'name' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
    ),
    'base_unit' => 
    array (
      'dbtype' => 'text',
      'phptype' => 'string',
    ),
    'price' => 
    array (
      'dbtype' => 'decimal',
      'precision' => '12,2',
      'phptype' => 'float',
      'null' => false,
      'default' => 0.0,
    ),
    'count' => 
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
    'uuid' => 
    array (
      'alias' => 'uuid',
      'primary' => false,
      'unique' => false,
      'type' => 'BTREE',
      'columns' => 
      array (
        'uuid_1c' => 
        array (
          'length' => '',
          'collation' => 'A',
          'null' => false,
        ),
      ),
    ),
  ),
  'composites' => 
  array (
    'Options' => 
    array (
      'class' => 'mSyncOfferOption',
      'local' => 'id',
      'foreign' => 'offer_id',
      'cardinality' => 'many',
      'owner' => 'local',
    ),
    'Prices' => 
    array (
      'class' => 'mSyncOfferPrice',
      'local' => 'id',
      'foreign' => 'offer_id',
      'cardinality' => 'many',
      'owner' => 'local',
    ),
  ),
  'aggregates' => 
  array (
    'ProductData' => 
    array (
      'class' => 'mSyncProductData',
      'local' => 'data_id',
      'foreign' => 'product_id',
      'cardinality' => 'one',
      'owner' => 'foreign',
    ),
  ),
);
