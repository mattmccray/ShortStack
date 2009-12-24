<?php

class Comment extends DocumentModel {
  protected $indexes = array(
    'post_id'      => 'INTEGER',
    'author'       => 'STRING',
    'publish_date' => 'TIMESTAMP'
  );
  protected $belongsTo = array(
    'doctype'=>'Post'
  );
  
  protected function beforeSave() {
    if( $this->hasChanged('body_src') ){
      $this->body = markdown($this->body_src);
    }
  }
}
