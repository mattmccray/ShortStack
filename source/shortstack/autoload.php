<?php

function __autoload($className) {
  if(stripos($className, '.') >= 0) {
    $path_parts = explode('.', $className);
    $className = $path_parts[0]; 
  }
  $classPath = ShortStack::AutoLoadFinder($className);
  if(!file_exists($classPath)) {
    return eval("class {$className}{public function __construct(){throw new NotFoundException('Class not found!');}}");
  } else {
    require_once($classPath);
  }
}


