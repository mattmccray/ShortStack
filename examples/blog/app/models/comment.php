<?php

class Comment extends Document {
  $indexes = array(
    'post_id'      => 'INTEGER',
    'user_id'      => 'INTEGER',
  );
  $belongsTo = array(
    'post' => array('document'=>'Post'),
    'user' => array('document'=>'User'),
  );
  
  protected beforeSave() {
    if( $this->hasChanged('body_src') ){
      $this->body = markdown($this->body_src);
    }
  }
}
