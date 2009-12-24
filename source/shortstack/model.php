<?php

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