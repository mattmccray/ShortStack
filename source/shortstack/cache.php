<?php
/**
 * Implements view cacheing. It's primarily used from the Controller.
 * @see Controller
 */
class Cache {
  /**
   *  @return bool
   */
  public static function Exists($name) {
    if(!USECACHE || DEBUG) return false;
    else return file_exists( ShortStack::CachePath($name) );
  }
  /**
   *  @return string
   */
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
  /**
   *  @return bool
   */
  public static function Save($name, $content) {
    $cacheContent = time() ."\n". $content;
    return file_put_contents( ShortStack::CachePath($name), $cacheContent);
  }
  /**
   *  @return bool
   */
  public static function Expire($name) {
    return @ unlink ( ShortStack::CachePath($name) );
  }
  /**
   *  @return bool
   */
  public static function Clear() {
    $cache_files = glob( ShortStack::CachePath("*") );
    foreach($cache_files as $filename) {
      @unlink ( $filename );
    }
    return true;
  }
}