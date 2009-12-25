<?php

class DB {
  
  // TODO: Change function names to TitleCase
  
  static protected $pdo;
  
  static public function Connect($conn, $user="", $pass="", $options=array()) {
    self::$pdo = new PDO($conn, $user, $pass, $options);
  }
  
  static public function Query($sql_string) {
    return self::$pdo->query($sql_string);
  }
  
  static public function GetLastError() {
    return self::$pdo->errorInfo();
  }

  static public function FetchAll($sql_string) {
    $statement = self::Query($sql_string);
    return $statement->fetchAll(); // PDO::FETCH_GROUP
  }
  
  static public function EnsureNotEmpty() {
    $statement = self::Query('SELECT name FROM sqlite_master WHERE type = \'table\'');
    $result = $statement->fetchAll();
        
    if( sizeof($result) == 0 ){
      define("EMPTYDB", true);
      throw new EmptyDbException("Database has no tables.");
    } else {
      define("EMPTYDB", false);
    }
  }
}