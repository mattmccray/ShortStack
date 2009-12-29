<?php
/**
 * A WORK IN PROGRESS...
 */
class Pager implements IteratorAggregate {
  protected $finder = null;
  public $pageSize = 10;
  public $currentPage = 0;
  public $pages = 0;

  function __construct($finder, $pageSize=10, $params=array()) {
    if($finder instanceof ModelFinder) {
      $this->finder = $finder;
    } else if(is_string($finder)) {
      $this->finder = get($finder);
    } else {
      throw new Exception("You must specify a model name or finder object.");
    }
    $this->pageSize = $pageSize;
    $this->pages = $this->count(); // ???
    if(count($params) > 0) {
      $this->fromParams($params);
    }
  }

  public function fromParams($params) {
    list($key, $page) = array_slice($params, -2);
    if($key == 'page' && is_numeric($page))
      $this->currentPage = intVal($page);
  }

  public function count() { // Count the number of pages...
    $this->finder->limit(0)->offset(0);
    $total = $this->finder->count();
    return ceil( $total / $this->pageSize );
  }

  public function items() {
    $this->finder->limit($this->pagesize)->offset($this->currentPage);
    return $this->finder->fetch();
  }

  public function getIterator() { // For using the finder as an array in foreach() statements
    return new ArrayIterator( $this->item() );
  }
}
