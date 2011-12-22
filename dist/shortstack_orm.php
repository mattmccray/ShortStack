<?php

// ShortStack v0.10
// By M@ McCray
// (All comments have been stripped see:)
// http://github.com/darthapo/ShortStack

define("SHORTSTACK_VERSION", "0.10");

class ShortStack {
  public static $Version = SHORTSTACK_VERSION;
  
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
  public static $VERSION = "";
  
  
  private static $doctypeCache = array();
}

class Redirect extends Exception { }
class FullRedirect extends Exception { }
class EmptyDbException extends Exception { }
class NotFoundException extends Exception { }
class DbException extends Exception { }
class StaleCache extends Exception { }
class DB {
  static protected $pdo;
  static public $connectionString;
  static public $lastSQL;

  static public function Connect($conn, $user="", $pass="", $options=array()) {
    self::$connectionString = $conn;
    self::$pdo = new PDO($conn, $user, $pass, $options);
    return self::$pdo;
  }

  static public function Query($sql_string) {
    self::$lastSQL = $sql_string;
    return self::$pdo->query($sql_string);
  }

  static public function GetLastError() {
    return self::$pdo->errorInfo();
  }

  static public function FetchAll($sql_string, $fetch_type=null) {
    $statement = self::Query($sql_string);
    if($fetch_type == null) $fetch_type = PDO::FETCH_ASSOC;
    if($statement != false) {
      return $statement->fetchAll($fetch_type); 
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
      if(in_array($fieldname, $valid_atts) && !in_array($fieldname, $cleanChangedFields)) { 
        
        $results[$fieldname] = '"'.htmlentities($this->$fieldname, ENT_QUOTES).'"';
        $cleanChangedFields[] = $fieldname;
      }
    }
    $this->changedFields = $cleanChangedFields;
    return $results;
  }
  
  public function isValid() {
    $this->beforeValidation();
    $result = validate($this->data, $this->validates, $this->errors);
    $this->afterValidation();
    return $result;
  }

  public function save() {
    $result = true;
    if($this->isDirty) {
      
      $isValid = $this->isValid();
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
        if($this->isNew) {
          $this->isNew = false;
          $this->afterCreate();
        }
        $this->afterSave();
        $this->changedFields = array();
        $this->isDirty = false;
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
  
  
  public function blastUpdate($values=array()){
    $result = true;
    foreach($values as $key=>$value) {
      $this->$key = $value;
    }
    if($this->isNew) { 
      $result = $this->_handleSqlInsert();
    }
    else { 
      $result = $this->_handleSqlUpdate();
    }
    $this->changedFields = array();
    $this->isDirty = false;
    return $result;
  }
  function __get($key) {
    if($this->data) {
      return html_entity_decode(@$this->data[$key], ENT_QUOTES );
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

  static public function toJSON($obj, $excludes=array()) {
    if(is_array($obj)) {
      $data = array();
      foreach($obj as $idx=>$mdl) {
        if($mdl instanceof Model)
          $data[] = $mdl->to_array($excludes);
        else
          $data[$idx] = $mdl;
      }
      return json_encode($data);

    } else if( $obj instanceof Model ) {
      return json_encode($obj->to_array($excludes));

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
  
  public function to_sql() {
    return $this->_buildSQL();
  }
  
  public function to_json($ignoreCache=false, $exclude=array()) {
    return Model::toJSON( $this->fetch($ignoreCache), $exclude );
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
      $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
      $this->id = (int)$dataRow['id'];
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
  
  public function isValid() {
    if(!$this->data) $this->_deserialize();
    $this->beforeValidation();
    $result = validate($this->data, $this->validates, $this->errors);
    $this->afterValidation();
    return $result;
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
    if(!$this->data) $this->_deserialize();
    foreach($this->data as $col=>$value) {
      if(!in_array($col, $exclude)) {
        $attrs[$col] = $this->$col;
      }
    }
    return $attrs;
  }
  function __get($key) {
    if(!$this->data) { $this->_deserialize(); }
    return @$this->data[$key];
  }

  function __set($key, $value) {
    if(!$this->data) { $this->_deserialize(); }
    if(is_string($value)) {
      $value = stripslashes($value);
    } 
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
    $this->id = (int)$result['last_insert_rowid'];
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
    $this->_massageDataTypes();
    $this->afterDeserialize(); 
    return $this;
  }
  
  private function _massageDataTypes() {
    if($this->data) {
      foreach($this->data as $col=>$value) {
        if( is_numeric($value) ) {
          if( stripos($value, ".") ) {
             $value = floatval($value); 
          } else {
            $value = intval($value);
          }
          $this->data[$col] = $value;
        }
      }
    }
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
    

    $tables = array_unique(array_merge(array($this->objtype), $all_order_cols));
    
    if($isCount)
      $sql = "SELECT count(". $this->objtype .".id) as count FROM ". join(', ', $tables) ." ";
    else
      $sql = "SELECT ". $this->objtype .".* FROM ". join(', ', $tables) ." ";

    if(count($all_finder_cols) > 0) {
      $sql .= "WHERE ". $this->objtype .".id IN (";
      $sql .= "SELECT ". $all_finder_cols[0] .".docid FROM ". join(', ', array_unique($all_finder_cols)). " ";
      $sql .= " WHERE ";
      $finders = array();
      foreach($this->finder as $idx => $qry) {
        if(!in_array($qry['col'], $this->nativeFields)) {
          $finders []= " ". $this->_getIdxCol($qry['col'])  ." ". $qry['comp'] .' "'. htmlentities($qry['val'], ENT_QUOTES) .'" ';
          
          if($idx > 0 && $all_finder_cols[0] != $this->_getIdxCol($qry['col'], false))
            $finders []= $all_finder_cols[0] .".docid = ".$this->_getIdxCol($qry['col'], false).".docid";
          
        }
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

      if(count($sortJoins) > 0) {
        if(count($all_finder_cols) > 0 || count($native_finder_cols) > 0) {
          $sql .= " AND ";
        }
        else {
          $sql .= " WHERE ";
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
    $this->fromParams($params);
    $this->pages = $this->pageCount(); 
  }

  public function fromParams($params=array()) {
    if(count($params) >= 2) {
      list($key, $page) = array_slice($params, -2);
      if($key == $this->pageKey && is_numeric($page)) {
        $this->currentPage = intVal($page);
        $this->currentDataPage = $this->currentPage - 1;
      }
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
