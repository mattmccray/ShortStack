<?php 

class Tagging extends ModelJoiner {

  protected $joins = array( 'Post', 'Tag' );

}
