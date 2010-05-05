<?php
/**
 * Base class for all Database Models (ORM).
 * @todo Model Todos
 *     - belongsTo should memoize the associated model.
 */
class Model {
  /**
   *  This is automatically set based on the Class name.
   */
  public $modelName = null;
  public $errors = array();
  /**#@+
   * @ignore
   */
  protected $data = false;
  protected $isNew = false;
  protected $isDirty = false;
  /**#@-*/
  /**
   *  This will contain and changed field names after update() is called, before save().
   */
  protected $changedFields = array();
  /**
   * You must override $schema in your sub class.
   */
  protected $schema = array();
  /**
   * If you want to associate this model to another, as hasMany, override this property.
   */
  protected $hasMany = array();
  /**
   * If you want to associate this model to another, as belongsTo, override this property.
   */
  protected $belongsTo = array();
  /**
   * Override this and populate with the validations you want to occur before saving.
   */
  protected $validates = array();
  
  /**
   *
   */
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
        // Stringifies everything, is this really ideal? TODO: Only stringify if is_string()
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
      // Validations
      $isValid = $this->isValid();
      if(!$isValid) return false;
      // Persistance
      $this->beforeSave();
      if($this->isNew) { // Create
        $this->beforeCreate();
        $result = $this->_handleSqlInsert();
      }
      else { // Update
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

  public function kill(){ // WARNING: This doesn't trigger the callbacks!
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
  
  /**#@+
   * @ignore  Kinda dangerous
   */
  public function blastUpdate($values=array()){
    $result = true;
    foreach($values as $key=>$value) {
      $this->$key = $value;
    }
    if($this->isNew) { // Create
      $result = $this->_handleSqlInsert();
    }
    else { // Update
      $result = $this->_handleSqlUpdate();
    }
    $this->changedFields = array();
    $this->isDirty = false;
    return $result;
  }

  /**#@+
   * @ignore
   */
  function __get($key) {
    if($this->data) {
      return html_entity_decode(@$this->data[$key], ENT_QUOTES );
    }
//    return NULL;
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
      return NULL; // FIXME: What to do here? Throw exception when __call is bad?
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
    //TODO: Type and schema checking...
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
    // Trigger to auto-insert created_on
    if(array_key_exists('created_on', $this->schema)) {
      $triggerSQL = "CREATE TRIGGER generate_". $this->modelName ."_created_on AFTER INSERT ON ". $this->modelName ." BEGIN UPDATE ". $this->modelName ." SET created_on = DATETIME('NOW') WHERE rowid = new.rowid; END;";
      if(DB::Query( $triggerSQL ) == false) $statement = false;
    }
    // Trigger to auto-insert updated_on
    if(array_key_exists('updated_on', $this->schema)) {
      $triggerSQL = "CREATE TRIGGER generate_". $this->modelName ."_updated_on AFTER UPDATE ON ". $this->modelName ." BEGIN UPDATE ". $this->modelName ." SET updated_on = DATETIME('NOW') WHERE rowid = new.rowid; END;";
      if(DB::Query( $triggerSQL ) == false) $statement = false;
    }
//    if($statement == false) $this->errors[]= "SQL Error"; //.DB::GetLastError()[2]; // [2] ??
    return ($statement != false);
  }

  protected function _handleSqlInsert() {
    $values = $this->getChangedValues();
    $sql = "INSERT INTO ".$this->modelName." (".join($this->changedFields, ', ').") VALUES (".join($values, ', ').");";
    $statement = DB::Query($sql);
    if($statement == false) return false;
    // Get the record's generated ID...
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
        else// if($rule == 'nullify') // Could also use 'cascade'=>'ignore'???
          $matches->update(array($fk=>null)); // nullifies
      }
    }
    return true;
  }
  /**#@-*/

  // Callbacks
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

  // Does NOT fire callbacks...
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
