<?php

// =======================================
// = Shortstack (MVC) Framework Settings =
// =======================================
$shortstack_config = array(
  'db' => array(
    'engine'   => 'sqlite', // Only one supported as yet
    'database' => 'test-db.sqlite3',
    'autoconnect' => true,
    'verify' => true,
  ),
  'models' => array(
    'folder' => 'comicus/app/models',
  ),
  'views' => array(
    'folder' => 'comicus/app/views',
    'force_short_tags'=>false,
  ),
  'controllers' => array(
    'folder' => 'comicus/app/controllers',
    '404_handler'=>'home',
  ),
  'helpers' => array(
    'folder' => 'comicus/app/helpers',
    'autoload'=> array('link', 'navigation'),
  ),
);