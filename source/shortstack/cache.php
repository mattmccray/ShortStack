<?php

class Cache {
  
  public static function Exists($name) {
    if(!USECACHE || DEBUG) return false;
    else return file_exists( ShortStack::CachePath($name) );
  }

  public static function Get($name) {
    return file_get_contents( ShortStack::CachePath($name) );
  }

  public static function Save($name, $content) {
    return file_put_contents( ShortStack::CachePath($name), $content);
  }

  public static function Remove($name) {
    return @ unlink ( ShortStack::CachePath($name) );
  }

  public static function Clear() {
    $cache_files = glob( ShortStack::CachePath("*") );
    foreach($cache_files as $filename) {
      @unlink ( $filename );
    }
    return true;
  }

}