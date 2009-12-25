<?php

class Post extends DocumentModel {
  protected $indexes = array(
    'user_id'      => 'INTEGER',
    'slug'         => 'STRING',
    'author'       => 'STRING',
    'publish_date' => 'TIMESTAMP',
  );
  protected $hasMany = array(
    'comments' => array('document'=>'Comment')
  );
  protected $belongs_to = array(
    'user' => array('model'=>'User'),
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