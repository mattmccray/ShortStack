<?php

class Post extends Document {
  $indexes = array(
    'slug'         => 'STRING',
    'author'       => 'STRING',
    'publish_date' => 'TIMESTAMP',
  );
  $hasMany = array(
    'doctype'=>'Comment'
  );

  protected beforeCreate() {
    $this->slug = slugify($this->title);
  }
  
  protected beforeSave() {
    if( $this->hasChanged('body_src') ){
      $this->body = markdown($this->body_src);
    }
  }
}
