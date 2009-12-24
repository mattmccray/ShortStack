<?php

class Dispatcher {
  static public $dispatched = false;
  static public $current = null;

  static public function recognize($override_controller=false) {
    if(!defined('BASEURI')) {
//      $base_uri = @ str_replace($_SERVER['PATH_INFO'], "/", array_shift(explode("?", $_SERVER['REQUEST_URI'])));
      $base_uri = @ getBaseUri();
      define("BASEURI", $base_uri);
    }
    //echo "base uri = ". BASEURI;
    $uri = @  '/'.$_SERVER['QUERY_STRING']; //@ $_SERVER['PATH_INFO']; // was REQUEST_URI
    $path_segments = array_filter(explode('/', $uri), create_function('$var', 'return ($var != null && $var != "");'));
    $controller = array_shift($path_segments);
    // override is mainly only used for an 'install' controller... I'd imagine.
    if($override_controller != false) $controller = $override_controller;
    if($controller == '') {
      $controller = 'home_controller';
    } else { 
      $controller = $controller.'_controller';
    }
    return self::dispatch($controller, $path_segments);
  }
  
  static public function dispatch($controller_name, $route_data=array()) {
    if (!self::$dispatched) {
      $controller = self::getControllerClass($controller_name);
      self::$current = $controller;
      try {
        $ctrl = new $controller();
        $ctrl->execute($route_data);
        // echo "CONTROLLER:<pre>$controller\n";
        // print_r($route_data);
        // echo "</pre>";
        self::$dispatched = true;
      }
      catch( NotFoundException $e ) {
        global $shortstack_config;
        if(@ $notfound = $shortstack_config['controllers']['404_handler']) {
          $uri = @ '/'.$_SERVER['QUERY_STRING']; // $uri = @ $_SERVER['PATH_INFO']; // was REQUEST_URI
          $path_segments = array_filter(explode('/', $uri), create_function('$var', 'return ($var != null && $var != "");'));
          $hack_var = array_shift($path_segments); array_unshift($path_segments, $hack_var);
          $controller = self::getControllerClass( $notfound );
          self::dispatch($controller, $path_segments);
        } else {
          echo "Not Found.";
        }
      }
      catch( Redirect $e ) {
        $route_data = explode('/', $e->getMessage());
        $controller = self::getControllerClass( array_shift($route_data) );
        self::dispatch($controller, $route_data);
      }
      catch( FullRedirect $e ) {
        $url = $e->getMessage();
        header( 'Location: '.$url );
        exit(0);
      }
    }
  }
  
  static private function getControllerClass($controller_name) {
    $controller = underscore( $controller_name );
    if(! strpos($controller, 'ontroller') > 0) $controller .= "_controller";
    return camelize( $controller );
  }  
}