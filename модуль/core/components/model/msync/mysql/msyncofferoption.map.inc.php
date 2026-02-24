<?php
$xpdo_meta_map['mSyncOfferOption']= array (
  'package' => 'msync',
  'version' => '1.1',
  'table' => 'msync_offer_options',
  'extends' => 'xPDOSimpleObject',
  'tableMeta' => 
  array (
    'engine' => 'MyISAM',
  ),
  'fields' => 
  array (
    'offer_id' => 0,
    'option' => '',
    'value' => '',
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
    'option' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
    ),
    'value' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
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
