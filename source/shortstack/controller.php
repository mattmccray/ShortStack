<?php

// = Controller =

class Controller {
  protected $defaultLayout = "_layout";
  
  // Default execute method... Feel free to override.
  function execute($args=array()) {
    if(@ $this->secure) $this->ensureLoggedIn();
    $this->dispatchAction($args);
  }
  
  function index($params=array()) {
    throw new NotFoundException("Action <code>index</code> not implemented.");
  }
  
  function render($view, $params=array(), $wrapInLayout=null) {
    $tmpl = new Template( ShortStack::viewPath($view) );
    $content = $tmpl->fetch($params);
    $this->renderText($content, $params, $wrapInLayout);
  }
  
  function renderText($text, $params=array(), $wrapInLayout=null) {
    $layoutView = ($wrapInLayout == null) ? $this->defaultLayout : $wrapInLayout;
    
    if($layoutView !== false) {
      $layout = new Template( ShortStack::viewPath($layoutView) );
      $layout->contentForLayout = $text;
      $layout->display($params);
    } else {
      echo $text;
    }
  }
  
  // TODO: ???Should this even be here???
  protected $sessionController = "session";
  protected $secured = false;
  
  // OVERRIDE THIS!!!!
  function authenticate($username, $password) {
    return false;
  }
  
  protected function _handleHttpAuth($realm="Authorization Required", $force=false) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || $force == true) {
      header('WWW-Authenticate: Basic realm="'.$realm.'"');
      header('HTTP/1.0 401 Unauthorized');
      echo '401 Unauthorized';
      exit;
    } else {
      $auth = array(
        'username'=>$_SERVER['PHP_AUTH_USER'],
        'password'=>$_SERVER['PHP_AUTH_PW'] // When hashed: md5($_SERVER['PHP_AUTH_PW']); ???
      );
      if(! $this->doLogin($auth) ) {
          $this->_handleHttpAuth($realm, true);
        }
      }
  }

  
  protected function ensureLoggedIn($useHTTP=false) {
    if (!$this->isLoggedIn()) {
      if($useHTTP) {
        $this->_handleHttpAuth();
      } else {
//        throw new Redirect($this->sessionController);
      }
    }
  }
  
  protected function isLoggedIn() {
    @ session_start();
    return isset($_SESSION['CURRENT_USER']);
  }
  
  protected function doLogin($src=array()) {
    if($this->authenticate($src['username'], $src['password'])) {
      @ session_start();
      $_SESSION['CURRENT_USER'] = $src['username'];
      return true;
    } else {
      @ session_start();
      session_destroy();
      return false;
    }
  }

  protected function doLogout() {
    @ session_start();
    session_destroy();
  }
  // end???
  
  protected function isGet() {
    return $_SERVER['REQUEST_METHOD'] == 'GET';
  }

  protected function isPost() {
    return ($_SERVER['REQUEST_METHOD'] == 'POST' && @ !$_POST['_method']);
  }

  protected function isPut() {
    return (@ $_SERVER['REQUEST_METHOD'] == 'PUT' || @ $_POST['_method'] == 'put');
  }

  protected function isDelete() {
    return (@ $_SERVER['REQUEST_METHOD'] == 'DELETE' || @ $_POST['_method'] == 'delete' );
  }

  protected function isHead() {
    return (@ $_SERVER['REQUEST_METHOD'] == 'HEAD' || @ $_POST['_method'] == 'head');
  }
  
  protected function dispatchAction($path_segments) {
    $action = @ $path_segments[0]; //array_shift($path_segments);
    if( method_exists($this, $action) ) {
      array_shift($path_segments);
      $this->$action($path_segments);
    } else {
      // Index is a bit of a catchall...
      $this->index($path_segments);
    }
  }
  
}