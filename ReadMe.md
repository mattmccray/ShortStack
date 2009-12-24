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
    
Define the doctype (classname) and any indexes for querying/ordering by:

    class Post extends DocumentModel {
      protected $indexes = array(
        'slug' => 'TEXT',
        'author' => 'TEXT',
        'publish_date' => 'TIMESTAMP'
      );
      
      protected function beforeCreate() {
        if($this->has('title'))
          $this->slug = slugify($this->title);
      }
      protected function beforeSave() {
        if($this->hasChanged('body_src'));
          $this->body = markdown($this->body_src);
      }
    }


Initialize the DB (if you've created new documents/indexes):

    ShortStack::InitializeDatabase();


Create a Post:

    $post = new Post();
    $post->update(array(
      'title' => 'Hello World!'
      'body_src' => "I'm the post body!",
      'author' => "M@",
      'publish_date' => 123456789,
    ));
    $post->save();

Get Post With ID of 1:

    $post = doc('Post', 1);


Query Posts:

    $posts = doc('Post')->where('author')->eq('M@')->order('publish_date', 'desc');
    
    foreach($posts as $post) {
      echo $post->title;
      // Use whatever fields you'd like ...
    }


Update Post:

    $post = doc('Post', 1);
    $post->update(array(
      'any_key_you_like' => "It won't matter"
    ));
    $post->save();

Bulk Update Posts:
    
    doc('Post')->where('author')->eq('M@')->update(array(
      'author' => 'M@ McCray'
    ));


Destroy Post:

    $post->destroy();
    doc('Post', 1)->destroy()
    Document::Destroy('Post', 1);

Bulk Destroy Posts -- Kinda dangerous:

    doc('Post')->where('author')->neq('M@')->destroy();

Coming soon: Relationships `hasMany` and `belongsTo`.


## Todos

* Use PDO prepared statements where possible?
* Better error handling.

