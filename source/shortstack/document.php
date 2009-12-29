<?php
 
class Document extends Model {
  public $id = null;
  public $created_on = null;
  public $updated_on = null;
  protected $rawData = null;
  protected $indexes = array();
  
  // It's best if you don't override this...
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
    // Force the $schema ??? To prevent overrides?
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
  
  public function getChangedValues() {
    $results = array();
    foreach($this->changedFields as $key=>$fieldname) {
      $results[$fieldname] = $this->$fieldname;
    }
    return $results;
  }
  
  public function reindex() {
    $wasSuccessful = true;
    foreach ($this->indexes as $field=>$fType) { // TODO: Optimize as single transactions?
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
  
  protected function _handleSqlCreate() {
    $mdlRes = parent::_handleSqlCreate();
    $idxRes = true;
    foreach ($this->indexes as $field=>$fType) { // Create index tables...
      $indexSQL = "CREATE TABLE IF NOT EXISTS ". $this->modelName ."_". $field ."_idx ( id INTEGER PRIMARY KEY, docid INTEGER, ". $field ." ". $fType ." );";
      if(DB::Query( $indexSQL ) == false) $idxRes = false;
    }
    return ($mdlRes != false && $idxRes != false);
  }
   
  protected function _handleSqlInsert() {
    $this->_serialize();
    $sql = 'INSERT INTO '.$this->modelName.' ( data ) VALUES ( "'. $this->rawData .'" );';
    $this->created_on = $this->updated_on = gmdate('Y-m-d H:i:s'); // Not official
    $statement = DB::Query($sql);
    if($statement == false) return false;
    $result = DB::Query('SELECT last_insert_rowid() as last_insert_rowid')->fetch(); // Get the record's generated ID...
    $this->id = $result['last_insert_rowid'];
    $this->reindex();
    return true;
  }
   
  protected function _handleSqlUpdate() {
    $this->_serialize();
    $sql = "UPDATE ".$this->modelName.' SET data="'. $this->rawData .'" WHERE id = '. $this->id .';';
    $statement = DB::Query($sql);
    if($statement == false) return false;
    $this->updated_on = gmdate('Y-m-d H:i:s'); // Not official
    $index_changed = array_intersect($this->changedFields, array_keys($this->indexes));
    if(count($index_changed) > 0) { // Only if an indexed field has changed
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
  
  protected function beforeDeserialize() {}
  protected function afterDeserialize() {}
  
  // If you'd rather store the data as something else (XML, say) you can override these methods
  protected function deserialize($source) { // Must return an associative array
    return json_decode($source, true);
  }
  protected function serialize($source) { // Must return a string
    return json_encode($source);
  }

  private function _serialize() { // Used internally only... Triggers callbacks.
    $this->beforeSerialize();
    $this->rawData = htmlentities( $this->serialize( $this->data ), ENT_QUOTES );
    $this->afterSerialize(); // ??: Should the results be passed in to allow massaging?
    return $this;
  }
  private function _deserialize() { // Used internally only... Triggers callbacks.
    $this->beforeDeserialize();
    $this->data = $this->deserialize( html_entity_decode($this->rawData, ENT_QUOTES) );
    $this->afterDeserialize(); // ??: Should the results be passed in to allow massaging?
    return $this;
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
  }
  
  // FIXME: All these loops really need an optimization pass...
  protected function _buildSQL($isCount=false) {
    // TODO: Implement OR logic...
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