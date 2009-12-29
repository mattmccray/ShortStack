<?php

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
    $this->matcher = new FinderMatch($this);
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
  
  // TODO: Change this to replace the order array, and possibly use func_get_args()
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
//    return count($this->fetch($ignoreCache));
  }
  
  public function get($ignoreCache=false) {   // Returns the first match
    $oldLimit = $this->limit;
    $this->limit = 1; // Waste not, want not.
    $docs = $this->_execQuery($ignoreCache);
    $this->limit = $oldLimit;
    if(count($docs) == 0)
      return null;
    else
      return @$docs[0];
  }
  
  public function fetch($ignoreCache=false) { // Executes current query
    return $this->_execQuery($ignoreCache);
  }
  
  // Returns the raw resultset...
  public function raw($ignoreCache=true) {
    $sql = $this->_buildSQL();
    $stmt = DB::Query($sql);
    return $stmt->fetchAll();
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
    $this->__cache = false;
  }

  public function update($values=array()) {
    foreach ($this as $doc) {
      $doc->update($values);
      $doc->save();
    }
    $this->__cache = false;
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
    } // FIXME: ELSE THROW SQL ERROR??? Hmm..
//    else { echo "STATEMENT ERROR on $sql\nError: ". DB::GetLastError() ."\n"; }
    return $items;
  }
  
  // Model _buildSQL
  protected function _buildSQL($isCount=false) {
    if($isCount)
      $sql = "SELECT count(id) as count FROM ". $this->objtype ." ";
    else
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
    if($isCount) return $sql.";";

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

class FinderMatch {
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
