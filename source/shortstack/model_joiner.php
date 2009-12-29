<?php
/**
 * Use For hasMany(through) Relationships
 */
class ModelJoiner extends Model {
  /**
   * Override this in your joiner subclass
   */
  protected $joins = array(); // OVERRIDE ME!

  /**#@+
   * @ignore
   */
  private $srcModel;
  private $toModel;

  public function __construct($dataRow=null, $from=null) {
    parent::__construct($dataRow);
    $this->srcModel = $from;
    if(count($this->joins) == 2) {
      list($left, $right) = $this->joins;
      $left = strtolower($left)."_id";
      $right = strtolower($right)."_id";
      if(! array_key_exists($left, $this->schema)) $this->schema[$left] = "INTEGER";
      if(! array_key_exists($right, $this->schema)) $this->schema[$right] = "INTEGER";
    }
  }

  public function getRelated($to) {
    list($a, $b) = $this->joins;
    $srcMdlCls = $this->srcModel->modelName;
    $toMdlCls = ($a == $srcMdlCls) ? $b : $a;
    $srcId = strtolower($srcMdlCls)."_id";
    $toId = strtolower($toMdlCls)."_id";
    $id = $this->srcModel->id;
    $sql = "SELECT * FROM $toMdlCls WHERE id IN (SELECT $toId FROM $this->modelName WHERE $srcId = $id);";
    //echo $sql;
    $stmt = DB::Query($sql);
    $mdls = array();
    if($stmt) {
      $results = $stmt->fetchAll();
      foreach ($results as $row) {
        $mdls[] = new $toMdlCls($row);
      }
    } // else {
    //       debug("No Statement Object for: $sql\nError: ");
    //       debug(DB::GetLastError());
    //     }
    return $mdls;
  }
  /**#@-*/
}