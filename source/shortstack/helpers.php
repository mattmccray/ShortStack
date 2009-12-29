<?php

function url_for($controller) {
  return BASEURI . $controller;
}

function link_to($controller, $label, $className="") {
  return '<a href="'. url_for($controller) .'" class="'. $className .'">'. $label .'</a>';
}

function slugify($str) {
   // only take alphanumerical characters, but keep the spaces and dashes too...
   $slug = preg_replace("/[^a-zA-Z0-9 -]/", "", trim($str));
   $slug = preg_replace("/[\W]{2,}/", " ", $slug); // replace multiple spaces with a single space
   $slug = str_replace(" ", "-", $slug); // replace spaces by dashes
   $slug = strtolower($slug);  // make it lowercase
   return $slug;
}

function underscore($str) {
  $str = str_replace("-", " ", $str);
  $str = preg_replace_callback('/[A-Z]/', "underscore_matcher", trim($str));
  $str = str_replace(" ", "", $str);
  $str = preg_replace("/^[_]?(.*)$/", "$1", $str);
  return $str;
}
/**
 * @ignore
 */
function underscore_matcher($match) { return "_" . strtolower($match[0]); }

function camelize($str) {
  $str = str_replace("-", "", $str);
	$str = 'x '.strtolower(trim($str));
	$str = ucwords(preg_replace('/[\s_]+/', ' ', $str));
	return substr(str_replace(' ', '', $str), 1);
}

function use_helper($helper) {
  if(! strpos($helper, 'elper') > 0) $helper .= "_helper";
  require_once( ShortStack::HelperPath($helper) );
}
/**
 * @ignore
 */
function getBaseUri() { // Used by the Dispatcher
	return str_replace("/".$_SERVER['QUERY_STRING'], "/", array_shift(explode("?", $_SERVER['REQUEST_URI'])));
}

function debug($obj) {
  echo "<pre>";
  print_r($obj);
  echo "</pre>\n";
}

function doc($doctype, $id=null) {// For use with documents
  return ($id == null) ? Document::Find($doctype) : Document::Get($doctype, $id);
}

function mdl($objtype, $id=null) {// For use with documents
  return ($id == null) ? Model::Find($objtype) :  Model::Get($objtype, $id);
}

function get($modelName, $id=null) {
  return ($modelName::$IsDocument) ? doc($modelName, $id) : mdl($modelName, $id);
}

