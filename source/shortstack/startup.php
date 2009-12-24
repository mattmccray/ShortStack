<?php

if( isset($shortstack_config) ) {
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
  define('FORCESHORTTAGS', $shortstack_config['views']['force_short_tags']);
} else {
  throw new NotFoundException("ShortStack configuration missing!");
}