<?php

include('test_helper.php');

class TestFinder extends UnitTestCase {
  
  private $user1;
  private $user2;
  private $user3;
  
  function setUp() {
    $this->user1 = get('User')->build(array(
      'username'     => 'matt',
      'password'     => 'pass',
      'display_name' => 'Matt'
    ));
    $this->user1->save();

    $this->user2 = get('User')->build(array(
      'username'     => 'dan',
      'password'     => 'pass',
      'display_name' => 'Dan'
    ));
    $this->user2->save();

    $this->user3 = get('User')->build(array(
      'username'     => 'sam',
      'password'     => 'pass',
      'display_name' => 'Sam'
    ));
    $this->user3->save();
    $this->assertEqual(get('User')->count(), 3);
    
    for ($i=0; $i < get('User')->count(); $i++) { 
      $ukey = 'user'.($i +1);
      $post = get('Post')->build(array(
        'user_id' => $this->{$ukey}->id,
        'title' => 'Hello World ('. $i .')',
        'author' => $this->{$ukey}->username,
        'body' => 'Hello world, how are you?!',
        'publish_date' => time(),
        'position' => $i
      ));
      $post->save();
//      $post->tag('news');
      // for ($j=0; $j < 2; $j++) { 
      //   $c = $post->newComment(array(
      //     'author' => 'User '.$j,
      //     'body' => "Posting!"
      //   ));
      //   $c->save();
      // }
    }
  }
  
  function tearDown() {
    get('User')->destroy();
  }
  
  
  function testCreated() {
    $this->assertEqual( get('User')->count(), 3 );
  }
 
  function testDestroyed() {
    $this->assertEqual( get('User')->count(), 3 );
    get('User')->destroy();
    $this->assertEqual( get('User')->count(), 0 );
  }
  
  function testTheFinder() {
    $matt = get('User')->where('username')->eq('matt')->get();
    $this->assertNotNull($matt);
    $this->assertEqual( $this->user1->id, $matt->id );

    $dan = get('User')->where('username')->eq('dan')->get();
    $this->assertNotNull($dan);
    $this->assertEqual( $this->user2->id, $dan->id );

    $sam = get('User')->where('username')->neq('dan')->andWhere('username')->neq('matt')->get();
    $this->assertNotNull($sam);
    $this->assertEqual( $this->user3->id, $sam->id );
    
    $postCnt = get("Post")->where('author')->neq('dan')->andWhere('author')->neq('sam')->count();
    $this->assertEqual( $postCnt, 1 );

    $postCnt = get("Post")->where('user_id')->neq('0')->where('author')->neq('sam')->count();
    $this->assertEqual( $postCnt, 2 );

  }
  
}
