<?php

/**
 * Document-Specific Version of ModelFinder
 *
 * Finds and queries document objects.
 *
 * @todo DocumentFinder Todos
 *     - Implement OR logic
 *     - Cleanup loop logic for building SQL
 *     - Add a 'fudge' factor for querying on non-indexed fields (will manually filter array)
 *     - Same for 'order'
 *
 * @see Model, Document
 */
class DocumentFinder extends ModelFinder {
  /**
   * @ignore
   */
  private $nativeFields = array('id','created_on','updated_on');

  /**
   * Calls the reindex() method of all the matched Documents.
   *
   * Returns $this so you can chain calls.
   *
   * @see Document
   * @return DocumentFinder
   */
  public function reindex() {
    foreach ($this as $doc) {
      $doc->reindex();
    }
    return $this;
  }

  /**#@+
   * @ignore
   */
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

    $tables = array_unique(array_merge(array($this->objtype), $all_order_cols));
    //TODO: Should it select the id, data, and datetime(created_on, 'localtime')???
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
          // Fix for where'ing on mulitple columns... ??
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
    //if($isCount) return $sql.";"; // Seems to quadruple the count if I exit here...

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
//    print_r($sql); echo "\n";
    return $sql;
  }

  protected function _getIdxCol($column, $appendCol=true) {
    $col = $this->objtype ."_". $column ."_idx";
    if($appendCol) {
      $col .= ".". $column;
    }
    return $col;
  }

  /**#@-*/
}