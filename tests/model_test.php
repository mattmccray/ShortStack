<?php

include('test_helper.php');

class TestOfModel extends UnitTestCase {
  
  private $user;
  
  
  function setUp() {
    $user = new User();
    $user->update(array(
      'username'     => 'test',
      'password'     => 'pass',
      'display_name' => 'Test User'
    ));
    $user->save();
    
    $this->user = $user;
  }
  
  function tearDown() {
//    Model::Destroy('User');
    // Using Finder Syntax:
//    mdl('User')->destroy();
    if($this->user)
      $this->user->destroy();
  }
  
  function testCreated() {
    $this->assertEqual( Model::Count('User'), 1 );
  }
 
  function testDestroyed() {
//    mdl('User')->destroy();
    $this->user->destroy();
    $this->assertEqual( Model::Count('User'), 0 );
  }
  
}