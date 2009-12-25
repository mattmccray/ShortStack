<?php

class Comment extends Document {
  $indexes = array(
    'post_id'      => 'INTEGER',
    'author'       => 'STRING',
    'publish_date' => 'TIMESTAMP',
  );
  $belongsTo = array(
    'doctype'=>'Post'
  );
  
  protected beforeSave() {
    if( $this->hasChanged('body_src') ){
      $this->body = markdown($this->body_src);
    }
  }
}
