<?php

if(!isset($shortstack_config)) {
  $shortstack_config = array(
    'db' => array(
      'engine'   => 'sqlite', // Only one supported as yet
      'database' => 'database.sqlite3',
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
}

if( isset($shortstack_config) ) {
  define('FORCESHORTTAGS', @$shortstack_config['views']['force_short_tags']);
  if(@ is_array($shortstack_config['helpers']['autoload']) ) {
    foreach($shortstack_config['helpers']['autoload'] as $helper) {
      require_once( ShortStack::helperPath($helper."_helper"));
    }
  }
  if(@ $shortstack_config['db']['autoconnect'] ) {
    DB::connect( $shortstack_config['db']['engine'].":".$shortstack_config['db']['database'] ); 
  }
  if(@ $shortstack_config['db']['verify'] ) {
    DB::ensureNotEmpty();
  }
} else {
  throw new NotFoundException("ShortStack configuration missing!");
}