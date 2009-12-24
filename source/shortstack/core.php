<?php

// = Autoloader =

function __autoload($className) {
  $classPath = ShortStack::AutoLoadFinder($className);
  if(!file_exists($classPath)) {
    return eval("class {$className}{public function __construct(){throw new NotFoundException('Class not found!');}}");
  } else {
    require_once($classPath);
  }
}

// = Exceptions =

class Redirect extends Exception { }
class FullRedirect extends Exception { }
class EmptyDbException extends Exception { }
class NotFoundException extends Exception { }

// = ShortStack Core =

class ShortStack {
  public static function AutoLoadFinder($className) {
    global $shortstack_config;
    if(strpos($className, 'ontroller') > 0) {
      return self::controllerPath( underscore($className) );
    } else if(strpos($className, 'elper') > 0) {
        return self::helperPath( underscore($className) );
    } else { // Model
      return self::modelPath( underscore($className) );
    }    
  }
  // Loads all models in the models/ folder and returns the model class names
  public static function LoadAllModels() {
    global $shortstack_config;
    $model_files = glob( self::modelPath("*") );
    $classNames = array();
    foreach($model_files as $filename) {
      $className = str_replace($shortstack_config['models']['folder']."/", "", $filename);
      $className = str_replace(".php", "", $className);
      require_once($filename);
      $classNames[] = camelize($className);
    }
    return $classNames;    
  }
  // File paths...
  public static function viewPath($path) {
    return self::getPathFor('views', $path);
  }
  public static function controllerPath($path) {
    return self::getPathFor('controllers', $path);
  }
  public static function modelPath($path) {
    return self::getPathFor('models', $path);
  }
  public static function helperPath($path) {
    return self::getPathFor('helpers', $path);
  }
  protected static function getPathFor($type, $path) {
    global $shortstack_config;
    return $shortstack_config[$type]['folder']."/".$path.".php";
  }
}

