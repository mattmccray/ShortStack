<?php
 
class Document extends CoreModel {

  public $id = null;
  protected $rawData = null;
  protected $indexes = array();

  function __construct($dataRow=null) {
    parent::__construct($dataRow);
    if($dataRow != null) {
      $this->rawData = $dataRow['data'];
      $this->data = false;
      $this->id = $dataRow['id'];
    } else {
      $this->rawData = null;
      $this->data = array();
      $this->id = null;
    }
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
  
  public function has($key) {
    if(!$this->data) $this->_deserialize();
    return array_key_exists($key, $this->data);
  }

  public function save() {
    if($this->isDirty) {
      $this->beforeSave();
      if($this->isNew) { // Insert
        $this->beforeCreate();
        $this->_serialize();
        $sql = 'INSERT INTO '.$this->modelName.' ( data ) VALUES ( "'. $this->rawData .'" );';
        $statement = DB::Query($sql);
        $result = DB::Query('SELECT last_insert_rowid() as last_insert_rowid')->fetch(); // Get the record's generated ID...
        $this->id = $result['last_insert_rowid'];
        Document::Reindex($this->modelName, $this->id);
        $this->afterCreate();
      } else { // Update
        $this->_serialize();
        $sql = "UPDATE ".$this->modelName.' SET data="'.htmlentities( json_encode($this->data), ENT_QUOTES).'" WHERE id = '. $this->id .';';
        $statement = DB::Query($sql);
        $index_changed = array_intersect($this->changedFields, array_keys(Document::GetIndexesFor($this->modelName)));
        if(count($index_changed) > 0)  // Only if an indexed field has changed
          Document::Reindex($this->modelName, $this->id);
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
    Document::Remove($this->modelName, $this->id);
    $this->afterDestroy();
    return $this;
  }
  
  public function _defineDocumentFromModel() {
    Document::Define( $this->modelName, $this->indexes, false);
  }
  
  protected function beforeDeserialize() {}
  protected function afterDeserialize() {}
  
  // If you'd rather store the data as something else (XML, say) you can override these methods
  protected function deserialize($source) { // Must return an associative array
    return json_decode($source, true);
  }
  protected function serialize($source) { // Must return a string
    return json_encode($source);
  }

  // Used internally only... Triggers callbacks.
  private function _serialize() {
    $this->beforeSerialize();
    $this->rawData = htmlentities( $this->serialize( $this->data ), ENT_QUOTES );
    $this->afterSerialize(); // ??: Should the results be passed in to allow massaging?
    return $this;
  }
  private function _deserialize() {
    $this->beforeDeserialize();
    $this->data = $this->deserialize( html_entity_decode($this->rawData, ENT_QUOTES) );
    $this->afterDeserialize(); // ??: Should the results be passed in to allow massaging?
    return $this;
  }
  
  public function to_array($exclude=array()) {
    $attrs = array( 'id'=>$this->id );
    foreach($this->data as $col=>$value) {
      if(!in_array($col, $exclude)) {
        $attrs[$col] = $this->$col;
      }
    }
    return $attrs;
  }
  
  // Static Methods
  
  private static $_document_indexes_ = array();
  
  public static function Register($class) {
    $doc = new $class();
    $doc->_defineDocumentFromModel();
  }
  
  public static function Define($name, $indexes=array(), $createClass=true) {
    Document::$_document_indexes_[$name] = array_merge($indexes, array()); // a hokey way to clone an array
    if($createClass)
      eval("class ". $name ." extends Document {  }");
  }
  
  public static function InitializeDatabase() {
    // Loop through all the docs and create tables and index tables...
    foreach( Document::$_document_indexes_ as $docType => $indexes) {
      $tableSQL = "CREATE TABLE IF NOT EXISTS ". $docType ." ( id INTEGER PRIMARY KEY, data TEXT, created_on TIMESTAMP, updated_on TIMESTAMP );";
      DB::Query( $tableSQL );
      foreach ($indexes as $field=>$fType) {
        $indexSQL = "CREATE TABLE IF NOT EXISTS ". $docType ."_". $field ."_idx ( id INTEGER PRIMARY KEY, docid INTEGER, ". $field ." ". $fType ." );";
        DB::Query( $indexSQL );
      }
    }
  }
  
  public static function Reindex($doctype=null, $id=null) {
    // Loop through all the records, deserialize the data column and recreate the index rows
    $docs = array();
    // Step one: Fetch all the documents to update...
    if($doctype != null) {
      if(!array_key_exists($doctype, Document::$_document_indexes_)) {
        $tmp = new $doctype();
        $tmp->_defineDocumentFromModel();
      }
      if($id != null) {
        $sql = "SELECT * FROM ".$doctype." WHERE id = ". $id .";";
      } else {
        $sql = "SELECT * FROM ".$doctype.";";
      }
      $results = DB::FetchAll($sql);
      foreach ($results as $row) {
        $docs[] = new $doctype($row);
      }
    } else {
      // Do 'em all!
      foreach( Document::$_document_indexes_ as $docType => $indexes) {
        $sql = "SELECT * FROM ".$doctype."";
        $results = DB::FetchAll($sql);
        foreach ($results as $row) {
          $docs[] = new $doctype($row);
        }
      }
    }
    // Step two: Loop through them and delete, then rebuild index rows
    foreach ($docs as $doc) {
      $indexes = Document::GetIndexesFor($doc->modelName);
      if(count($indexes) > 0) {
        foreach ($indexes as $field=>$fType) {
          // TODO: Optimize as single transactions?
          $indexTable = $doc->modelName ."_". $field ."_idx";
          $sql = "DELETE FROM ". $indexTable ." WHERE docid = ". $doc->id .";";
          $results = DB::Query($sql);
          $sql = "INSERT INTO ". $indexTable ." ( docid, ". $field ." ) VALUES (".$doc->id.', "'.htmlentities($doc->{$field}, ENT_QUOTES).'" );';
          $results = DB::Query($sql);
        }
      } else {
        echo "! No indexes for ". $doc->modelName ."\n";
      }
    }
  }
  
  public static function Get($doctype, $id) {
    $sql = "SELECT * FROM ".$doctype." WHERE id = ". $id ." LIMIT 1;";
    $results = DB::FetchAll($sql);
    $docs = array();
    foreach ($results as $row) {
      $docs[] = new $doctype($row);
    }
    return @$docs[0];
  }
 
  public static function Find($doctype) {
    return new DocumentFinder($doctype);
  }
  
  // Doesn't fire callbacks!
  public static function Remove($doctype, $id) {
    $sql = "DELETE FROM ".$doctype." WHERE id = ". $id .";";
    DB::Query($sql);
    foreach ( Document::GetIndexesFor($doctype) as $field=>$fType) {
      $sql = "DELETE FROM ".$doctype."_".$field."_idx WHERE docid = ". $id .";";
      DB::Query($sql);
    }
  }
  
  public static function Count($doctype) {
    $sql = "SELECT count(id) as count FROM ".$doctype.";";
    $statement = DB::Query($sql);
    if($statement) {
      $results = $statement->fetchAll(); // PDO::FETCH_ASSOC ???
      return @(integer)$results[0]['count'];
    } else { // Throw an ERROR?
      return 0;
    }
  }

  public static function GetIndexesFor($doctype) {
    return Document::$_document_indexes_[$doctype];
  }
  
}


class DocumentFinder extends CoreFinder {

  // Document _buildSQL
  protected function _buildSQL() {
    // TODO: Implment OR logic...

    $all_order_cols = array();
    foreach($this->order as $field=>$other) {
      $all_order_cols[] = $this->_getIdxCol($field, false);
    }
    $all_finder_cols = array();
    foreach($this->finder as $qry) {
      $all_finder_cols []= $this->_getIdxCol($qry['col'], false);
    }
    // Also for OR?

    $tables = array_merge(array($this->objtype), $all_order_cols);
    $sql = "SELECT ". $this->objtype .".* FROM ". join(', ', $tables) ." ";

    if(count($this->finder) > 0) {
      $sql .= "WHERE ". $this->objtype .".id IN (";
      $sql .= "SELECT ". $all_finder_cols[0] .".docid FROM ". join(', ', $all_finder_cols). " ";
      $sql .= "WHERE ";
      $finders = array();
      foreach($this->finder as $qry) {
        $finders []= " ". $this->_getIdxCol($qry['col'])  ." ". $qry['comp'] .' "'. htmlentities($qry['val'], ENT_QUOTES) .'" ';
      }
      $sql .= join(' AND ', $finders);
      $sql .= ") ";
    }
    if(count($this->order) > 0) {
      $sql .= "AND ";
      $sortJoins = array();
      foreach ($this->order as $field => $dir) {
        $sortJoins[] = $this->_getIdxCol($field, false) .".docid = ". $this->objtype .".id ";
      }
      $sql .= join(" AND ", $sortJoins);
      $sql .= " ORDER BY ";
      $order_params = array();
      foreach ($this->order as $field => $dir) {
        $order_params[]= $this->_getIdxCol($field) ." ". $dir;
      }
      $sql .= join(", ", $order_params);
    }
    if($this->limit != false && $this->limit > 0) {
      $sql .= " LIMIT ". $this->limit ." ";
    }
    $sql .= ";";
//    print_r($sql);
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
