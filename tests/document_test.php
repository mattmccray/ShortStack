<?php

include('test_helper.php');


// $post = new Post();
// 
// $post->updateValues(array(
//   'title' => 'Hello World',
//   'author' => 'matt',
//   'body' => 'Hello world, how are you?!',
//   'publish_date' => time(),
//   'position' => '10'
// ));
// 
// $post->save();
// 
//print_r($post->slug);


//print_r(Document::Find('Post')->fetch());

// Document::Find('Post')->where('author')->eq('matt')->update(array(
//   'author'=>'M@'
// ));


$qry = doc('Post')->where('author')->eq('matt')->order('slug');
//$posts = $qry->fetch();

if($qry->count() == 0) {
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
  }
}

echo "\n\n".$qry->count() ." posts by matt\n";

foreach ($qry as $post) {
//  print_r($post);
  echo " - ". $post->id .") ". $post->title ." (". $post->author.")\n";
}


//$p2 = Document::Get('Post', 2);

//print_r($p2);

//$p2->author = 'bob';

//print_r($p2);

//$p2->destroy();

//$pst = Document::Get('Post', 1);
// 
// 
// print_r($pst);
// 
// echo $pst->author ." <<<\n";
// 
// print_r($pst);

//Document::Find('Post')->destroy();

echo "\n\nDone with test.\n";

// $post = Document::find('Post')->where('id')->eq(1)->get();
// 
// $mine = Document::find('Post')->where('author')->eq('Matt')->order('slug');
// foreach ($mine as $post) {
//   # code...
// }
// 
// $mine = Document::find('Post')->where('author')->eq('Matt')->and('publish_date')->lt('now()')->order('slug');
// 
// $newPost = Document::create('Post');




 
// = Document =
/*
  Document Usage Example(s):



Document::define('Post', array(
  'slug' => 'TEXT',
  'author' => 'TEXT',
  'publish_date' => 'TIMESTAMP',
  'position' => 'INTEGER'
));

$post = Document::find('Post')->where('id')->eq(1)->get();

$mine = Document::find('Post')->where('author')->eq('Matt')->order('slug');
foreach ($mine as $post) {
  # code...
}

$mine = Document::find('Post')->where('author')->eq('Matt')->and('publish_date')->lt('now()')->order('slug');

$newPost = Document::create('Post')











  

  class PostDoc extends Document {
    $doctype = "PostDoc"; // Or auto-detect from classname?
    $indices = array('slug', 'author', 'publish_date'); // For query or order
    
    
    function beforeSave() {
      $this->slug = slugify($this->title);
    }
  }
  
  $pd = new DocFinder('PostDoc');
  
  $pd->all()->order(array('slug'));
  
  $pd->where(array( 'author'=>'matt' ))->order(array( 'created_on's ));
  
  $pd->get($id);
  
  $p = new PostDoc();
  
  $p->author = 'matt';
  $p->body = "I'm the post body.";
  $p->title = "Hello World!";
  $p->publish_date = now();
  
  $p->save();


  $p = new PostDoc();

  $p->update(array(
    'author' => 'matt',
    'body' => "I'm the post body.",
    'title' => "Hello World!",
    'publish_date' => now()
  ));

  $p->save();
  
*/

/*

Document Tables:

DocStore
  id: pk (?uniqid)
  doctype: string
  data: text (json?)
  created_on: date
  updated_on: date

DocStoreIdx -- Per Doc/Field?
  id: pk
  docid: fk
  doctype: string
  field: string
  value: string


CREATE TABLE IF NOT EXISTS DocStore (
  id INTEGER PRIMARY KEY,
  doctype VarChar(255),
  data TEXT,
  created_on TIMESTAMP,
  updated_on TIMESTAMP
);

CREATE TABLE IF NOT EXISTS PostDoc_author_idx (
  id INTEGER PRIMARY KEY,
  docid INTEGER,
  author TEXT
);

CREATE TABLE IF NOT EXISTS PostDoc_slug_idx (
  id INTEGER PRIMARY KEY,
  docid INTEGER,
  slug TEXT
);

CREATE TABLE IF NOT EXISTS DocStoreIdx (
  id INTEGER PRIMARY KEY,
  docid INTEGER,
  doctype VarChar(255),
  field TEXT,
  value TEXT
);

INSERT INTO DocStore
  ( id, doctype, data )
VALUES
  ( 1, "PostDoc", "{&quot;author&quot;:&quot;matt&quot;, &quot;body&quot;:&quot;I'm the post body.&quot;, &quot;title&quot;:&quot;Hello World!&quot;, &quot;slug&quot;:&quot;hello-world&quot;, &quot;publish_date&quot;:&quot;now(FIXME)&quot;}" );


INSERT INTO PostDoc_author_idx
  ( docid, author )
VALUES
  ( 1, "matt"  );

INSERT INTO PostDoc_slug_idx
  ( docid, slug )
VALUES
  ( 1, "hello-world"  );

---

INSERT INTO DocStore
  ( id, doctype, data )
VALUES
  ( 2, "PostDoc", "{&quot;author&quot;:&quot;dan&quot;, &quot;body&quot;:&quot;I'm the post body.&quot;, &quot;title&quot;:&quot;Hello World!&quot;, &quot;slug&quot;:&quot;hello-world&quot;, &quot;publish_date&quot;:&quot;now(FIXME)&quot;}" );


INSERT INTO PostDoc_author_idx
  ( docid, author )
VALUES
  ( 2, "dan"  );

INSERT INTO PostDoc_slug_idx
  ( docid, slug )
VALUES
  ( 2, "hello-world"  );

--

select * from DocStore 
where id in (
  select docid from DocStoreIdx
  where field = 'author' and value = 'matt'
);


select id from DocStore 
where id in (
  select A.docid from PostDoc_author_idx as A, PostDoc_slug_idx as B
  where A.author = 'matt'
  and B.slug = 'hello-world'
  and A.docid = B.docid
  order by B.slug
);


select id from DocStore 
where id in (
  select A.docid from PostDoc_author_idx as A, PostDoc_slug_idx as B
  where B.slug = 'hello-world'
  and A.docid = B.docid
  order by A.author DESC
);

-- FULL (WORKING) SQL:

select DocStore.* from DocStore, PostDoc_author_idx
where DocStore.id in (
  select PostDoc_author_idx.docid from PostDoc_author_idx, PostDoc_slug_idx
  where PostDoc_slug_idx.slug = 'hello-world'
  and PostDoc_author_idx.docid = PostDoc_slug_idx.docid
)
and PostDoc_author_idx.docid = DocStore.id
order by PostDoc_author_idx.author ASC;


select DocStore.* from DocStore, PostDoc_author_idx
where DocStore.id in (
  select PostDoc_author_idx.docid from PostDoc_author_idx, PostDoc_slug_idx
  where PostDoc_slug_idx.slug = 'hello-world'
  and PostDoc_author_idx.docid = PostDoc_slug_idx.docid
)
and PostDoc_author_idx.docid = DocStore.id
order by PostDoc_author_idx.author ASC;

*/