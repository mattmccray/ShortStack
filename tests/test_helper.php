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
//     Dispatcher::recognize('install');
// }
// 
//  
// if(! Dispatcher::$dispatched ) {
//   Dispatcher::recognize();
// }

// =======================================
// = Shortstack (MVC) Framework Settings =
// =======================================
$config['shortstack'] = array(
  'db' => array(
    'engine'   => 'sqlite', // Only one supported as yet
    'database' => 'test-db.sqlite3',
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
  ),
  'helpers' => array(
    'folder' => 'helpers',
    'autoload'=> array(),
  ),
);

$shortstack_config = $config['shortstack'];


try {
  include('../source/shortstack-dev.php');
} catch (EmptyDbException $e) {

  foreach(ShortStack::LoadAllModels() as $modelName) {
    $mdl = new $modelName;
    if($mdl instanceof Model) {
      $res = $mdl->_createTableForModel();
      if( $res->execute() == "" )
        echo $modelName ." Created\n";
      else
        echo $modelName ." Already exists\n";
    }
  }
  echo "Models loaded...\n";
  Document::InitalizeDatabase();
  echo "Documents loaded...\n";
}


