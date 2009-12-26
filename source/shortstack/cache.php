<?php

class Cache {
  
  public static function Exists($name) {
    if(!USECACHE || DEBUG) return false;
    else return file_exists( ShortStack::CachePath($name) );
  }

  public static function Get($name) {
    $cacheContent = file_get_contents( ShortStack::CachePath($name) );
    $splitter = strpos($cacheContent, "\n"); 
    $contents = substr($cacheContent, $splitter+1, strlen($cacheContent) - $splitter);
    $timeSinseCached = time() - intVal(substr($cacheContent, 0, $splitter));;
    if($timeSinseCached > CACHELENGTH) {
      Cache::Expire($name);
      throw new StaleCache('Cache expired.');
    } else {
      return $contents;
    }
  }

  public static function Save($name, $content) {
    $cacheContent = time() ."\n". $content;
    return file_put_contents( ShortStack::CachePath($name), $cacheContent);
  }

  public static function Expire($name) {
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