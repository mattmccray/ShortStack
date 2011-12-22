<?php


/**
 * ShortStack
 *
 * Collects various, but global to the framework, methods.
 *
 */
class ShortStack {
  public static $Version = SHORTSTACK_VERSION;
  /**
   * Returns path information based on the $className, used by the autoloader.
   * @return string
   */
  public static function AutoLoadFinder($className) {
    if(strpos($className, 'ontroller') > 0) {
      return self::ControllerPath( underscore($className) );
    } else if(strpos($className, 'elper') > 0) {
        return self::HelperPath( underscore($className) );
    } else { // Model
      return self::ModelPath( underscore($className) );
    }
  }
  /**
   * Loads all models in the `models` folder and returns the model class names.
   *
   * @returns array
   * @see Model
   */
  public static function LoadAllModels() {
    $model_files = glob( self::ModelPath("*") );
    $classNames = array();
    foreach($model_files as $filename) {
      $path = explode('/', $filename);
      $className = array_slice($path, -1);
      $className = str_replace(".php", "", $className[0]);
      require_once($filename);
      $classNames[] = camelize($className);
    }
    return $classNames;
  }
  /**
   * Create all the tables needs for the models and documentmodels...
   * @return array list of all models loaded (classnames)
   */
  public static function InitializeDatabase() {
    $modelnames = ShortStack::LoadAllModels();
    foreach($modelnames as $modelName) {
      $mdl = new $modelName;
      if($mdl instanceof Model) {
        $mdl->createTableForModel();
      }
    }
    return $modelnames;
  }
  public static function IsDocument($className) {
    if(!array_key_exists($className, self::$doctypeCache)) {
      $mdl = new $className();
      self::$doctypeCache[$className] = ($mdl instanceof Document) ? true : false;
    }
    return self::$doctypeCache[$className];
  }
  /**#@+
    * File paths...
    * @return string
    */
  public static function ViewPath($path) {
    return self::GetPathFor('views', $path);
  }
  public static function ControllerPath($path) {
    return self::GetPathFor('controllers', $path);
  }
  public static function ModelPath($path) {
    return self::GetPathFor('models', $path);
  }
  public static function HelperPath($path) {
    return self::GetPathFor('helpers', $path);
  }
  public static function CachePath($path) {
    return self::GetPathFor('cacheing', $path, '.html');
  }
  protected static function GetPathFor($type, $path, $suffix=".php") {
    global $shortstack_config;
    return $shortstack_config[$type]['folder']."/".$path.$suffix;
  }
  public static $VERSION = "";
  /**#@-*/
  /**
   * @ignore
   */
  private static $doctypeCache = array();
}

class Redirect extends Exception { }
class FullRedirect extends Exception { }
class EmptyDbException extends Exception { }
class NotFoundException extends Exception { }
class DbException extends Exception { }
class StaleCache extends Exception { }

