<?php

// ShortStack v0.9.7b3
// By M@ McCray
// (All comments have been stripped see:)
// http://github.com/darthapo/ShortStack

function __autoload($className) {
  $classPath = ShortStack::AutoLoadFinder($className);
  if(!file_exists($classPath)) {
    return eval("class {$className}{public function __construct(){throw new NotFoundException('Class not found!');}}");
  } else {
    require_once($classPath);
  }
}
class ShortStack {
  
  public static function AutoLoadFinder($className) {
    if(strpos($className, 'ontroller') > 0) {
      return self::ControllerPath( underscore($className) );
    } else if(strpos($className, 'elper') > 0) {
        return self::HelperPath( underscore($className) );
    } else { 
      return self::ModelPath( underscore($className) );
    }
  }
  
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
  
  
  private static $doctypeCache = array();
}

class Redirect extends Exception { }
class FullRedirect extends Exception { }
class EmptyDbException extends Exception { }
class NotFoundException extends Exception { }
class DbException extends Exception { }
class StaleCache extends Exception { }

function url_for($controller) {
  return BASEURI . $controller;
}

function link_to($controller, $label, $className="") {
  return '<a href="'. url_for($controller) .'" class="'. $className .'">'. $label .'</a>';
}

function ends_with($test, $string) {
  $strlen = strlen($string);
  $testlen = strlen($test);
  if ($testlen > $strlen) return false;
  return substr_compare($string, $test, -$testlen) === 0;

}

function slugify($str) {
   
   $slug = preg_replace("/[^a-zA-Z0-9 -]/", "", trim($str));
   $slug = preg_replace("/[\W]{2,}/", " ", $slug); 
   $slug = str_replace(" ", "-", $slug); 
   $slug = strtolower($slug);  
   return $slug;
}

function underscore($str) {
  $str = str_replace("-", " ", $str);
  $str = preg_replace_callback('/[A-Z]/', "underscore_matcher", trim($str));
  $str = str_replace(" ", "", $str);
  $str = preg_replace("/^[_]?(.*)$/", "$1", $str);
  return $str;
}

function underscore_matcher($match) { return "_" . strtolower($match[0]); }

function camelize($str) {
  $str = str_replace("-", "", $str);
	$str = 'x '.strtolower(trim($str));
	$str = ucwords(preg_replace('/[\s_]+/', ' ', $str));
	return substr(str_replace(' ', '', $str), 1);
}

function plural($str, $force = FALSE) {
	$str = strtolower(trim($str));
	$end3 = substr($str, -3);
	$end1 = substr($str, -1);
	if ($end3 == 'eau') { $str .= 'x'; }
	elseif ($end3 == 'man') { $str = substr($str, 0, -2).'en'; }
	elseif (in_array($end3, array('dum', 'ium', 'lum'))) { $str = substr($str, 0, -2).'a'; }
	elseif (strlen($str) > 4 && in_array($end3, array('bus', 'eus', 'gus', 'lus', 'mus', 'pus'))) { $str = substr($str, 0, -2).'i'; }
	elseif ($end3 == 'ife') { $str = substr($str, 0, -2).'ves'; }
	elseif ($end1 == 'f') { $str = substr($str, 0, -1).'ves'; }
	elseif ($end1 == 'y') {	$str = substr($str, 0, -1).'ies';	}
	elseif (in_array($end1, array('h', 'o', 'x'))) { $str .= 'es'; }
	elseif ($end1 == 's') {	if ($force == TRUE)	{ $str .= 'es'; } }
	else { $str .= 's'; }
	return $str;
}

function singular($str) {
	$str = strtolower(trim($str));
	$end5 = substr($str, -5);
	$end4 = substr($str, -4);
	$end3 = substr($str, -3);
	$end2 = substr($str, -2);
	$end1 = substr($str, -1);
	if ($end5 == 'eives') { $str = substr($str, 0, -3).'f'; }
	elseif ($end4 == 'eaux') { $str = substr($str, 0, -1); }
	elseif ($end4 == 'ives') { $str = substr($str, 0, -3).'fe'; }
	elseif ($end3 == 'ves') { $str = substr($str, 0, -3).'f'; }
	elseif ($end3 == 'ies') {	$str = substr($str, 0, -3).'y'; }
	elseif ($end3 == 'men') {	$str = substr($str, 0, -2).'an'; }
	elseif ($end3 == 'xes' && strlen($str) > 4 OR in_array($end3, array('ses', 'hes', 'oes'))) { $str = substr($str, 0, -2); }
	elseif (in_array($end2, array('da', 'ia', 'la'))) { $str = substr($str, 0, -1).'um'; }
	elseif (in_array($end2, array('bi', 'ei', 'gi', 'li', 'mi', 'pi'))) { $str = substr($str, 0, -1).'us'; }
	else { if ($end1 == 's')	$str = substr($str, 0, -1); }
	return $str;
}

function object_sort(&$data, $key) {
  for ($i = count($data) - 1; $i >= 0; $i--) {
    $swapped = false;
    for ($j = 0; $j < $i; $j++){
      if ($data[$j]->$key > $data[$j + 1]->$key) { 
        $tmp = $data[$j];
        $data[$j] = $data[$j + 1];        
        $data[$j + 1] = $tmp;
        $swapped = true;
      }
    }
    if (!$swapped) return;
  }
}

function object_sort_r(&$object, $key) { 
  for ($i = count($object) - 1; $i >= 0; $i--) { 
    $swapped = false; 
    for ($j = 0; $j < $i; $j++) { 
      if ($object[$j]->$key < $object[$j + 1]->$key) { 
        $tmp = $object[$j]; 
        $object[$j] = $object[$j + 1];       
        $object[$j + 1] = $tmp; 
        $swapped = true; 
      } 
    } 
    if (!$swapped) return; 
  } 
}

function use_helper($helper) {
  if(! strpos($helper, 'elper') > 0) $helper .= "_helper";
  require_once( ShortStack::HelperPath($helper) );
}

function getBaseUri() { 
	return str_replace("/".$_SERVER['QUERY_STRING'], "/", array_shift(explode("?", $_SERVER['REQUEST_URI'])));
}

function debug($obj) {
  echo "<pre>";
  print_r($obj);
  echo "</pre>\n";
}

function doc($doctype, $id=null) {
  return ($id == null) ? Document::Find($doctype) : Document::Get($doctype, $id);
}

function mdl($objtype, $id=null) {
  return ($id == null) ? Model::Find($objtype) :  Model::Get($objtype, $id);
}

function get($modelName, $id=null) {
  return (ShortStack::IsDocument($modelName)) ? doc($modelName, $id) : mdl($modelName, $id);
  
}

class Dispatcher {
  static public $dispatched = false;
  static public $current = null;

  static public function Recognize($override_controller=false) {
    if(!defined('BASEURI')) {
      $base_uri = @ getBaseUri();
      define("BASEURI", $base_uri);
    }
    $uri = @  '/'.$_SERVER['QUERY_STRING']; 
    $path_segments = array_filter(explode('/', $uri), create_function('$var', 'return ($var != null && $var != "");'));
    $controller = array_shift($path_segments);
    
    if($override_controller != false) $controller = $override_controller;
    if($controller == '') {
      global $shortstack_config;
      $controller = @$shortstack_config['controllers']['index']; 
      if(!$controller) $controller = 'home_controller'; 
    } else {
      $controller = $controller.'_controller';
    }
    return self::Dispatch($controller, $path_segments);
  }

  static public function Dispatch($controller_name, $route_data=array()) {
    if (!self::$dispatched) {
      $controller = self::getControllerClass($controller_name);
      self::$current = $controller;
      try {
        if(!Controller::IsAllowed($controller_name)) throw new NotFoundException();
        $ctrl = new $controller();
        $ctrl->execute($route_data);
        self::$dispatched = true;
      }
      catch( NotFoundException $e ) {
        global $shortstack_config;
        if(@ $notfound = $shortstack_config['controllers']['404_handler']) {
          $uri = @ '/'.$_SERVER['QUERY_STRING']; 
          $path_segments = array_filter(explode('/', $uri), create_function('$var', 'return ($var != null && $var != "");'));
          $hack_var = array_shift($path_segments); array_unshift($path_segments, $hack_var);
          $controller = self::getControllerClass( $notfound );
          self::Dispatch($controller, $path_segments);
        } else {
          echo "Not Found.";
        }
      }
      catch( Redirect $e ) {
        $route_data = explode('/', $e->getMessage());
        $controller = self::getControllerClass( array_shift($route_data) );
        self::Dispatch($controller, $route_data);
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
class Cache {
  
  public static function Exists($name) {
    if(!USECACHE || DEBUG) return false;
    else return file_exists( ShortStack::CachePath($name) );
  }
  
  public static function Get($name) {
    $cacheContent = file_get_contents( ShortStack::CachePath($name) );
    $splitter = strpos($cacheContent, "\n");
    $contents = substr($cacheContent, $splitter+1, strlen($cacheContent) - $splitter);
    if(CACHELENGTH > 0) {
      $timeSinseCached = time() - intVal(substr($cacheContent, 0, $splitter));;
      if($timeSinseCached > CACHELENGTH) {
        Cache::Expire($name);
        throw new StaleCache('Cache expired.');
      } else {
        return $contents;
      }
    } else {
      return $contents;
    }
  }
  
  public static function Save($name, $content) {
    if(!USECACHE || DEBUG) return true;
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
class Controller {
  protected $defaultLayout = "_layout";
  protected $cacheName = false;
  protected $cacheOutput = true;
  function execute($args=array()) {
    $this->cacheName = get_class($this)."-".join('_', $args);
    if(@ $this->secure) $this->ensureLoggedIn();
    $this->_preferCached();
    $this->dispatchAction($args);
  }

  function index($params=array()) {
    throw new NotFoundException("Action <code>index</code> not implemented.");
  }

  function render($view, $params=array(), $wrapInLayout=null) {
    $tmpl = new Template( ShortStack::ViewPath($view) );
    $content = $tmpl->fetch($params);
    $this->renderText($content, $params, $wrapInLayout);
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
        'password'=>$_SERVER['PHP_AUTH_PW'] 
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
        } catch(StaleCache $e) {  }  
      }
    }
  }

  protected function _cacheContent($content, $name=null) {
    if($this->cacheOutput) {
      $cname = ($name == null) ? $this->cacheName : $name;
      Cache::Save($cname, $content);
    }
  }

  protected function ensureLoggedIn($useHTTP=false) {
    if (!$this->isLoggedIn()) {
      if($useHTTP) {
        $this->_handleHttpAuth();
      } else {
        throw new Redirect($this->sessionController);
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

  protected function isGet() { return $_SERVER['REQUEST_METHOD'] == 'GET'; }
  protected function isPost() { return ($_SERVER['REQUEST_METHOD'] == 'POST' && @ !$_POST['_method']); }
  protected function isPut() { return (@ $_SERVER['REQUEST_METHOD'] == 'PUT' || @ $_POST['_method'] == 'put'); }
  protected function isDelete() { return (@ $_SERVER['REQUEST_METHOD'] == 'DELETE' || @ $_POST['_method'] == 'delete' ); }
  protected function isHead() { return (@ $_SERVER['REQUEST_METHOD'] == 'HEAD' || @ $_POST['_method'] == 'head'); }

  protected function dispatchAction($path_segments) {
    $action = @ $path_segments[0]; 
    if( method_exists($this, $action) ) {
      array_shift($path_segments);
      $this->$action($path_segments);
    } else {
      
      $this->index($path_segments);
    }
  }
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
class DB {
  static protected $pdo;

  static public function Connect($conn, $user="", $pass="", $options=array()) {
    self::$pdo = new PDO($conn, $user, $pass, $options);
    return self::$pdo;
  }

  static public function Query($sql_string) {
    return self::$pdo->query($sql_string);
  }

  static public function GetLastError() {
    return self::$pdo->errorInfo();
  }

  static public function FetchAll($sql_string) {
    $statement = self::Query($sql_string);
    if($statement != false) {
      return $statement->fetchAll(); 
    } else {
      $err = self::GetLastError();
      throw new DbException("Error:\n\t".$err[2]."\nWas thrown by SQL:\n\t".$sql_string);
    }
  }

  static public function EnsureNotEmpty() {
    $statement = self::Query('SELECT name FROM sqlite_master WHERE type = \'table\'');
    $result = $statement->fetchAll();
    if( sizeof($result) == 0 ){
      define("EMPTYDB", true);
      throw new EmptyDbException("Database has no tables.");
    } else {
      define("EMPTYDB", false);
    }
  }
}
class Model {
  
  public $modelName = null;
  public $errors = array();
  
  protected $data = false;
  protected $isNew = false;
  protected $isDirty = false;
  
  
  protected $changedFields = array();
  
  protected $schema = array();
  
  protected $hasMany = array();
  
  protected $belongsTo = array();
  
  protected $validates = array();
  
  
  function __construct($dataRow=null) {
    $this->modelName = get_class($this);
    if($dataRow != null) {
      $this->data = $dataRow;
      $this->isNew = false;
    } else {
      $this->data = array();
      $this->isNew = true;
    }
    $this->isDirty = false;
  }

  public function has($key) {
    return array_key_exists($key, $this->data);
  }

  public function updateValues($values=array()) {
    foreach($values as $key=>$value) {
      $this->$key = $value;
    }
    return $this;
  }

  public function update($values=array()) {
    return $this->updateValues($values);
  }

  public function hasChanged($key) {
    return in_array($key, $this->changedFields);
  }

  public function getChangedValues() {
    $valid_atts = array_keys( $this->schema );
    $cleanChangedFields = array();
    $results = array();
    foreach($this->changedFields as $fieldname) {
      if(in_array($fieldname, $valid_atts)) { 
        $results[$fieldname] = '"'.htmlentities($this->$fieldname, ENT_QUOTES).'"';
        $cleanChangedFields[] = $fieldname;
      }
    }
    $this->changedFields = $cleanChangedFields;
    return $results;
  }
  
  public function isValid() {
    $this->errors = array();
    return (count($this->errors) == 0);
  }

  public function save() {
    $result = true;
    if($this->isDirty) {
      
      $this->beforeValidation();
      $isValid = $this->isValid();
      $this->afterValidation();
      if(!$isValid) return false;
      
      $this->beforeSave();
      if($this->isNew) { 
        $this->beforeCreate();
        $result = $this->_handleSqlInsert();
      }
      else { 
        $result = $this->_handleSqlUpdate();
      }
      if($result) {
        $this->changedFields = array();
        $this->isDirty = false;
        if($this->isNew) {
          $this->isNew = false;
          $this->afterCreate();
        }
        $this->afterSave();
      }
    }
    return $result;
  }

  public function destroy(){
    $this->beforeDestroy();
    $thisDel = $this->_handleSqlDelete();
    $relDel = $this->_handleRelatedSqlDelete();
    $this->afterDestroy();
    return ($thisDel && $relDel);
  }

  public function kill(){ 
    $thisDel = $this->_handleSqlDelete();
    $relDel = $this->_handleRelatedSqlDelete();
    return ($thisDel && $relDel);
  }

  public function to_array($exclude=array()) {
    $attrs = array();
    foreach($this->data as $col=>$value) {
      if(!in_array($col, $exclude)) {
        $attrs[$col] = $this->$col;
      }
    }
    return $attrs;
  }

  public function to_json($exclude=array()) {
    return Model::toJSON( $this->to_array($exclude) );
  }

  public function createTableForModel() {
    return $this->_handleSqlCreate();
  }
  function __get($key) {
    if($this->data) {
      return html_entity_decode($this->data[$key], ENT_QUOTES );
    }
  }

  function __set($key, $value) {
    $value = stripslashes($value);
    if(@ $this->data[$key] != htmlentities($value, ENT_QUOTES)) {
      $this->data[$key] = $value;
      $this->changedFields[] = $key;
      $this->isDirty = true;
    }
  }

  function __call( $method, $args ) {
    if(array_key_exists($method, $this->hasMany)) {
      return $this->_handleHasMany($method, $args);
    }
    else if(array_key_exists($method, $this->belongsTo)) {
      return $this->_handleBelongsTo($method, $args);
    }
    else if(preg_match('/^(new|add|set)(.*)/', $method, $matches)) {
      list($full, $mode, $modelName) = $matches;
      return $this->_handleRelationshipBuilder($mode, $modelName, $args);
    }
    else {
      return NULL; 
    }
  }

  protected function _handleHasMany($method, $args) {
    $def = $this->hasMany[$method];
    if(array_key_exists('document', $def)) {
      $mdlClass = $def['document'];
      $fk = strtolower($this->modelName)."_id";
      return Document::Find($mdlClass)->where($fk)->eq($this->id);
    }
    else if(array_key_exists('model', $def)) {
      $mdlClass = $def['model'];
      $fk = strtolower($this->modelName)."_id";
      return Model::Find($mdlClass)->where($fk)->eq($this->id);
    }
    else if(array_key_exists('through', $def)) {
      $thruCls = $def['through'];
      $thru = new $thruCls(null, $this);
      return $thru->getRelated($method);
    }
    else {
      throw new Exception("A relationship has to be defined as a model or document");
    }
  }

  protected function _handleBelongsTo($method, $args) {
    $def = $this->belongsTo[$method];
    if(array_key_exists('document', $def)) {
      $mdlClass = $def['document'];
      $fk = strtolower($mdlClass)."_id";
      return Document::Find($mdlClass)->where('id')->eq($this->{$fk})->get();
    }
    else if(array_key_exists('model', $def)) {
      $mdlClass = $def['model'];
      $fk = strtolower($mdlClass)."_id";
      return Model::Get($mdlClass, $this->{$fk});
    } else {
      throw new Exception("A relationship has to be defined as a model or document");
    }
  }

  protected function _handleRelationshipBuilder($mode, $modelName, $args) {
    
    $fk = strtolower($this->modelName)."_id";
    if($mode == 'new') {
      $mdl = new $modelName();
      $mdl->{$fk} = $this->id;
      if(count($args) > 0 && is_array($args[0])) {
        $mdl->update($args[0]);
      }
      return $mdl;
    }
    else if($mode == 'add') {
      list($mdl) = $args;
      $mdl->{$fk} = $this->id;
      $mdl->save();
    }
    else if($mode == 'set') {
      list($mdl) = $args;
      $fk = strtolower($mdl->modelName)."_id";
      $this->{$fk} = $mdl->id;
    }
    else {
      throw new Exception("Unknown relationship mode: ".$mode);
    }
    return $this;
  }

  protected function _handleSqlCreate() {
    $sql = "CREATE TABLE IF NOT EXISTS ";
    $sql.= $this->modelName ." ( ";
    $cols = array();
    $modelColumns = $this->schema;
    if(!array_key_exists('id',$this->schema))
      $modelColumns["id"] = 'INTEGER PRIMARY KEY';
    foreach($modelColumns as $name=>$def) {
      $cols[] = $name ." ". $def;
    }
    $sql.= join($cols, ', ');
    $sql.= " );";
    $statement = DB::Query( $sql );
    
    if(array_key_exists('created_on', $this->schema)) {
      $triggerSQL = "CREATE TRIGGER generate_". $this->modelName ."_created_on AFTER INSERT ON ". $this->modelName ." BEGIN UPDATE ". $this->modelName ." SET created_on = DATETIME('NOW') WHERE rowid = new.rowid; END;";
      if(DB::Query( $triggerSQL ) == false) $statement = false;
    }
    
    if(array_key_exists('updated_on', $this->schema)) {
      $triggerSQL = "CREATE TRIGGER generate_". $this->modelName ."_updated_on AFTER UPDATE ON ". $this->modelName ." BEGIN UPDATE ". $this->modelName ." SET updated_on = DATETIME('NOW') WHERE rowid = new.rowid; END;";
      if(DB::Query( $triggerSQL ) == false) $statement = false;
    }

    return ($statement != false);
  }

  protected function _handleSqlInsert() {
    $values = $this->getChangedValues();
    $sql = "INSERT INTO ".$this->modelName." (".join($this->changedFields, ', ').") VALUES (".join($values, ', ').");";
    $statement = DB::Query($sql);
    if($statement == false) return false;
    
    $result = DB::Query('SELECT last_insert_rowid() as last_insert_rowid')->fetch();
    $this->data['id'] = $result['last_insert_rowid'];
    return true;
  }

  protected function _handleSqlUpdate() {
    $values = $this->getChangedValues();
    $fields = array();
    foreach($values as $field=>$value) {
      $fields[] = $field." = ".$value;
    }
    $sql = "UPDATE ".$this->modelName." SET ". join($fields, ", ") ." WHERE id = ". $this->id .";";
    $statement = DB::Query($sql);
    return ($statement != false);
  }

  protected function _handleSqlDelete() {
    $sql = "DELETE FROM ".$this->modelName." WHERE id = ". $this->id .";";
    $stmt = DB::Query($sql);
    return ($stmt != false);
  }

  protected function _handleRelatedSqlDelete() {
    $fk = strtolower($this->modelName)."_id";
    foreach ($this->hasMany as $methodName => $relDef) {
      $rule = (array_key_exists('cascade', $relDef)) ? $relDef['cascade'] : 'delete';
      if(array_key_exists('through', $relDef)) {
        $joinerCls = $relDef['through'];
        get($joinerCls)->where($fk)->eq($this->id)->destroy();
      }
      else {
        $mdlCls = (array_key_exists('document', $relDef)) ? $relDef['document'] : $relDef['model'];
        $matches = get($mdlCls)->where($fk)->eq($this->id);
        if($rule == 'delete')
          $matches->destroy();
        else
          $matches->update(array($fk=>null)); 
      }
    }
    return true;
  }
  protected function beforeValidation() {}
  protected function afterValidation() {}
  protected function beforeSave() {}
  protected function afterSave() {}
  protected function beforeCreate() {}
  protected function afterCreate() {}
  protected function beforeDestroy() {}
  protected function afterDestroy() {}

  public static $IsModel = true;
  public static $IsDocument = false;

  public static function Get($modelName, $id) {
    $sql = "SELECT * FROM ".$modelName." WHERE id = ". $id ." LIMIT 1;";
    $results = DB::FetchAll($sql);
    return new $modelName( $results[0] );
  }

  public static function Find($modelName) {
    return new ModelFinder($modelName);
  }
  public static function Remove($modelName, $id) {
    $inst = self::Get($modelName, $id);
    return $inst->kill();
  }

  static public function Count($className) {
    $sql = "SELECT count(id) as count FROM ".$className.";";
    $results = DB::FetchAll($sql);
    return @(integer)$results[0]['count'];
  }

  static public function toJSON($obj) {
    if(is_array($obj)) {
      $data = array();
      foreach($obj as $idx=>$mdl) {
        if($mdl instanceof Model)
          $data[] = $mdl->to_array();
        else
          $data[$idx] = $mdl;
      }
      return json_encode($data);

    } else if( $obj instanceof Model ) {
      return json_encode($obj->to_array());

    } else {
      return json_encode($obj);
    }
  }
}

class ModelJoiner extends Model {
  
  protected $joins = array();
  private $srcModel;
  private $toModel;

  public function __construct($dataRow=null, $from=null) {
    parent::__construct($dataRow);
    $this->srcModel = $from;
    if(count($this->joins) == 2) {
      list($left, $right) = $this->joins;
      $left = strtolower($left)."_id";
      $right = strtolower($right)."_id";
      if(! array_key_exists($left, $this->schema)) $this->schema[$left] = "INTEGER";
      if(! array_key_exists($right, $this->schema)) $this->schema[$right] = "INTEGER";
    }
  }

  public function getRelated($to) {
    list($a, $b) = $this->joins;
    $srcMdlCls = $this->srcModel->modelName;
    $toMdlCls = ($a == $srcMdlCls) ? $b : $a;
    $srcId = strtolower($srcMdlCls)."_id";
    $toId = strtolower($toMdlCls)."_id";
    $id = $this->srcModel->id;
    $sql = "SELECT * FROM $toMdlCls WHERE id IN (SELECT $toId FROM $this->modelName WHERE $srcId = $id);";
    
    $stmt = DB::Query($sql);
    $mdls = array();
    if($stmt) {
      $results = $stmt->fetchAll();
      foreach ($results as $row) {
        $mdls[] = new $toMdlCls($row);
      }
    } 
    
    
    
    return $mdls;
  }
  
}
class ModelFinder implements IteratorAggregate {
  
  protected $objtype;
  protected $matcher = false;
  protected $finder = array();
  protected $or_finder = array();
  protected $order = array();
  protected $limit = false;
  protected $offset = false;
  private $__cache = false;
  

  public function __construct($objtype) {
    $this->objtype = $objtype;
    $this->matcher = new FinderMatcher($this);
  }
  
  public function where($index) {
    $this->__cache = false;
    $this->matcher->_updateIdxAndCls($index, 'and');
    return $this->matcher;
  }
  
  public function andWhere($index) {
    $this->__cache = false;
    $this->matcher->_updateIdxAndCls($index, 'and');
    return $this->matcher;
  }
  
  public function orWhere($index) {
    $this->__cache = false;
    $this->matcher->_updateIdxAndCls($index, 'or');
    return $this->matcher;
  }
  
  public function order($field, $dir='ASC') {
    
    $this->__cache = false;
    $this->order[$field] = $dir;
    return $this;
  }
  
  public function limit($count) {
    $this->__cache = false;
    $this->limit = $count;
    return $this;
  }
  
  public function offset($count) {
    $this->__cache = false;
    $this->offset = $count;
    return $this;
  }
  
  public function count() {
    $sql = $this->_buildSQL(true);
    $res = DB::FetchAll($sql);
    return intVal( $res[0]['count'] );
  }
  
  public function get($ignoreCache=false) {   
    $oldLimit = $this->limit;
    $this->limit = 1; 
    $docs = $this->_execQuery($ignoreCache);
    $this->limit = $oldLimit;
    if(count($docs) == 0)
      return null;
    else
      return @$docs[0];
  }
  
  public function fetch($ignoreCache=false) { 
    return $this->_execQuery($ignoreCache);
  }
  
  public function raw($ignoreCache=true) { 
    $sql = $this->_buildSQL();
    $stmt = DB::Query($sql);
    return $stmt->fetchAll();
  }
  
  public function getIterator() { 
    $docs = $this->_execQuery();
    return new ArrayIterator($docs);
  }
  
  public function destroy() {
    foreach ($this as $doc) {
      $doc->destroy();
    }
    $this->__cache = false;
    return $this;
  }
  
  public function update($values=array()) {
    foreach ($this as $doc) {
      $doc->update($values);
      $doc->save();
    }
    $this->__cache = false;
    return $this;
  }
  
  public function build($props=array()) {
    
    $mdlCls = $this->objtype;
    $mdl = new $mdlCls();
    $mdl->update($props);
    return $mdl;
  }
  
  public function _addFilter($column, $comparision, $value, $clause) {
    $this->__cache = false;
    $finder_filter = array('col'=>$column, 'comp'=>$comparision, 'val'=>$value);
    if($clause == 'or') $this->or_finder[] = $finder_filter;
    else $this->finder[] = $finder_filter;
    return $this;
  }
  
  protected function _execQuery($ignoreCache=false) {
    if($ignoreCache == false && $this->__cache != false) return $this->__cache;
    $sql = $this->_buildSQL();
    $stmt = DB::Query($sql);
    $items = array();
    if($stmt) {
      $results = $stmt->fetchAll();
      $className = $this->objtype;
      foreach ($results as $rowdata) {
        $items[] = new $className($rowdata);
      }
      $this->__cache = $items;
    } 

    return $items;
  }
  
  protected function _buildSQL($isCount=false) {
    if($isCount)
      $sql = "SELECT count(id) as count FROM ". $this->objtype ." ";
    else
      $sql = "SELECT * FROM ". $this->objtype ." ";
    
    if(count($this->finder) > 0) {
      $sql .= "WHERE ";
      $finders = array();
      foreach($this->finder as $qry) {
        $finders []= $qry['col']." ".$qry['comp'].' "'. htmlentities($qry['val'],ENT_QUOTES).'"';
      }
      $sql .= join(" AND ", $finders);
    }
    

    if(count($this->order) > 0) {
      $sql .= " ORDER BY ";
      $order_params = array();
      foreach ($this->order as $field => $dir) {
        $order_params[]= $field." ".$dir;
      }
      $sql .= join(", ", $order_params);
    }
    if($this->limit != false && $this->limit > 0) {
      $sql .= " LIMIT ". $this->limit ." ";
    }
    if($this->offset != false && $this->offset > 0) {
      $sql .= " OFFSET ". $this->offset ." ";
    }
    $sql .= " ;";
    return $sql;
  }
}

class FinderMatcher {
  
  protected $finder;
  protected $index;
  protected $clause;

  public function __construct($finder) {
    $this->finder = $finder;
  }
  public function _updateIdxAndCls($idx, $clause) {
    $this->index = $idx;
    $this->clause = $clause;
    return $this;
  }
  
  
  public function eq($value) {
    return $this->finder->_addFilter($this->index, '=', $value, $this->clause);
  }
  public function neq($value) {
    return  $this->finder->_addFilter($this->index, '!=', $value, $this->clause);
  }
  public function gt($value) {
    return $this->finder->_addFilter($this->index, '>', $value, $this->clause);
  }
  public function lt($value) {
    return $this->finder->_addFilter($this->index, '<', $value, $this->clause);
  }
  public function gte($value) {
    return $this->finder->_addFilter($this->index, '>=', $value, $this->clause);
  }
  public function lte($value) {
    return $this->finder->_addFilter($this->index, '<=', $value, $this->clause);
  }
  public function like($value) {
    return $this->finder->_addFilter($this->index, 'like', $value, $this->clause);
  }
  public function in($value) {
    return $this->finder->_addFilter($this->index, 'in', $value, $this->clause);
  }
  
}
class Document extends Model {
  public $id = null;
  public $created_on = null;
  public $updated_on = null;
  protected $indexes = array();
  
  protected $rawData = null;
  
  protected $schema = array(
    'id'          => 'INTEGER PRIMARY KEY',
    'data'        => 'TEXT',
    'created_on'  => 'DATETIME',
    'updated_on'  => 'DATETIME'
  );
  
  
  function __construct($dataRow=null) {
    parent::__construct($dataRow);
    if($dataRow != null) {
      $this->rawData = $dataRow['data'];
      $this->data = false;
      $this->id = $dataRow['id'];
      $this->created_on = $dataRow['created_on'];
      $this->updated_on = $dataRow['updated_on'];
    } else {
      $this->rawData = null;
      $this->data = array();
      $this->id = null;
    }
    
  }

  public function has($key) {
    if(!$this->data) $this->_deserialize();
    return array_key_exists($key, $this->data);
  }

  public function getChangedValues() {
    $results = array();
    foreach($this->changedFields as $key=>$fieldname) {
      $results[$fieldname] = $this->$fieldname;
    }
    return $results;
  }

  public function reindex() {
    $wasSuccessful = true;
    foreach ($this->indexes as $field=>$fType) { 
      $indexTable = $this->modelName ."_". $field ."_idx";
      $sql = "DELETE FROM ". $indexTable ." WHERE docid = ". $this->id .";";
      if(DB::Query($sql) == false) $wasSuccessful = false;
      $sql = "INSERT INTO ". $indexTable ." ( docid, ". $field ." ) VALUES (".$this->id.', "'.htmlentities($this->{$field}, ENT_QUOTES).'" );';
      if(DB::Query($sql) == false) $wasSuccessful = false;
    }
    return $wasSuccessful;
  }

  public function to_array($exclude=array()) {
    $attrs = array(
      'id'=>$this->id,
      'created_on'=>$this->created_on,
      'updated_on'=>$this->updated_on,
    );
    foreach($this->data as $col=>$value) {
      if(!in_array($col, $exclude)) {
        $attrs[$col] = $this->$col;
      }
    }
    return $attrs;
  }
  function __get($key) {
    if(!$this->data) { $this->_deserialize(); }
    return $this->data[$key];
  }

  function __set($key, $value) {
    if(!$this->data) { $this->_deserialize(); }
    $value = stripslashes($value);
    if(@ $this->data[$key] != $value) {
      $this->data[$key] = $value;
      $this->changedFields[] = $key;
      $this->isDirty = true;
    }
  }

  protected function _handleSqlCreate() {
    $mdlRes = parent::_handleSqlCreate();
    $idxRes = true;
    foreach ($this->indexes as $field=>$fType) { 
      $indexSQL = "CREATE TABLE IF NOT EXISTS ". $this->modelName ."_". $field ."_idx ( id INTEGER PRIMARY KEY, docid INTEGER, ". $field ." ". $fType ." );";
      if(DB::Query( $indexSQL ) == false) $idxRes = false;
    }
    return ($mdlRes != false && $idxRes != false);
  }

  protected function _handleSqlInsert() {
    $this->_serialize();
    $sql = 'INSERT INTO '.$this->modelName.' ( data ) VALUES ( "'. $this->rawData .'" );';
    $this->created_on = $this->updated_on = gmdate('Y-m-d H:i:s'); 
    $statement = DB::Query($sql);
    if($statement == false) return false;
    $result = DB::Query('SELECT last_insert_rowid() as last_insert_rowid')->fetch(); 
    $this->id = $result['last_insert_rowid'];
    $this->reindex();
    return true;
  }

  protected function _handleSqlUpdate() {
    $this->_serialize();
    $sql = "UPDATE ".$this->modelName.' SET data="'. $this->rawData .'" WHERE id = '. $this->id .';';
    $statement = DB::Query($sql);
    if($statement == false) return false;
    $this->updated_on = gmdate('Y-m-d H:i:s'); 
    $index_changed = array_intersect($this->changedFields, array_keys($this->indexes));
    if(count($index_changed) > 0) { 
      $this->reindex();
    }
    return true;
  }

  protected function _handleRelatedSqlDelete() {
    $removedIndexes = true;
    foreach ( $this->indexes as $field=>$fType) {
      $sql = "DELETE FROM ".$this->modelName."_".$field."_idx WHERE docid = ". $this->id .";";
      if(DB::Query($sql) == false) $removedIndexes = false;
    }
    return (parent::_handleRelatedSqlDelete() && $removedIndexes);
  }

  private function _serialize() { 
    $this->beforeSerialize();
    $this->rawData = htmlentities( $this->serialize( $this->data ), ENT_QUOTES );
    $this->afterSerialize(); 
    return $this;
  }
  private function _deserialize() { 
    $this->beforeDeserialize();
    $this->data = $this->deserialize( html_entity_decode($this->rawData, ENT_QUOTES) );
    $this->afterDeserialize(); 
    return $this;
  }
  protected function beforeDeserialize() {}
  protected function afterDeserialize() {}
  protected function deserialize($source) { 
    return json_decode($source, true);
  }
  protected function serialize($source) { 
    return json_encode($source);
  }

  public static $IsModel = false;
  public static $IsDocument = true;

  public static function ReindexAll($doctype) {
    doc($doctype)->reindex();
  }

  public static function Find($doctype) {
    return new DocumentFinder($doctype);
  }
}


class DocumentFinder extends ModelFinder {
  
  private $nativeFields = array('id','created_on','updated_on');
  public function reindex() {
    foreach ($this as $doc) {
      $doc->reindex();
    }
    return $this;
  }
  protected function _buildSQL($isCount=false) {
    
    $all_order_cols = array();
    $native_order_cols = array();
    foreach($this->order as $field=>$other) {
      if(in_array($field, $this->nativeFields))
        $native_order_cols[] = $field;
      else
        $all_order_cols[] = $this->_getIdxCol($field, false);
    }

    $all_finder_cols = array();
    $native_finder_cols = array();
    foreach($this->finder as $qry) {
      $colname = $qry['col'];
      if(in_array($colname, $this->nativeFields))
        $native_finder_cols[] = $colname;
      else
        $all_finder_cols[] = $this->_getIdxCol($colname, false);
    }
    

    $tables = array_merge(array($this->objtype), $all_order_cols);
    
    if($isCount)
      $sql = "SELECT count(". $this->objtype .".id) as count FROM ". join(', ', $tables) ." ";
    else
      $sql = "SELECT ". $this->objtype .".* FROM ". join(', ', $tables) ." ";

    if(count($all_finder_cols) > 0) {
      $sql .= "WHERE ". $this->objtype .".id IN (";
      $sql .= "SELECT ". $all_finder_cols[0] .".docid FROM ". join(', ', $all_finder_cols). " ";
      $sql .= " WHERE ";
      $finders = array();
      foreach($this->finder as $qry) {
        if(!in_array($qry['col'], $this->nativeFields))
          $finders []= " ". $this->_getIdxCol($qry['col'])  ." ". $qry['comp'] .' "'. htmlentities($qry['val'], ENT_QUOTES) .'" ';
      }
      $sql .= join(' AND ', $finders);
      $sql .= ") ";
    }
    if(count($native_finder_cols) > 0) {
      $sql .= (count($all_finder_cols) > 0) ? " AND " : " WHERE ";
      $finders = array();
      foreach($this->finder as $qry) {
        if(in_array($qry['col'], $this->nativeFields))
          $finders []= " ". $this->objtype .".". $qry['col']  ." ". $qry['comp'] .' "'. htmlentities($qry['val'], ENT_QUOTES) .'" ';
      }
      $sql .= join(' AND ', $finders);
    }
    

    if(count($this->order) > 0) {
      if(count($all_finder_cols) > 0 || count($native_finder_cols) > 0)
        $sql .= " AND ";
      else
        $sql .= " WHERE ";
      $sortJoins = array();
      $order_params = array();
      foreach ($this->order as $field => $dir) {
        if(!in_array($field, $this->nativeFields)) {
          $sortJoins[] = $this->_getIdxCol($field, false) .".docid = ". $this->objtype .".id ";
          $order_params[]= $this->_getIdxCol($field) ." ". $dir;
        } else {
          $order_params[]= $this->objtype .".". $field ." ". $dir;
        }
      }
      $sql .= join(" AND ", $sortJoins);
      $sql .= " ORDER BY ";
      $sql .= join(", ", $order_params);
    }

    if($this->limit != false && $this->limit > 0) {
      $sql .= " LIMIT ". $this->limit ." ";
    }
    if($this->offset != false && $this->offset > 0) {
      $sql .= " OFFSET ". $this->offset ." ";
    }
    $sql .= ";";

    return $sql;
  }

  protected function _getIdxCol($column, $appendCol=true) {
    $col = $this->objtype ."_". $column ."_idx";
    if($appendCol) {
      $col .= ".". $column;
    }
    return $col;
  }
}
class Pager implements IteratorAggregate {
  protected $finder = null;
  public $pageSize = 10;
  public $currentPage = 1;
  public $currentDataPage = 0;
  public $pages = 0;
  public $pageKey;
  public $baseUrl;

  function __construct($finder, $params=array(), $rootUrl='/', $pageSize=10, $pageKey='page') {
    if($finder instanceof ModelFinder) {
      $this->finder = $finder;
    } 
    else if(is_string($finder)) {
      $this->finder = get($finder);
    } 
    else {
      throw new Exception("You must specify a model name or finder object.");
    }
    if(ends_with('/', $rootUrl) )
      $this->baseUrl = $rootUrl;
    else
      $this->baseUrl = $rootUrl.'/';
    $this->pageSize = $pageSize;
    $this->pageKey = $pageKey;
    if(count($params) > 0) {
      $this->fromParams($params);
    }
    $this->pages = $this->pageCount(); 
  }

  public function fromParams($params) {
    list($key, $page) = array_slice($params, -2);
    if($key == $this->pageKey && is_numeric($page)) {
      $this->currentPage = intVal($page);
      $this->currentDataPage = $this->currentPage - 1;
    }
  }

  public function count() { 
    return $this->finder->limit(0)->offset(0)->count();
  }

  public function pageCount() { 
    $total = $this->count();
    return intVal(ceil( $total / $this->pageSize ));
  }

  public function items() {
    $this->finder->limit($this->pageSize)->offset(($this->currentDataPage * $this->pageSize));
    return $this->finder->fetch();
  }

  public function getIterator() { 
    return new ArrayIterator( $this->items() );
  }
  
  public function renderPager($className='pager', $currentClass='current', $inactiveClass='inactive', $toggleClass='toggle') {
    $html = '<div class="'.$className.'">';
    
    $html .= '<a href="'.$this->baseUrl.'page/';
    if($this->currentDataPage == 0) {
      $html .=  '1" class="'.$inactiveClass.' '.$toggleClass;
    } else {
      $html .=  $this->currentDataPage.'" class="'.$toggleClass;
    }
    $html .='"><span>&laquo; Prev</span></a>';
    
    for ($i=1; $i <= $this->pages; $i++) { 
      $html .= '<a href="'.$this->baseUrl.'page/'.$i.'"';
      if($i == $this->currentPage)
        $html .=' class="'.$currentClass.'"';
      $html .='><span>'.$i.'</span></a>';
    }
    
    $html .= '<a href="'.$this->baseUrl.'page/';
    if($this->currentPage == $this->pages) {
      $html .=  $this->currentPage.'" class="'.$inactiveClass.' '.$toggleClass;
    } else {
      $html .=  ($this->currentPage +1).'" class="'.$toggleClass;
    }
    $html .='"><span>Next &raquo;</span></a>';

    return $html."</div>";
  }
}

class Template {
  public $context;
  private $path;
  private $silent;
  
  function __construct($templatePath, $vars=array(), $swallowErrors=true) {
    $this->path = $templatePath;
    $this->context = $vars;
    $this->silent = $swallowErrors;
  }

  function __set($key, $value) {
    $this->context[$key] = $value;
  }

  function __get($key) {
    if(array_key_exists($key, $this->context)) {
      return $this->context[$key];
    }
    else {
      if($this->silent)
        return "";
      else
        throw new Exception("$key does not exist in template: ".$this->templatePath);
    }
  }

  function __call($name, $args=array()) {
    if($name == 'render') {
      @list($view, $params) = $args;
      if(strpos($view, '.php') < 1) $view .= ".php";
      $tmp = new Template($view, $this->context);
      $inlineContent = $tmp->fetch($params);
      $this->context = $tmp->context; 
      return $inlineContent;
    }
    else if(function_exists($name)) {
      if(is_array($args)) {
        return call_user_func_array($name, $args);
      }
      else {
        return call_user_func($name, $args);
      }
    }
    else if($this->silent) {
      return "";
    }
    else {
      throw new Exception("Method $name doesn't exist!!");
    }
  }

  function assign($key, $value) {
    $this->context[$key] = $value;
  }

  function display($params=array()) {
    echo $this->fetch($params);
  }

  function fetch($params=array()) {
    if(is_array($params))
      $this->context = array_merge($this->context, $params);
    ob_start();
    extract( $this->context, EXTR_PREFIX_INVALID, 'ss' ); 
    if (FORCESHORTTAGS) { 
      echo eval('?>'.preg_replace("/;*\s*\?>/", "; ?>", str_replace('<?=', '<?php echo ', file_get_contents($this->path))));
    } else {
      include $this->path; 
    }
    $buffer = ob_get_contents();
    @ob_end_clean();

    return $buffer;
  }

}
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