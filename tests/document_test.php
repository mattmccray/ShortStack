<?php

include('test_helper.php');

class TestOfDocument extends UnitTestCase {

  private $user;
  
  function setUp() {
    $this->user = new User();
    $this->user->update(array(
      'username'     => 'test',
      'password'     => 'pass',
      'display_name' => 'Test User'
    ));
    $this->user->save();
    
    for ($i=0; $i < 6; $i++) { 
      $post = new Post();

      $post->updateValues(array(
        'user_id' => $this->user->id,
        'title' => 'Hello World ('. $i .')',
        'author' => 'matt',
        'body' => 'Hello world, how are you?!',
        'publish_date' => time(),
        'position' => $i
      ));
      
      $post->save();
      
      for ($j=0; $j < 2; $j++) { 
        $c = new Comment();
        $c->update(array(
          'post_id' => $post->id,
          'author' => 'User '.$j,
          'body' => "Posting!"
        ));
        $c->save();
      }

    }
  }
  
  function tearDown() {
    doc('Post')->destroy();
    doc('Comment')->destroy();
    $this->user->destroy();
  }
  

  function testDocCreate() {
    
    $this->assertEqual( doc('Post')->count(), 6 );
  }

  function testDocFinder() {
    $qry = doc('Post')->where('author')->eq('matt');//->order('slug');
    //echo "\n\n".$qry->count() ." posts by matt\n";
    // 
    // foreach ($qry as $post) {
    // //  print_r($post);
    //   echo " - ". $post->id .") ". $post->title ." (". $post->author.")\n";
    // }
//    debug($qry->count());
    $this->assertNotEqual(doc('Post')->count(), 0);
    $this->assertNotEqual($qry->count(), 0);

    // Using get() helper
    $qry2 = get('Post')->where('author')->eq('matt');//->order('slug');
    $this->assertNotEqual(get('Post')->count(), 0);
    $this->assertNotEqual($qry2->count(), 0);
    
  }

  function testDocUpdate() {
    $qry = doc('Post')->where('author')->neq('matt');
    $this->assertEqual($qry->count(true), 0);

    $post = doc('Post')->where('author')->eq('matt')->get(); // The first one
    $post->update(array( 'author'=>'dan' ));
    $post->save();

    $this->assertEqual($qry->count(true), 1);
  }

  function testRelations() {
    $post = doc('Post')->where('author')->eq('matt')->get(); // The first one
    $this->assertTrue( $post instanceof Document );
    
    $comments = $post->comments();
    
    $this->assertEqual( $comments->count(), 2 );
  }
  
  function testRelationsToModels() {
    $post = doc('Post')->where('author')->eq('matt')->get(); // The first one
    $user = $post->user();
    $this->assertNotNull($user);
    $this->assertIsA($user, User);
    $this->assertEqual($user->username, 'test');
  }


  function testDocDestroy() {
    doc('Post')->destroy();
    $this->assertEqual( doc('Post')->count(), 0 );
  }
  
  
  
    // function testLogCreatesNewFileOnFirstMessage() {
    //     @unlink('/temp/test.log');
    //     $log = new Log('/temp/test.log');
    //     $this->assertFalse(file_exists('/temp/test.log'));
    //     $log->message('Should write this to a file');
    //     $this->assertTrue(file_exists('/temp/test.log'));
    // }
}
