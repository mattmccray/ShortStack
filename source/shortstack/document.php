<?php
 
class Document extends CoreModel {

  public $id = null;
  public $created_on = null;
  public $updated_on = null;
  
  protected $rawData = null;
  protected $indexes = array();

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
        $this->created_on = $this->updated_on = gmdate('Y-m-d H:i:s'); // Not official
        $statement = DB::Query($sql);
        if($statement == false) return false;
        $result = DB::Query('SELECT last_insert_rowid() as last_insert_rowid')->fetch(); // Get the record's generated ID...
        $this->id = $result['last_insert_rowid'];
        Document::Reindex($this->modelName, $this->id);
        $this->afterCreate();
      } else { // Update
        $this->_serialize();
        $sql = "UPDATE ".$this->modelName.' SET data="'. $this->rawData .'" WHERE id = '. $this->id .';';
        $statement = DB::Query($sql);
        if($statement == false) return false;
        $this->updated_on = gmdate('Y-m-d H:i:s'); // Not official
        $index_changed = array_intersect($this->changedFields, array_keys(Document::GetIndexesFor($this->modelName)));
        if(count($index_changed) > 0) { // Only if an indexed field has changed {
          // debug("Reindexing because these fields changed:");
          // debug($index_changed);
          Document::Reindex($this->modelName, $this->id);
        }
      }
      $this->changedFields = array();
      $this->isDirty = false;
      $this->isNew = false;
      $this->afterSave();
    }
    return true;
  }

  // TODO: Add some sort of RELOAD method...

  // Warning: Like Han solo, this method doesn't fuck around, it will shoot first.
  public function destroy() {
    $this->beforeDestroy();
    Document::Remove($this->modelName, $this->id);
    $this->afterDestroy();
    return $this;
  }
  
  public function _defineDocumentFromModel() {
    // TODO: If this->belongsTo, then auto create the needed index(es)?
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
  
  // Think this goes away
  // public static function Register($class) {
  //   $doc = new $class();
  //   $doc->_defineDocumentFromModel();
  // }
  
  public static function Define($name, $indexes=array(), $createClass=true) {
    Document::$_document_indexes_[$name] = array_merge($indexes, array()); // a hokey way to clone an array
    if($createClass)
      eval("class ". $name ." extends Document {  }");
  }
  
  // FIXME: Clean this up so that it runs in a single transaction/sql call???
  // TODO: Call ShortStack::LoadAllModels??? 
  public static function InitializeDatabase() {
    // Loop through all the docs and create tables and index tables...
    foreach( Document::$_document_indexes_ as $docType => $indexes) {
      $tableSQL = "CREATE TABLE IF NOT EXISTS ". $docType ." ( id INTEGER PRIMARY KEY, data TEXT, created_on DATETIME, updated_on DATETIME );";
      DB::Query( $tableSQL );
      // Trigger to auto-insert created_on
      $triggerSQL = "CREATE TRIGGER generate_". $docType ."_created_on AFTER INSERT ON ". $docType ." BEGIN UPDATE ". $docType ." SET created_on = DATETIME('NOW') WHERE rowid = new.rowid; END;";
      DB::Query( $triggerSQL );
      // Trigger to auto-insert updated_on
      $triggerSQL = "CREATE TRIGGER generate_". $docType ."_updated_on AFTER UPDATE ON ". $docType ." BEGIN UPDATE ". $docType ." SET updated_on = DATETIME('NOW') WHERE rowid = new.rowid; END;";
      DB::Query( $triggerSQL );
      // Create index tables
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
      // if(!array_key_exists($doctype, Document::$_document_indexes_)) { // don't think this matters anymore
      //   $tmp = new $doctype();
      //   $tmp->_defineDocumentFromModel();
      // }
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
        $sql = "SELECT * FROM ".$doctype.";";
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
    if(!array_key_exists($doctype, Document::$_document_indexes_)) {
      $doc = new $doctype();
      $doc->_defineDocumentFromModel();
    }
    return Document::$_document_indexes_[$doctype];
  }
  
}


class DocumentFinder extends CoreFinder {
  private $nativeFields = array('id','created_on','updated_on');
  
  // FIXME: All these loops really need an optimization pass...
  protected function _buildSQL($isCount=false) {
    // TODO: Implment OR logic...
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
    // Also for OR?

    $tables = array_merge(array($this->objtype), $all_order_cols);
    //TODO: Should it select the id, data, and datetime(created_on, 'localtime')???
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
    if($isCount) return $sql.";";

    if(count($this->order) > 0) {
      $sql .= " AND ";
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
