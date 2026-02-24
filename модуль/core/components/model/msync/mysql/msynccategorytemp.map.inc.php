<?php
$xpdo_meta_map['mSyncCategoryTemp']= array (
  'package' => 'msync',
  'version' => '1.1',
  'table' => 'msync_categories_temp',
  'extends' => 'xPDOSimpleObject',
  'tableMeta' => 
  array (
    'engine' => 'MyISAM',
  ),
  'fields' => 
  array (
    'name' => NULL,
    'uuid' => '',
    'parent_uuid' => '',
    'level' => NULL,
  ),
  'fieldMeta' => 
  array (
    'name' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
    ),
    'uuid' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '74',
      'phptype' => 'string',
      'null' => true,
      'default' => '',
    ),
    'parent_uuid' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '74',
      'phptype' => 'string',
      'null' => true,
      'default' => '',
    ),
    'level' => 
    array (
      'dbtype' => 'int',
      'precision' => '4',
      'attributes' => 'unsigned',
      'phptype' => 'integer',
      'null' => true,
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
        'uuid' => 
        array (
          'length' => '',
          'collation' => 'A',
          'null' => false,
        ),
      ),
    ),
  ),
);
