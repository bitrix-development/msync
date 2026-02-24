<?php
$xpdo_meta_map['mSyncProductData']= array (
  'package' => 'msync',
  'version' => '1.1',
  'table' => 'msync_products',
  'extends' => 'xPDOObject',
  'tableMeta' => 
  array (
    'engine' => 'MyISAM',
  ),
  'fields' => 
  array (
    'product_id' => 0,
    'uuid_1c' => '',
  ),
  'fieldMeta' => 
  array (
    'product_id' => 
    array (
      'dbtype' => 'integer',
      'attributes' => 'unsigned',
      'precision' => '10',
      'phptype' => 'integer',
      'null' => false,
      'default' => 0,
      'index' => 'pk',
    ),
    'uuid_1c' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '74',
      'phptype' => 'string',
      'null' => true,
      'default' => '',
    ),
  ),
  'indexes' => 
  array (
    'product' => 
    array (
      'alias' => 'product',
      'primary' => true,
      'unique' => true,
      'type' => 'BTREE',
      'columns' => 
      array (
        'product_id' => 
        array (
          'length' => '',
          'collation' => 'A',
          'null' => false,
        ),
      ),
    ),
    'uuid_1c' => 
    array (
      'alias' => 'uuid_1c',
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
    'Offers' => 
    array (
      'class' => 'mSyncOfferData',
      'local' => 'product_id',
      'foreign' => 'data_id',
      'cardinality' => 'many',
      'owner' => 'local',
    ),
  ),
  'aggregates' => 
  array (
    'Product' => 
    array (
      'class' => 'msProduct',
      'local' => 'id',
      'foreign' => 'id',
      'cardinality' => 'one',
      'owner' => 'foreign',
    ),
  ),
);
