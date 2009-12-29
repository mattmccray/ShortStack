<?php

class Page extends Document {
  $indexes = array(
    'slug'=>'STRING',
    'user_id'=>'INTEGER',
  );
  $belongsTo = array(
    'user' = array('document'=>'User')
  );

  protected function beforeCreate() {
    $this->slug = slugify($this->title);
  }
  
  protected function beforeSave() {
    if( $this->hasChanged('body_src') ){
      $this->body = markdown($this->body_src);
    }
  }
}
