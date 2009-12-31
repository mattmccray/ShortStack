<?php

define("SYSROOT", dirname(__FILE__));
define("APPROOT", SYSROOT."/app");

if(strpos(__FILE__,'.com') === false) {
  define('DEPLOYEDTO','local');
} else {
  define('DEPLOYEDTO','remote');
}

if(DEPLOYEDTO=='local') {
  error_reporting(E_ALL);
  define('DEBUG', 1);
} else {
  define('DEBUG', 0);
}

$shortstack_config = array();

include_once('app/config.php'); // should look like the example_config.php

date_default_timezone_set('America/Chicago'); // Or your Timezone.

// Optional.
function use_lib($file) {
  if(! strpos($file, '.php') > 0) $file .= ".php";
  require_once( APPROOT."/lib/".$file );
}

try {
  $includePath = get_include_path();
  $includePath .= ":".SYSROOT.":".SYSROOT."/".$shortstack_config['views']['folder'];
  set_include_path($includePath);
  use_lib('shortstack');
}
catch (EmptyDbException $e) {
  $uri = (@$_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "";
  if(strpos($uri, 'install') < 1 ) {
    Dispatcher::Recognize('install');
  }
}
 
if(! Dispatcher::$dispatched ) {
  Dispatcher::Recognize();
}