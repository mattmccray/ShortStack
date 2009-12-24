<?php

class Post extends DocumentModel {
  protected $indexes = array(
    'slug'         => 'STRING',
    'author'       => 'STRING',
    'publish_date' => 'TIMESTAMP',
  );
  protected $hasMany = array(
    'doctype'=>'Comment'
  );

  protected function beforeCreate() {
    if($this->has('title'))
      $this->slug = slugify($this->title);
  }
  
  protected function beforeSave() {
    if( $this->hasChanged('body_src') ){
      $this->body = markdown($this->body_src);
    }
  }
}