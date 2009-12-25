<?php

class Comment extends Document {
  protected $indexes = array(
    'post_id'      => 'INTEGER',
    'author'       => 'STRING',
    'publish_date' => 'TIMESTAMP'
  );
  protected $belongsTo = array(
    'post'=>array('document'=>'Post'),
    'post'=>array('model'=>'User')
  );
  
  protected function beforeSave() {
    if( $this->hasChanged('body_src') ){
      $this->body = markdown($this->body_src);
    }
  }
}
