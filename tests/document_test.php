<?php

include('test_helper.php');

class TestOfDocumentModel extends UnitTestCase {
  
  function setUp() {
    for ($i=0; $i < 6; $i++) { 
      $post = new Post();

      $post->updateValues(array(
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
    
  }

  
  function testDocUpdate() {
    
  }

  // function testRelations() {
  //   $post = doc('Post')->where('author')->eq('matt')->get(); // The first one
  //   $this->assertTrue( $post instanceof DocumentModel );
  //   
  //   $comments = $post->comments();
  //   
  //   $this->assertEqual( $comments->count(), 2 );
  // }


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
