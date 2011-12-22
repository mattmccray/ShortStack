# ShortStack

A simple, easily customized, MVC framework for PHP. Currently built for use with PHP 5.2ish, PDO, and SQLite.

* DB Abstraction Layer (over PDO)
* Models with `hasMany`/`belongsTo` (and `hasMany(through)`) relationships
* `Document` Models (or schema-less models, loosely based on the [friendfeed][] design)
* Controllers/Actions (hybrid)
* Templates
* Pagination helper class
* View Caching
* Core Framework < 50kb in a single `.php` file

  [friendfeed]: http://bret.appspot.com/entry/how-friendfeed-uses-mysql


## Documents

To create a Document, just create a class that extends `Document` and define any fields (to index) that you want to query or sort by.

Example:    

    class Post extends Document {
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


Initialize the DB (first-time):

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
      // -or-
    $post = Document::Get('Post', 1);

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


Remove Post without firing callbacks:

    $post->kill();
      // - or -
    Document::Remove('Post', 1);


Bulk Destroy Posts -- Kinda dangerous:

    doc('Post')->where('author')->neq('M@')->destroy();

> The ORM can be used without the rest of the stack by include shortstack_orm.php instead of shortstack.php (both under dist/).

## Todos

* Use PDO prepared statements where possible?
* Better error handling.
* Remove all the @ cruft
