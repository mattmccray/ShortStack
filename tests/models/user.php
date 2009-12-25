<?php 

class User extends Model {

  protected $schema = array(
    'username' => 'varchar(255)',
    'password' => 'varchar(255)',
    'display_name' => 'varchar(255)',
    'email' => 'varchar(255)',
    'created_on' => 'datetime default CURRENT_TIMESTAMP',
  );

  protected $hasMany = array(
    'posts' => array('document'=>'Post'),
    'pages' => array('model'=>'Page'),
  );
  
  static public function authenticate($username, $password) {
    return (mdl('User')->where('username')->eq($username)->andWhere('password')->eq($password)->count() > 0);
  }  

}
