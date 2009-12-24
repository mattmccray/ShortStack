<?php

class DB
{
  static protected $pdo; // Public for now...
  
  static public function connect($conn, $user="", $pass="", $options=array()) {
    self::$pdo = new PDO($conn, $user, $pass, $options);
  }
  
  static public function query($sql_string) {
    return self::$pdo->query($sql_string);
  }
  
  static public function getLastError() {
    return self::$pdo->errorInfo();
  }

  static public function fetchAll($sql_string) {
    $statement = self::query($sql_string);
    return $statement->fetchAll(); // PDO::FETCH_GROUP
  }
  
  static public function ensureNotEmpty() {
    $statement = self::query('SELECT name FROM sqlite_master WHERE type = \'table\'');
    $result = $statement->fetchAll();
        
    if( sizeof($result) == 0 ){
      define("EMPTYDB", true);
      throw new EmptyDbException("Database has no tables.");
    } else {
      define("EMPTYDB", false);
    }
  }
}