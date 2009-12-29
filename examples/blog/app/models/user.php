<?php

class User extends Document {
  $indexes = array(
    'username' => 'STRING',
    'password' => 'STRING',
  );
  $hasMany = array(
    'pages' => array('document'=>'Page'),
    'posts' => array('document'=>'Post'),
    'comments' => array('document'=>'Comment'),
  );
}
