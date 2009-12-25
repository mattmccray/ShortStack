<?php

class CoreModel {

    public $modelName = null;
    
    protected $data = false;
    protected $isNew = false;
    protected $isDirty = false;
    protected $hasMany = array();
    protected $belongsTo = array();
    protected $changedFields = array();

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
        $this->isDirty = true;
      }
    }
    
    public function has($key) {
      return array_key_exists($key, $this->data);
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
      if(array_key_exists('document', $def)) {
        $mdlClass = $def['document'];
        $fk = strtolower($this->modelName)."_id";
        return Document::Find($mdlClass)->where($fk)->eq($this->id);
      }
      else if(array_key_exists('model', $def)) {
        $mdlClass = $def['model'];
        $fk = strtolower($this->modelName)."_id";
        return Model::Find($mdlClass, $fk." = ".$this->id);
      } else {
        throw new Exception("A relationship has to be defined as a model or document");
      }
    }

    protected function _handleBelongsTo($method, $args) {
      $def = $this->belongsTo[$method];
      if(array_key_exists('document', $def)) {
        $mdlClass = $def['document'];
        $fk = strtolower($mdlClass)."_id";
        return Document::Find($mdlClass)->where('id')->eq($this->$fk).get();
      }
      else if(array_key_exists('model', $def)) {
        $mdlClass = $def['model'];
        $fk = strtolower($mdlClass)."_id";
        return Model::Get($mdlClass, $this->$fk);
      } else {
        throw new Exception("A relationship has to be defined as a model or document");
      }
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

    // Override these two
    public function save() {}
    public function destroy(){}
    
    // Callbacks
    protected function beforeSave() {}
    protected function afterSave() {}
    protected function beforeCreate() {}
    protected function afterCreate() {}
    protected function beforeDestroy() {}
    protected function afterDestroy() {}
    protected function beforeSerialize() {}
    protected function afterSerialize() {}

    protected function getChangedValues() {
      $results = array();
      foreach($this->changedFields as $key=>$fieldname) {
        $results[$fieldname] = $this->$fieldname;
      }
      return $results;
    }
    
    // ????
    public function assignTo($modelOrName, $id=null) {
      if(is_string($modelOrName)) {
        $fk = strtolower($modelOrName)."_id";
        $this->$fk = $id;
      } else {
        $fk = strtolower( get_class($modelOrName))."_id";
        $this->$fk = $modelOrName->id;
      }
      return $this;
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
      return CoreModel::toJSON( $this->to_array($exclude) );
    }
      
    // = Static methods / variables =    
    static public function toJSON($obj) {
      if(is_array($obj)) {
        $data = array();
        foreach($obj as $idx=>$mdl) {
          if($mdl instanceof CoreModel)
            $data[] = $mdl->to_array();
          else
            $data[$idx] = $mdl;
        }
        return json_encode($data);
        
      } else if( $obj instanceof CoreModel ) {
        return json_encode($obj->to_array());
      
      } else {
        return json_encode($obj);
      }
    }
}


class Model extends CoreModel {
  
  protected $schema = array();
  
  public function updateValues($values=array()) {
    $valid_atts = array_keys( $this->schema );
    foreach($values as $key=>$value) {
      if(in_array($key, $valid_atts)) $this->$key = $value;
    }
    return $this;
  }
  
  protected function getChangedValues() {
    $results = array();
    foreach($this->changedFields as $key=>$fieldname) {
      $results[$fieldname] = '"'.htmlentities($this->$fieldname, ENT_QUOTES).'"';
    }
    return $results;
  }
  
  public function save() {
    if($this->isDirty) {
      $this->beforeSave();
      if($this->isNew) {
        // Create
        $this->beforeCreate();
        $values = $this->getChangedValues();
        $sql = "INSERT INTO ".$this->modelName." (".join($this->changedFields, ', ').") VALUES (".join($values, ', ').");";
        $statement = DB::Query($sql);
        // Get the record's generated ID...
        $result = DB::Query('SELECT last_insert_rowid() as last_insert_rowid')->fetch();
        $this->data['id'] = $result['last_insert_rowid'];
        $this->afterCreate();
      } else {
        // Update
        $values = $this->getChangedValues();
        $fields = array();
        foreach($values as $field=>$value) {
          $fields[] = $field." = ".$value;
        }
        $sql = "UPDATE ".$this->modelName." SET ". join($fields, ", ") ." WHERE id = ". $this->id .";";
        $statement = DB::Query($sql);
      }
      $this->changedFields = array();
      $this->isDirty = false;
      $this->isNew = false;
      $this->afterSave();
    }
    return $this;
  }
  
  // Warning: Like Han solo, this method doesn't fuck around, it will shoot first.
  public function destroy() {
    $this->beforeDestroy();
    Model::Remove($this->modelName, $this->id);
    $this->afterDestroy();
    return $this;
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
     
     return DB::Query( $sql );
   }

   public static function Get($modelName, $id) {
     $sql = "SELECT * FROM ".$modelName." WHERE id = ". $id ." LIMIT 1;";
     $results = DB::FetchAll($sql);
     $mdls = array();
     foreach ($results as $row) {
       $mdls[] = new $modelName($row);
     }
     return @$mdls[0];
   }
   
   public static function Find($modelName) {
     return new ModelFinder($modelName);
   }

   // Does NOT fire callbacks...
   public static function Remove($modelName, $id) {
     $sql = "DELETE FROM ".$modelName." WHERE id = ". $id .";";
     DB::Query($sql);
   }

   static public function Count($className) {
     $sql = "SELECT count(id) as count FROM ".$className.";";
     $statement = DB::Query($sql);
     if($statement) {
       $results = $statement->fetchAll(); // PDO::FETCH_ASSOC ???
       return @(integer)$results[0]['count'];
     } else { // Throw an ERROR?
       return 0;
     }
   }
}


class ModelFinder extends CoreFinder {

  // Model _buildSQL
  protected function _buildSQL() {
    $sql = "SELECT * FROM ". $this->objtype ." ";
    // TODO: Implment OR logic...
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
    $sql .= " ;";
    return $sql;
  }

}