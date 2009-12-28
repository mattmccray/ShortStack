<?php

include('test_helper.php');

class TestOfPager extends UnitTestCase {
  
  private $user;
  
    function setUp() {
      $this->user = new User();
      $this->user->update(array(
        'username'     => 'test',
        'password'     => 'pass',
        'display_name' => 'Test User'
      ));
      $this->user->save();

      $now = time();
      for ($i=0; $i < 25; $i++) { 
        $post = $this->user->newPost(array(
          'title' => 'Hello World ('. $i .')',
          'author' => 'matt',
          'body' => 'Hello world, how are you?!',
          'publish_date' => $now,
          'position' => $i
        ));
        $post->save();
      }
    }

    function tearDown() {
      doc('User')->destroy();
      doc('Post')->destroy();
    }

  
  function testCount() {
    $pgr = new Pager('Post');
    $this->assertEqual($pgr->count(), 3);

    $pgr = new Pager(doc('Post')->where('author')->neq('matt'));
    $this->assertEqual($pgr->count(), 0);
  }
  
  function testParamParsing() {
    $pgr = new Pager('Post', 10, array('posts', 'page', '2'));
    $this->assertEqual($pgr->count(), 3);
    $this->assertEqual($pgr->currentPage, 2);
  }
  
}