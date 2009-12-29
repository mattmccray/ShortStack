<?php

class Post extends Document {
  $indexes = array(
    'slug'         => 'STRING',
    'user_id'      => 'INTEGER',
    'publish_date' => 'TIMESTAMP',
  );
  $hasMany = array(
    'comments' => array('document'=>'Comment', 'cascade'=>'delete'),// or nullify
    'tags' => array('through' => 'Tagging'),
  );
  $belongsTo = array(
    'user' => array('document'=>'User'),
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
  
  public function tag($name) {
    $tag = Tag::FindOrCreate($name);
    $this->newTagging(array('tag_id'=>$tag->id))->save();
  }
}