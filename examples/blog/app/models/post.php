<?php

class Post extends Document {
  $indexes = array(
    'slug'         => 'STRING',
    'user_id'      => 'INTEGER',
    'publish_date' => 'TIMESTAMP',
  );
  $hasMany = array(
    'document'=>'Comment'
  );
  $belongsTo = array(
    'document'=>'User'
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
