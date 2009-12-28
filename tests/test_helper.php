<?php

define("SYSROOT", dirname(__FILE__));

$config = array();
//include_once('config.php');

// You should probably add your timezone to the config too
date_default_timezone_set('America/Chicago');


// try {
//   include_once('lib/shortstack.php');
// } catch (EmptyDbException $e) {
//   if(@ strpos('install', (string)$_SERVER['PATH_INFO']) == -1 )
//     Dispatcher::Recognize('install');
// }
// 
//  
// if(! Dispatcher::$dispatched ) {
//   Dispatcher::Recognize();
// }

// =======================================
// = Shortstack (MVC) Framework Settings =
// =======================================
$config['shortstack'] = array(
  'db' => array(
    'engine'   => 'sqlite', // Only one supported as yet
    'database' => 'db.test.sqlite3',
    'autoconnect' => true,
    'verify' => true,
  ),
  'models' => array(
    'folder' => 'models',
  ),
  'views' => array(
    'folder' => 'views',
    'force_short_tags'=>false,
  ),
  'controllers' => array(
    'folder' => 'controllers',
    '404_handler'=>'home',
    'index' => 'home',
  ),
  'helpers' => array(
    'folder' => 'helpers',
    'autoload'=> array(),
  ),
  'cacheing' => array(
    'folder' => 'caches',
    'enabled' => true, // False?
  ),
);

$shortstack_config = $config['shortstack'];


try {
  include_once('../source/shortstack-dev.php');
} catch (EmptyDbException $e) {

  ShortStack::InitializeDatabase();
  echo "Models loaded...\n";
}

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED ); //& ~E_WARNING

require_once('simpletest/autorun.php');

