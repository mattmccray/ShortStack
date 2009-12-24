# ShortStack

A simple, easily customized, MVC framework for PHP. Currently built for use with PHP > 5, PDO, and SQLite.

* DB Abstraction Layer (over PDO)
* Models
* Document Store (Simple schema-less storage in SQLite)
* Controllers/Actions (hybrid)
* Templates
* Caching (coming soon)


## Document Store

Still very early, but here are some usage examples...
    
Define the doctype and any indexes for querying/ordering by:

    Document::Define('Post', array(
      'slug' => 'INTEGER',
      'author' => 'STRING',
      'publish_date' => 'TIMESTAMP'
    ));


Initialize the DB (if you've created new documents/indexes):

    Document::InitializeDatabase();


Create a Post:

    $post = new Post();
    $post->update(array(
      'title' => 'Hello World!',
      'slug' => 'hello-world',
      'body' => "I'm the post body.",
      'author' => "M@",
      'publish_date' => 123456789,
    ));
    $post->save();

Get Post With ID of 1:

    $post = Document::Get('Post', 1);


Query Posts:

    $posts = Document::Find('Post')->where('author')->eq('M@')->order('publish_date', 'desc');
    
    foreach($posts as $post) {
      echo $post->title;
      // Use whatever fields you'd like ...
    }


Update Post:

    $post = Document::Get('Post', 1);
    $post->update(array(
      'any_key_you_like' => "It won't matter"
    ));
    $post->save();

Bulk Update Posts:
    
    Document::Find('Post')->where('author')->eq('M@')->update(array(
      'author' => 'M@ McCray'
    ));


Destroy Post:

    $post->destroy();
    // Or
    Document::Destory('Post', 1);

Bulk Destroy Posts -- Kinda dangerous:


    Document::Find('Post')->where('author')->neq('M@')->destroy();

Coming soon: Better Document Class support, callbacks, `hasMany` and `belongsTo`.


