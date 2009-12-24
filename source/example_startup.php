<?php

define("SYSROOT", dirname(__FILE__));

$config = array();
include_once('config.php');

// You should probably add your timezone to the config too
date_default_timezone_set($config['site']['timezone']);

$shortstack_config = $config['shortstack'];

try {
  include_once('lib/shortstack.php');
} catch (EmptyDbException $e) {
  if(@ strpos('install', (string)$_SERVER['PATH_INFO']) == -1 )
    Dispatcher::recognize('install');
}

 
if(! Dispatcher::$dispatched ) {
  Dispatcher::recognize();
}