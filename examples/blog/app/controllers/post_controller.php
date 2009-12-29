<?php

class PostController extends Controller {
  
  public function index($args=array()) {
    $pager = new Pager( doc('Post')->where('publish_date')->lt( date('Y-m-d H:i:s') ) );
    $pager->fromParams($args);
    $this->render( 'posts', array('pager'=>$pager) );
  }
  
}
