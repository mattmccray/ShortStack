<?php

include('test_helper.php');

class TestOfDocument extends UnitTestCase {

  private $user;
  
  function setUp() {
    $this->user  = get('User')->build(array(
      'username'     => 'test',
      'password'     => 'pass',
      'display_name' => 'Test User'
    ));
    $this->user->save();
    
    // $this->user = new User();
    // $this->user->update(array(
    //   'username'     => 'test',
    //   'password'     => 'pass',
    //   'display_name' => 'Test User'
    // ));
    // $this->user->save();
    
    for ($i=0; $i < 6; $i++) { 
//      $post = new Post();
      
      $post = $this->user->newPost(array(
        'title' => 'Hello World ('. $i .')',
        'author' => 'matt',
        'body' => 'Hello world, how are you?!',
        'publish_date' => time(),
        'position' => $i
      ));

      // $post->updateValues(array(
      //   'user_id' => $this->user->id,
      //   'title' => 'Hello World ('. $i .')',
      //   'author' => 'matt',
      //   'body' => 'Hello world, how are you?!',
      //   'publish_date' => time(),
      //   'position' => $i
      // ));
      
      $post->save();
      
      $post->tag('news');
      
      for ($j=0; $j < 2; $j++) { 
        $c = $post->newComment(array(
          'author' => 'User '.$j,
          'body' => "Posting!"
        ));
        $c->save();
      }
    }
  }
  
  function tearDown() {
    doc('User')->destroy();
    // All of these should cascade from user->destroy():
      // doc('Post')->destroy();
      // doc('Comment')->destroy();
      // mdl('Tagging')->destroy();
    
    mdl('Tag')->destroy();
  }
  

  function testDocCreate() {
    
    $this->assertEqual( Model::Count('User'), 1 );
    $this->assertEqual( Document::Count('Post'), 6 );
    $this->assertEqual( doc('Post')->count(), 6 );
    $this->assertEqual( get('Comment')->count(), 6*2 );
    $this->assertEqual( get('Tag')->count(), 1 );
    $this->assertEqual( get('Tagging')->count(), 6 );
    
    $post = doc('Post')->where('author')->eq('matt')->get(); // The first one
    $this->assertEqual( $post->comments()->count(), 2 );
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
    $qry2 = get('Post')->where('author')->eq('matt')->order('created_on')->order('slug');
    $this->assertNotEqual(get('Post')->count(), 0);
    $this->assertNotEqual($qry2->count(), 0);
    
  }

  function testDocUpdate() {
    $qry = doc('Post')->where('author')->neq('matt');

    $post = doc('Post')->where('author')->eq('matt')->get(); // The first one

    $post->update(array( 'author'=>'dan', 'new-column'=>'for fun!' ));

    $this->assertEqual($qry->count(), 0);

    $post->save();

    $this->assertEqual($qry->count(), 1);
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

  function testRelationsThru() {
    $post = doc('Post')->where('author')->eq('matt')->get(); // The first one

    $tags = $post->tags();
    $this->assertEqual(count($tags), 1);

    $post->tag('misc');

    $tags = $post->tags();
    $this->assertEqual(count($tags), 2);
  }

  function testDocDestroy() {
    doc('Post')->destroy();
    $this->assertEqual( doc('Post')->count(), 0 );
  }

}
