<?php
 
class Document {
  private static $_document_indexes_ = array();
  
  public static function Register($class) {
    $doc = new $class();
    $doc->_defineDocumentFromModel();
  }
  
  public static function Define($name, $indexes=array(), $createClass=true) {
    Document::$_document_indexes_[$name] = array_merge($indexes, array()); // a hokey way to clone an array
    if($createClass)
      eval("class ". $name ." extends DocumentModel {  }");
  }
  
  public static function InitializeDatabase() {
    // Loop through all the docs and create tables and index tables...
    foreach( Document::$_document_indexes_ as $docType => $indexes) {
      $tableSQL = "CREATE TABLE IF NOT EXISTS ". $docType ." ( id INTEGER PRIMARY KEY, data TEXT, created_on TIMESTAMP, updated_on TIMESTAMP );";
      DB::query( $tableSQL );
      foreach ($indexes as $field=>$fType) {
        $indexSQL = "CREATE TABLE IF NOT EXISTS ". $docType ."_". $field ."_idx ( id INTEGER PRIMARY KEY, docid INTEGER, ". $field ." ". $fType ." );";
        DB::query( $indexSQL );
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
      $results = DB::fetchAll($sql);
      foreach ($results as $row) {
        $docs[] = new $doctype($row);
      }
    } else {
      // Do 'em all!
      foreach( Document::$_document_indexes_ as $docType => $indexes) {
        $sql = "SELECT * FROM ".$doctype."";
        $results = DB::fetchAll($sql);
        foreach ($results as $row) {
          $docs[] = new $doctype($row);
        }
      }
    }
    // Step two: Loop through them and delete, then rebuild index rows
    foreach ($docs as $doc) {
      $indexes = Document::GetIndexesFor($doc->doctype);
      foreach ($indexes as $field=>$fType) {
        // TODO: Optimize as single transactions?
        $indexTable = $doc->doctype ."_". $field ."_idx";
        $sql = "DELETE FROM ". $indexTable ." WHERE docid = ". $doc->id .";";
        $results = DB::query($sql);
        $sql = "INSERT INTO ". $indexTable ." ( docid, ". $field ." ) VALUES (".$doc->id.', "'.htmlentities($doc->{$field}, ENT_QUOTES).'" );';
        $results = DB::query($sql);
      }
    }
  }
  
  public static function Get($doctype, $id) {
    $sql = "SELECT * FROM ".$doctype." WHERE id = ". $id .";";
    $results = DB::fetchAll($sql);
    $docs = array();
    foreach ($results as $row) {
      $docs[] = new $doctype($row);
    }
    return @$docs[0];
  }
 
  public static function Find($doctype) {
    return new DocumentFinder($doctype);
  }
  
  public static function Destroy($doctype, $id) {
    $sql = "DELETE FROM ".$doctype." WHERE id = ". $id .";";
    DB::query($sql);
    foreach ( Document::GetIndexesFor($doctype) as $field=>$fType) {
      $sql = "DELETE FROM ".$doctype."_".$field."_idx WHERE docid = ". $id .";";
      DB::query($sql);
    }
  }

  public static function GetIndexesFor($doctype) {
    return Document::$_document_indexes_[$doctype];
  }
}


class DocumentFinder implements IteratorAggregate {
  
  protected $doctype;
  protected $matcher = false;
  protected $all_finder_cols = array();
  protected $finder = array();
  protected $or_finder = array();
  protected $all_order_cols = array();
  protected $order = array();
  protected $limit = false;
  
  public function __construct($docType) {
    $this->doctype = $docType;
  }
  
  public function where($index) {
    if(! $this->matcher) $this->matcher = new DocumentMatcher($this, $index);
    $this->matcher->_updateIdxAndCls($index, 'and');
    return $this->matcher;
  }
  
  public function andWhere($index) {
    if(! $this->matcher) $this->matcher = new DocumentMatcher($this, $index);
    $this->matcher->_updateIdxAndCls($index, 'and');
    return $this->matcher;
  }

  public function orWhere($index) {
    if(! $this-matcher) $this->matcher = new DocumentMatcher($this, $index);
    $this->matcher->_updateIdxAndCls($index, 'or');
    return $this->matcher;
  }
  
  public function order($field, $dir='ASC') {
    $this->order[$field] = $dir;
    $this->all_order_cols[]= $this->_getIdxCol($field, false);
    return $this;
  }
  
  public function limit($count) {
    $this->limit = $count;
    return $this;
  }
  
  public function count() {
    return count($this->fetch());
  }
  
  public function get() {   // Returns the first match
    $oldLimit = $this->limit;
    $this->limit = 1; // Waste not, want not.
    $docs = $this->_execQuery();
    $this->limit = $oldLimit;
    return @$docs[0];
  }
  
  public function fetch() { // Executes current query
    return $this->_execQuery();
  }
  
  public function getIterator() { // For using the finder as an array in foreach() statements
    $docs = $this->_execQuery();
    return new ArrayIterator($docs);
  }
  
// Warning these modified the matched records!!

  public function destroy() {
    foreach ($this as $doc) {
      $doc->destroy();
    }
  }

  public function update($values=array()) {
    foreach ($this as $doc) {
      $doc->update($values);
      $doc->save();
    }
  }
  
  public function _addFilter($column, $comparision, $value, $clause) {
    // FIXME: Make it ' like "%'. $value .'%'; for LIKES...
    $finder_filter = " ". $this->_getIdxCol($column)  ." ". $comparision .' "'. htmlentities($value, ENT_QUOTES) .'" ';//array($column, $comparision, $value, $clause);
    if($clause == 'or') {
      $this->or_finder[] = $finder_filter;
    } else {
      $this->finder[] = $finder_filter;
    }
    $this->all_finder_cols[]=$this->_getIdxCol($column, false);
    return $this;
  }
  
  protected function _getIdxCol($column, $appendCol=true) {
    $col = $this->doctype ."_". $column ."_idx";
    if($appendCol) {
      $col .= ".". $column;
    }
    return $col; 
  }
  
  protected function _execQuery() {
    $sql = $this->_buildSQL();
    $results = DB::fetchAll($sql);
    $docs = array();
    $className = $this->doctype;
    foreach ($results as $rowdata) {
      $docs[] = new $className($rowdata);
    }
    return $docs;
  }
  
  protected function _buildSQL() {
    $tables = array_merge(array($this->doctype), $this->all_order_cols);
    $sql = "SELECT ". $this->doctype .".* FROM ". join(', ', $tables) ." ";
    
    if(count($this->finder) > 0) {
      $sql .= "WHERE ". $this->doctype .".id IN (";
      $sql .= "SELECT ". $this->all_finder_cols[0] .".docid FROM ". join(', ', $this->all_finder_cols). " ";
      $sql .= "WHERE ";
      $sql .= join(' AND ', $this->finder);
      $sql .= ") ";
    }
    if(count($this->order) > 0) {
      $sql .= "AND ";
      $sortJoins = array();
      foreach ($this->order as $field => $dir) {
        $sortJoins[] = $this->_getIdxCol($field, false) .".docid = ". $this->doctype .".id ";
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

}

class DocumentMatcher {
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

class DocumentModel {
  public $doctype;
  public $id = null;
  
  protected $rawData = null;
  protected $data = false;
  protected $isNew;
  protected $hasChanged;
  protected $changedFields = array();

  protected $indexes = array();
  protected $hasMany = array();
  protected $belongsTo = array();

  function __construct($dataRow=null) {
    $this->doctype = get_class($this);
    if($dataRow != null) {
      $this->id = $dataRow['id'];
      $this->rawData = $dataRow['data'];
      $this->isNew = false;
    } else {
      $this->rawData = null;
      $this->data = array();
      $this->isNew = true;
    }
    $this->hasChanged = false;
  }

  function __get($key) {
    if(!$this->data) { $this->_deserialize(); }
    return $this->data[$key]; //html_entity_decode($this->data[$key], ENT_QUOTES );
  }

  function __set($key, $value) {
    if(!$this->data) { $this->_deserialize(); }
    $value = stripslashes($value);
    if(@ $this->data[$key] != $value){ //htmlentities($value, ENT_QUOTES)) {
      $this->data[$key] = $value;
      $this->changedFields[] = $key;
      $this->hasChanged = true;
    }
  }
  
  public function has($key) {
    if(!$this->data) $this->_deserialize();
    return array_key_exists($key, $this->data);
  }
  
  function __call( $method, $args ) {
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
    $mdlClass = $def['doctype'];
    $fk = strtolower($this->doctype)."_id";
    return Document::Find($mdlClass)->where($fk)->eq($this->id)->get();
  }
  
  protected function _handleBelongsTo($method, $args) {
    $def = $this->hasMany[$method];
    $mdlClass = $def['doctype'];
    $fk = strtolower($mdlClass)."_id";
    return Document::Find($mdlClass)->where('id')->eq($this->$fk).get();
  }
  
  public function updateValues($values=array()) {
    return $this->update($values);
  }
  public function update($values=array()) {
    foreach($values as $key=>$value) {
      $this->$key = $value;
    }
    return $this;
  }
  public function hasChanged($key) {
    return in_array($key, $this->changedFields);
  }

  public function save() {
    if($this->hasChanged) {
      $this->beforeSave(); // Cannot cancel events... yet.
      if($this->isNew) { // Insert
        $this->beforeCreate(); // Cannot cancel events... yet.
        $this->_serialize();
        $sql = 'INSERT INTO '.$this->doctype.' ( data ) VALUES ( "'. $this->rawData .'" );';
        $statement = DB::query($sql);
        $result = DB::query('SELECT last_insert_rowid() as last_insert_rowid')->fetch(); // Get the record's generated ID...
        $this->id = $result['last_insert_rowid'];
        Document::Reindex($this->doctype, $this->id);
        $this->afterCreate(); // Cannot cancel events... yet.
      } else { // Update
        $this->serialize();
        $sql = "UPDATE ".$this->doctype.' SET data="'.htmlentities( json_encode($this->data), ENT_QUOTES).'" WHERE id = '. $this->id .';';
        $statement = DB::query($sql);
        $index_changed = array_intersect($this->changedFields, array_keys(Document::GetIndexesFor($this->doctype)));
        if(count($index_changed) > 0)  // Only if an indexed field has changed
          Document::Reindex($this->doctype, $this->id);
      }
      $this->changedFields = array();
      $this->hasChanged = false;
      $this->isNew = false;
      $this->afterSave(); // Cannot cancel events... yet.
    }
    return $this;
  }
  
  // Callbacks
  protected function beforeSave() {}
  protected function afterSave() {}
  protected function beforeCreate() {}
  protected function afterCreate() {}
  protected function beforeDestroy() {}
  protected function afterDestroy() {}
  protected function beforeSerialize() {}
  protected function afterSerialize() {}
  protected function beforeDeserialize() {}
  protected function afterDeserialize() {}

  // Warning: Like Han solo, this method doesn't fuck around, it will shoot first.
  public function destroy() {
    $this->beforeDestroy(); // Cannot cancel events... yet.
    Document::Destroy($this->doctype, $this->id);
    $this->afterDestroy(); // Cannot cancel events... yet.
    return $this;
  }
  
  public function _defineDocumentFromModel() {
    Document::Define( $this->doctype, $this->indexes, false);
  }
  
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
  
  public function to_json($exclude=array()) {
    return Document::toJSON( $this->to_array($exclude) );
  }

  static public function toJSON($obj) {
    if(is_array($obj)) {
      $data = array();
      foreach($obj as $idx=>$mdl) {
        if($mdl instanceof Document)
          $data[] = $mdl->to_array();
        else
          $data[$idx] = $mdl;
      }
      return json_encode($data);
      
    } else if( $obj instanceof Document ) {
      return json_encode($obj->to_array());
    
    } else {
      return json_encode($obj);
    }
  }

}