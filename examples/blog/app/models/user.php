<?php

class User extends Document {
  $indexes = array(
    'username' => 'STRING',
    'password' => 'STRING',
  );
  $hasMany = array(
    'document'=>'Page',
    'document'=>'Post',
    'document'=>'Comment',
  );
  
}
