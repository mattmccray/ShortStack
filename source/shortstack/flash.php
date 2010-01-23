<?php


class Flash implements ArrayAccess {
  public $now = array();
  public function __construct($key = "_FLASH_DATA_") {
  	$this->key = $key;
  	@session_start();
  	$this->now = isset($_SESSION[$this->key]) ? $_SESSION[$this->key] : array();
  }
  public function offsetSet($offset, $value) {
    $_SESSION[$this->key][$offset] = $value;
  }
  public function offsetExists($offset) {
    return isset($this->now[$offset]);
  }
  public function offsetUnset($offset) {
    unset($this->now[$offset]);
    unset($_SESSION[$this->key][$offset]);
  }
  public function offsetGet($offset) {
    $_SESSION[$this->key] = array(); // Clears the session array once a var has been accessed
    return isset($this->now[$offset]) ? $this->now[$offset] : null;
  }
}