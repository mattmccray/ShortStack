<?php

// Application Settings

$config['site'] = array(
  'timezone' => 'America/Chicago'
)


// =======================================
// = Shortstack (MVC) Framework Settings =
// =======================================
$config['shortstack'] = array(
  'db' => array(
    'engine'   => 'sqlite', // Only one supported as yet
    'database' => 'app/data/database.sqlite3',
    'autoconnect' => true,
    'verify' => true,
  ),
  'models' => array(
    'folder' => 'app/models',
  ),
  'views' => array(
    'folder' => 'app/views',
    'force_short_tags' => false,
  ),
  'controllers' => array(
    'folder' => 'app/controllers',
    '404_handler'=>'home',
  ),
  'helpers' => array(
    'folder' => 'app/helpers',
    'autoload'=> array(),
  ),
  'cacheing' => array(
    'folder' => 'app/views/_cache',
    'enabled' => true,
    'expires' => 60*60, // In Seconds (60*60 == 1h)
  ),
);