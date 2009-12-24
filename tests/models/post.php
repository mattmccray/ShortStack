<?php

class Post extends DocumentModel {
  protected $indexes = array(
    'slug' => 'TEXT',
    'author' => 'TEXT',
    'publish_date' => 'TIMESTAMP',
    'position' => 'INTEGER'
  );
  
  protected function beforeCreate() {
    $this->slug = slugify($this->title);
  }
}
