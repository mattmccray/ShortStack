<?php 

class Tag extends Model {

  protected $schema = array(
    'name' => 'varchar(255)',
  );

  protected $hasMany = array(
    'posts' => array('through'=>'Tagging'),
  );


  public static function FindOrCreate($name) {
    $tag = mdl('Tag')->where('name')->eq($name)->get();
    if($tag == null) {
      $tag = new Tag();
      $tag->update(array(
        "name" => $name
      ));
      $tag->save();
    }
    return $tag;
  }
}
