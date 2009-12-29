<?php
/**
 * ModelFinder
 *
 * Finds and queries model objects. Also serves as base-class for other Model types (like Document).
 *
 * @todo ModelFinder Todos
 *     - Implement OR logic
 *
 * @see Model, Document
 */
class ModelFinder implements IteratorAggregate {
  /**#@+
  * @ignore
  */
  protected $objtype;
  protected $matcher = false;
  protected $finder = array();
  protected $or_finder = array();
  protected $order = array();
  protected $limit = false;
  protected $offset = false;
  private $__cache = false;
  /**#@-*/

  public function __construct($objtype) {
    $this->objtype = $objtype;
    $this->matcher = new FinderMatcher($this);
  }
  /**
   * @returns FinderMatcher
   * @see FinderMatcher
   */
  public function where($index) {
    $this->__cache = false;
    $this->matcher->_updateIdxAndCls($index, 'and');
    return $this->matcher;
  }
  /**
   * @returns FinderMatcher
   * @see FinderMatcher
   */
  public function andWhere($index) {
    $this->__cache = false;
    $this->matcher->_updateIdxAndCls($index, 'and');
    return $this->matcher;
  }
  /**
   * @returns FinderMatcher
   * @see FinderMatcher
   */
  public function orWhere($index) {
    $this->__cache = false;
    $this->matcher->_updateIdxAndCls($index, 'or');
    return $this->matcher;
  }
  /**
   * Returns $this so you can chain calls.
   *
   * @returns ModelFinder
   */
  public function order($field, $dir='ASC') {
    // TODO: Change this to replace the order array, and possibly use func_get_args()
    $this->__cache = false;
    $this->order[$field] = $dir;
    return $this;
  }
  /**
   * Returns $this so you can chain calls.
   *
   * @returns ModelFinder
   */
  public function limit($count) {
    $this->__cache = false;
    $this->limit = $count;
    return $this;
  }
  /**
   * Returns $this so you can chain calls.
   *
   * @returns ModelFinder
   */
  public function offset($count) {
    $this->__cache = false;
    $this->offset = $count;
    return $this;
  }
  /**
   * @returns int
   */
  public function count() {
    $sql = $this->_buildSQL(true);
    $res = DB::FetchAll($sql);
    return intVal( $res[0]['count'] );
  }
  /**
   * Returns first matched Model, or NULL
   *
   * @returns Model
   * @see Model
   */
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
  /**
   * Returns array of Model objects or NULL
   *
   * @returns array
   * @see Model
   */
  public function fetch($ignoreCache=false) { // Executes current query
    return $this->_execQuery($ignoreCache);
  }
  /**
   * Returns raw dataset (not as models)
   *
   * @returns array
   */
  public function raw($ignoreCache=true) { // Returns the raw resultset...
    $sql = $this->_buildSQL();
    $stmt = DB::Query($sql);
    return $stmt->fetchAll();
  }
  /**
   * Allows for direct use of Finder in an array-like fashion. (foreach)
   *
   * @returns ArrayIterator
   */
  public function getIterator() { // For using the finder as an array in foreach() statements
    $docs = $this->_execQuery();
    return new ArrayIterator($docs);
  }
  /**
   * This calls the destroy() method on all matched Models.
   *
   * WARNING: This won't mess around and can't be undone.
   *
   * Returns $this so you can chain calls.
   *
   * @returns ModelFinder
   */
  public function destroy() {
    foreach ($this as $doc) {
      $doc->destroy();
    }
    $this->__cache = false;
    return $this;
  }
  /**
   * This calls the update() method on all matched Models supplying them with the $values specified.
   *
   * WARNING: This won't mess around and can't be undone.
   *
   * Returns $this so you can chain calls.
   *
   * @param array $values associate array of values
   * @returns ModelFinder
   */
  public function update($values=array()) {
    foreach ($this as $doc) {
      $doc->update($values);
      $doc->save();
    }
    $this->__cache = false;
    return $this;
  }
  /**
   * @ignore
   * @internal Used to by FinderMatcher only.
   */
  public function _addFilter($column, $comparision, $value, $clause) {
    $this->__cache = false;
    $finder_filter = array('col'=>$column, 'comp'=>$comparision, 'val'=>$value);
    if($clause == 'or') $this->or_finder[] = $finder_filter;
    else $this->finder[] = $finder_filter;
    return $this;
  }
  /**
   * @ignore
   * @internal Used to fetch then execute SQL for the finder, used internally only.
   */
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
  /**
   * @ignore
   * @internal Used to generate SQL for the finder, used internally only.
   */
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


