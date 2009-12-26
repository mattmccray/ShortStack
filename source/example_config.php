<?php

// =======================================
// = Shortstack (MVC) Framework Settings =
// =======================================
$shortstack_config = array(
  'db' => array(
    'engine'   => 'sqlite', // Only one supported as yet
    'database' => 'app/data/db.sqlite3',
    'autoconnect' => true,
    'verify' => true,
  ),
  'models' => array(
    'folder' => 'app/models',
  ),
  'views' => array(
    'folder' => 'app/views',
    'force_short_tags'=>false,
  ),
  'controllers' => array(
    'folder' => 'app/controllers',
    'index' => 'home',
    '404_handler'=>'home',
  ),
  'helpers' => array(
    'folder' => 'app/helpers',
    'autoload'=> array('link', 'navigation'),
  ),
  'cacheing' => array(
    'folder' => 'caches',
    'enabled' => true,
    'expires' => 60*60, // In Seconds (60*60 == 1h)
  ),
);