<?php
/**
 * A WORK IN PROGRESS...
 */
class Pager implements IteratorAggregate {
  protected $finder = null;
  public $pageSize = 10;
  public $currentPage = 1;
  public $currentDataPage = 0;
  public $pages = 0;
  public $pageKey;
  public $baseUrl;

  function __construct($finder, $params=array(), $rootUrl='/', $pageSize=10, $pageKey='page') {
    if($finder instanceof ModelFinder) {
      $this->finder = $finder;
    } 
    else if(is_string($finder)) {
      $this->finder = get($finder);
    } 
    else {
      throw new Exception("You must specify a model name or finder object.");
    }
    if(ends_with('/', $rootUrl) )
      $this->baseUrl = $rootUrl;
    else
      $this->baseUrl = $rootUrl.'/';
    $this->pageSize = $pageSize;
    $this->pageKey = $pageKey;
    $this->fromParams($params);
    $this->pages = $this->pageCount(); // ???
  }

  public function fromParams($params=array()) {
    if(count($params) >= 2) {
      list($key, $page) = array_slice($params, -2);
      if($key == $this->pageKey && is_numeric($page)) {
        $this->currentPage = intVal($page);
        $this->currentDataPage = $this->currentPage - 1;
      }
    }
  }

  public function count() { // Count the number of pages...
    return $this->finder->limit(0)->offset(0)->count();
  }

  public function pageCount() { // Count the number of pages...
    $total = $this->count();
    return intVal(ceil( $total / $this->pageSize ));
  }

  public function items() {
    $this->finder->limit($this->pageSize)->offset(($this->currentDataPage * $this->pageSize));
    return $this->finder->fetch();
  }

  public function getIterator() { // For using the finder as an array in foreach() statements
    return new ArrayIterator( $this->items() );
  }
  
  public function renderPager($className='pager', $currentClass='current', $inactiveClass='inactive', $toggleClass='toggle') {
    $html = '<div class="'.$className.'">';
    // << Prev
    $html .= '<a href="'.$this->baseUrl.'page/';
    if($this->currentDataPage == 0) {
      $html .=  '1" class="'.$inactiveClass.' '.$toggleClass;
    } else {
      $html .=  $this->currentDataPage.'" class="'.$toggleClass;
    }
    $html .='"><span>&laquo; Prev</span></a>';
    // Page Numbers
    for ($i=1; $i <= $this->pages; $i++) { 
      $html .= '<a href="'.$this->baseUrl.'page/'.$i.'"';
      if($i == $this->currentPage)
        $html .=' class="'.$currentClass.'"';
      $html .='><span>'.$i.'</span></a>';
    }
    // Next>>
    $html .= '<a href="'.$this->baseUrl.'page/';
    if($this->currentPage == $this->pages) {
      $html .=  $this->currentPage.'" class="'.$inactiveClass.' '.$toggleClass;
    } else {
      $html .=  ($this->currentPage +1).'" class="'.$toggleClass;
    }
    $html .='"><span>Next &raquo;</span></a>';

    return $html."</div>";
  }
}
