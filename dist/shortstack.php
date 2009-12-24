<?php
 
// ShortStack v0.8.1
// By M@ McCray
// http://github.com/darthapo/ShortStack


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


// = Helpers =

function url_for($controller) {
  return BASEURI . $controller;
}

function link_to($controller, $label, $className="") {
  return '<a href="'. url_for($controller) .'" class="'. $className .'">'. $label .'</a>';
}

function slugify($str) {
   // only take alphanumerical characters, but keep the spaces and dashes too...
   $slug = preg_replace("/[^a-zA-Z0-9 -]/", "", trim($str));
   $slug = preg_replace("/[\W]{2,}/", " ", $slug); // replace multiple spaces with a single space
   $slug = str_replace(" ", "-", $slug); // replace spaces by dashes
   $slug = strtolower($slug);  // make it lowercase
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

function use_helper($helper) {
  if(! strpos($helper, 'elper') > 0) $helper .= "_helper";
  require_once( ShortStack::helperPath($helper) );
}

// Used by the Dispatcher
function getBaseUri() {
	return str_replace("/".$_SERVER['QUERY_STRING'], "/", array_shift(explode("?", $_SERVER['REQUEST_URI'])));
}

// = Dispatcher =

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
// = DB =

class DB
{
  static protected $pdo; // Public for now...
  
  static public function connect($conn, $user="", $pass="", $options=array()) {
    self::$pdo = new PDO($conn, $user, $pass, $options);
  }
  
  static public function query($sql_string) {
    return self::$pdo->query($sql_string);
  }
  
  static public function getLastError() {
    return self::$pdo->errorInfo();
  }

  static public function fetchAll($sql_string) {
    $statement = self::query($sql_string);
    return $statement->fetchAll();
  }
  
  static public function ensureNotEmpty() {
    $statement = self::query('SELECT name FROM sqlite_master WHERE type = \'table\'');
    $result = $statement->fetchAll();
    if( sizeof($result) == 0 ){
      define("EMPTYDB", true);
      throw new EmptyDbException("Database has no tables.");
    } else {
      define("EMPTYDB", false);
    }
  }
}
// = Model =

class Model {

    protected $data;
    protected $isNew;
    protected $hasChanged;
    protected $modelName;
    protected $hasMany = array();
    protected $belongsTo = array();
    protected $changedFields = array();
    protected $schema = array();

    function __construct($dataRow=null) {
      $this->modelName = get_class($this);
      if($dataRow != null) {
        $this->data = $dataRow;
        $this->isNew = false;
      } else {
        $this->data = array();
        $this->isNew = true;
      }
      $this->hasChanged = false;
    }

    function __get($key) {
      if($this->data) {
        return html_entity_decode($this->data[$key], ENT_QUOTES );
      }
    }

    function __set($key, $value) {
      // TODO: Validate against schema to ensure fields exist?
      $value = stripslashes($value);
      if(@ $this->data[$key] != htmlentities($value, ENT_QUOTES)) {
        $this->data[$key] = $value;
        $this->changedFields[] = $key;
        $this->hasChanged = true;
      }
    }
    
    function __call( $method, $args ) {
      // TODO: Support relationships here...
      if(array_key_exists($method, $this->hasMany)) {
        return $this->_handleHasMany($method, $args);
      }
      else if(array_key_exists($method, $this->belongsTo)) {
        return $this->_handleBelongsTo($method, $args);
      }
      // look for 'add'+hasManyName
      // look for 'set'+belongsToName
      else {
        return NULL; // FIXME: What to do here?
      }
    }
    
    protected function _handleHasMany($method, $args) {
      $def = $this->hasMany[$method];
      $mdlClass = $def['model'];
      $fk = strtolower($this->modelName)."_id";
      return Model::Find($mdlClass, $fk." = ".$this->id);
    }

    protected function _handleBelongsTo($method, $args) {
      $def = $this->hasMany[$method];
      $mdlClass = $def['model'];
      $fk = strtolower($mdlClass)."_id";
      return Model::FindById($mdlClass, "id = ".$this->$fk);
    }
    
    public function updateValues($values=array()) {
      $valid_atts = array_keys( $this->schema );
      foreach($values as $key=>$value) {
        if(in_array($key, $valid_atts)) $this->$key = $value;
      }
    }

    public function save() {
      if($this->isNew) {
        // Create
        $values = $this->getChangedValues();
        $sql = "INSERT INTO ".$this->modelName." (".join($this->changedFields, ', ').") VALUES (".join($values, ', ').");";
        $statement = DB::query($sql);
        // Get the record's generated ID...
        $result = DB::query('SELECT last_insert_rowid() as last_insert_rowid')->fetch();
        $this->data['id'] = $result['last_insert_rowid'];
        
      } else {
        // Update
        $values = $this->getChangedValues();
        $fields = array();
        foreach($values as $field=>$value) {
          $fields[] = $field." = ".$value;
        }
        $sql = "UPDATE ".$this->modelName." SET ". join($fields, ", ") ." WHERE id = ". $this->id .";";
        $statement = DB::query($sql);
      }
      $this->changedFields = array();
      $this->hasChanged = false;
      $this->isNew = false;
    }
    
    //TODO: Before Save, After Save, Before Create, After Create, Before Destroy, After Destroy

    // Warning: Like Han solo, this method doesn't fuck around, it will shoot first.
    public function destroy() {
      $sql = "DELETE FROM ".$this->modelName." WHERE id = ". $this->id .";";
      $statement = DB::query($sql);
    }

    protected function getChangedValues() {
      $results = array();
      foreach($this->changedFields as $key=>$fieldname) {
        $results[$fieldname] = '"'.htmlentities($this->$fieldname, ENT_QUOTES).'"';
      }
      return $results;
    }
    
    public function assignTo($modelOrName, $id=null) {
      if(is_string($modelOrName)) {
        $fk = strtolower($modelOrName)."_id";
        $this->$fk = $id;
      } else {
        $fk = strtolower( get_class($modelOrName))."_id";
        $this->$fk = $modelOrName->id;
      }
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
    
    
    // = SQL Builder Helper Methods =

    public function _createTableForModel() {
      return self::CreateTableForModel($this->modelName, $this);
    }
    static public function CreateTableForModel($modelName, $mdlInst=false) {
      $sql = "CREATE TABLE IF NOT EXISTS ";
      $sql.= $modelName ." ( ";
      $cols = array();
      if(! $mdlInst) $mdlInst = new $modelName;

      $modelColumns = $mdlInst->schema;
      $modelColumns["id"] = 'INTEGER PRIMARY KEY';
      
      foreach($modelColumns as $name=>$def) {
        $cols[] = $name ." ". $def;
      }
      $sql.= join($cols, ', ');
      $sql.= " );";
      
      return DB::query( $sql );
    }

    public function _count($whereClause=null, $sqlPostfix=null) {
      return self::Count($this->modelName, $whereClause, $sqlPostfix);
    }
    static public function Count($className, $whereClause=null, $sqlPostfix=null) {
      $sql = "SELECT count(id) as count FROM ".$className;
      if($whereClause != null) $sql .= " WHERE ".$whereClause;
      if($sqlPostfix != null)  $sql .= " ".$sqlPostfix;
      $sql .= ';';
      $statement = DB::query($sql);
      $results = $statement->fetchAll(); // PDO::FETCH_ASSOC ???
      return $results[0]['count'];
    }

    public function _query($whereClause=null, $sqlPostfix='', $selectClause='*') {
      if(@ strpos(strtolower(' '.$sqlPostfix), 'order') < 1 && isset($this->defaultOrderBy)) {
        $sqlPostfix .= " ORDER BY ".$this->defaultOrderBy;
      }
      return self::Query($this->modelName, $whereClause, $sqlPostfix, $selectClause);
    }
    static public function Query($className, $whereClause=null, $sqlPostfix='', $selectClause='*') {
      $sql = "SELECT ". $selectClause ." FROM ".$className." ";
      if($whereClause != null) {
        $sql .= " WHERE ". $whereClause;
      }
      if(@ strpos(strtolower(' '.$sqlPostfix), 'order') < 1) {
        // God I hate this...
        $mdl = new $className();
        if(isset($mdl->defaultOrderBy)) {
          $sql .= " ORDER BY ".$mdl->defaultOrderBy." ";
//          echo $sql;
        }
      }
      if($sqlPostfix != '') {
        $sql .= " ". $sqlPostfix;
      }
      $sql .= ';';
      $statement = DB::query($sql);
      if(!$statement) {
        $errInfo = DB::getLastError();
        throw new Exception("DB Error: ".$errInfo[2] ."\n". $sql);
      }
      return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function _find($whereClause=null, $sqlPostfix='', $selectClause='*') {
      return self::Find($this->modelName, $whereClause, $sqlPostfix, $selectClause);
    }
    static public function Find($className, $whereClause=null, $sqlPostfix='', $selectClause='*') {
      $results = self::Query($className, $whereClause, $sqlPostfix, $selectClause);
      $models = array();
      foreach($results as $row) {
        $models[] = new $className($row);
      }
      return $models;
    }

    public function _findFirst($whereClause=null, $sqlPostfix='') {
      return self::FindFirst($this->modelName, $whereClause, $sqlPostfix);
    }
    static public function FindFirst($className, $whereClause=null, $sqlPostfix='') {
      $models = self::Find($className, $whereClause, $sqlPostfix." LIMIT 1");
      return @ $models[0];
    }

    public function _findById($id) {
      return self::FindById($this->modelName);
    }
    static public function FindById($className, $id) {
      return self::FindFirst($className, "id = ".$id);
    }
    
    // = Static methods / variables =    
    
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
// = Template =

class Template {
  
  private $path;
  private $context;
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
    } else {
      if($this->silent)
        return "";
      else
        throw new Exception("$key does not exist in template: ".$this->templatePath);
    }
  }
  
  function __call($name, $args) {
    if($this->silent)
      return "";
    else
      throw new Exception("Method $name doesn't exist!!");
  }
  
  function assign($key, $value) {
    $this->context[$key] = $value;
  }
  
  function display($params=array()) {
    echo $this->fetch($params);
  }
  
  function fetch($params=array()) {
    extract(array_merge($params, $this->context)); // Make variables local!
    ob_start();
    if (FORCESHORTTAGS) { // If the PHP installation does not support short tags we'll do a little string replacement, changing the short tags to standard PHP echo statements.
      echo eval('?>'.preg_replace("/;*\s*\?>/", "; ?>", str_replace('<?=', '<?php echo ', file_get_contents($this->path))));
    } else {
      include($this->path); // include() vs include_once() allows for multiple views with the same name
    }
    $buffer = ob_get_contents();
    @ob_end_clean();
    return $buffer;
  }
  
}
// = Startup =

if( isset($shortstack_config) ) {
  if(@ is_array($shortstack_config['helpers']['autoload']) ) {
    foreach($shortstack_config['helpers']['autoload'] as $helper) {
      require_once( ShortStack::helperPath($helper."_helper"));
    }
  }
  if(@ $shortstack_config['db']['autoconnect'] ) {
    DB::connect( $shortstack_config['db']['engine'].":".$shortstack_config['db']['database'] ); 
  }
  if(@ $shortstack_config['db']['verify'] ) {
    DB::ensureNotEmpty();
  }
  define('FORCESHORTTAGS', $shortstack_config['views']['force_short_tags']);
} else {
  throw new NotFoundException("ShortStack configuration missing!");
}

//echo "<pre>";
//print_r($_SERVER);
//echo "</pre>";