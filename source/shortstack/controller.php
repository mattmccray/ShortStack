<?php

class Controller {
  protected $defaultLayout = "_layout";
  protected $cacheName = false;
  protected $cacheOutput = true;
  
  function __get($key) {
    if($key == 'flash') {
      if(!$this->__flash) $this->__flash = new Flash();
      return $this->__flash;
    }
    return null;
  }

  // Default execute method... Feel free to override.
  function execute($args=array()) {
    $this->cacheName = get_class($this)."-".join('_', $args);
    if(@ $this->secure) $this->requiresLogin();
    $this->_preferCached();
    $this->dispatchAction($args);
  }

  function index($params=array()) {
    throw new NotFoundException("Action <code>index</code> not implemented.");
  }

  function render($view, $params=array(), $wrapInLayout=null) {
    $tmpl = new Template( ShortStack::ViewPath($view) );
    $content = $tmpl->fetch($params);
    $this->renderText($content, $tmpl->context, $wrapInLayout);
  }

  function renderText($text, $params=array(), $wrapInLayout=null) {
    $layoutView = ($wrapInLayout == null) ? $this->defaultLayout : $wrapInLayout;
    $output = '';
    if($layoutView !== false) {
      $layout = new Template( ShortStack::ViewPath($layoutView) );
      $layout->contentForLayout = $text;
      $output = $layout->fetch($params);
    } else {
      $output = $text;
    }
    $this->_cacheContent($output);
    echo $output;
  }

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

  protected function _preferCached($name=null) {
    if($this->cacheOutput) {
      $cname = ($name == null) ? $this->cacheName : $name;
      if(Cache::Exists($cname)) {
        try {
          echo Cache::Get($cname);
          exit(0);
        } catch(StaleCache $e) {  }  // Do nothing!
      }
    }
  }

  protected function _cacheContent($content, $name=null) {
    if($this->cacheOutput) {
      $cname = ($name == null) ? $this->cacheName : $name;
      Cache::Save($cname, $content);
    }
  }

  protected function requiresLogin($useHTTP=false) {
    $this->cacheOutput = false;
    if (!$this->isLoggedIn()) {
      if($useHTTP) {
        $this->_handleHttpAuth();
      } else {
        throw new Redirect($this->sessionController);
      }
    }
  }
  /**
   * @deprecated
   */
  protected function ensureLoggedIn($useHTTP=false) {
    $this->requiresLogin($useHTTP);
  }
  
  public function currentUsername() {
    if($this->isLoggedIn())
      return $_SESSION['CURRENT_USER'];
    else
      return null;
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

  protected function isGet() { return $_SERVER['REQUEST_METHOD'] == 'GET'; }
  protected function isPost() { return ($_SERVER['REQUEST_METHOD'] == 'POST' && @ (!$_POST['_method'] || $_POST['_method'] == 'post')); }
  protected function isPut() { return (@ $_SERVER['REQUEST_METHOD'] == 'PUT' || @ $_POST['_method'] == 'put'); }
  protected function isDelete() { return (@ $_SERVER['REQUEST_METHOD'] == 'DELETE' || @ $_POST['_method'] == 'delete' ); }
  protected function isHead() { return (@ $_SERVER['REQUEST_METHOD'] == 'HEAD' || @ $_POST['_method'] == 'head'); }

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

  // Static methods
  private static $blacklisted_controllers = array();

  public static function Blacklist() {
    foreach (func_get_args() as $controller) {
      self::$blacklisted_controllers[] = $controller;
    }
  }

  public static function IsAllowed($controller) {
    return !in_array($controller, self::$blacklisted_controllers);
  }
}