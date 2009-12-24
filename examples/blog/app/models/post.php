<?php

class Post extends DocumentModel {
  $indexes = array(
    'slug'         => 'STRING',
    'author'       => 'STRING',
    'publish_date' => 'TIMESTAMP',
  );

  protected beforeCreate() {
    $this->slug = slugify($this->title);
  }
}
