<?php
/**
 * @ignore
 */
global $shortstack_config;
if( isset($shortstack_config) ) {
  define('FORCESHORTTAGS', (@$shortstack_config['views']['force_short_tags'] == true) ? 1 : 0);
  define('USECACHE', (@$shortstack_config['cacheing']['enabled'] == true) ? 1 : 0);
  define('CACHELENGTH', @$shortstack_config['cacheing']['expires']);

  if(@ is_array($shortstack_config['helpers']['autoload']) ) {
    foreach($shortstack_config['helpers']['autoload'] as $helper) {
      require_once( ShortStack::HelperPath($helper."_helper"));
    }
  }
  if(@ $shortstack_config['db']['autoconnect'] ) {
    DB::Connect( $shortstack_config['db']['engine'].":".$shortstack_config['db']['database'] );
  }
  if(@ $shortstack_config['db']['verify'] ) {
    DB::EnsureNotEmpty();
  }
} else {
  throw new NotFoundException("ShortStack configuration missing!");
}