<?php

function h($content) {
  return htmlentities($content);
}

function display_errors($allErrors, $error_msgs=array()) {
  $msgs = array_merge($error_msgs, array(
    'required' => ' is required.',
    'email' => ' must be a valid email address.',
    'numeric' => ' must be a number.',
    'contain' => ' has an invalid value.',
    'contains' => ' has an invalid value.',
  ));
  $html = "<fieldset class=\"errors\"><legend>Errors</legend><p>Sorry, the following errors were encountered while trying to process your request:</p>";
  $html .="<dl>";
  foreach ($allErrors as $field => $errors) {
    # code...
    $html .= "<dt>$field</dt><dd><ul>";
    foreach ($errors as $error) {
      $errMsg = (array_key_exists($error, $msgs)) ? $msgs[$error] : $error;
      $html .= "<li class=\"$error\">".$errMsg."</li>";
    }
    $html .= "</ul></dd>";
  }
  return $html."</dl></fieldset>";
}

function url_for($controller) {
  return BASEURI . $controller;
}

function link_to($controller, $label, $className="") {
  return '<a href="'. url_for($controller) .'" class="'. $className .'">'. $label .'</a>';
}

function select_box($name, $options, $default=null, $className='') {
  $html = "<select id=\"$name\" name=\"$name\" class=\"$className\">";
  foreach ($options as $value => $text) {
    $html .="<option value=\"$value\" ".(($value == $default) ? ' selected' : '').">$text</option>";
  }
  return $html."</select>";
}
function to_options($mdlArr, $textField='title', $keyField='id') {
  $opts = array();
  foreach ($mdlArr as $mdl) {
    $opts[$mdl->{$keyField}] = $mdl->{$textField};
  }
  return $opts;
}

function ends_with($test, $string) {
  $strlen = strlen($string);
  $testlen = strlen($test);
  if ($testlen > $strlen) return false;
  return substr_compare($string, $test, -$testlen) === 0;
//  return substr_compare($str, $test, -strlen($test), strlen($test)) == 0;
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

function pluralize($str, $force = FALSE) {
	$str = strtolower(trim($str));
	$end3 = substr($str, -3);
	$end1 = substr($str, -1);
	if ($end3 == 'eau') { $str .= 'x'; }
	elseif ($end3 == 'man') { $str = substr($str, 0, -2).'en'; }
	elseif (in_array($end3, array('dum', 'ium', 'lum'))) { $str = substr($str, 0, -2).'a'; }
	elseif (strlen($str) > 4 && in_array($end3, array('bus', 'eus', 'gus', 'lus', 'mus', 'pus'))) { $str = substr($str, 0, -2).'i'; }
	elseif ($end3 == 'ife') { $str = substr($str, 0, -2).'ves'; }
	elseif ($end1 == 'f') { $str = substr($str, 0, -1).'ves'; }
	elseif ($end1 == 'y') {	$str = substr($str, 0, -1).'ies';	}
	elseif (in_array($end1, array('h', 'o', 'x'))) { $str .= 'es'; }
	elseif ($end1 == 's') {	if ($force == TRUE)	{ $str .= 'es'; } }
	else { $str .= 's'; }
	return $str;
}

function singularize($str) {
	$str = strtolower(trim($str));
	$end5 = substr($str, -5);
	$end4 = substr($str, -4);
	$end3 = substr($str, -3);
	$end2 = substr($str, -2);
	$end1 = substr($str, -1);
	if ($end5 == 'eives') { $str = substr($str, 0, -3).'f'; }
	elseif ($end4 == 'eaux') { $str = substr($str, 0, -1); }
	elseif ($end4 == 'ives') { $str = substr($str, 0, -3).'fe'; }
	elseif ($end3 == 'ves') { $str = substr($str, 0, -3).'f'; }
	elseif ($end3 == 'ies') {	$str = substr($str, 0, -3).'y'; }
	elseif ($end3 == 'men') {	$str = substr($str, 0, -2).'an'; }
	elseif ($end3 == 'xes' && strlen($str) > 4 OR in_array($end3, array('ses', 'hes', 'oes'))) { $str = substr($str, 0, -2); }
	elseif (in_array($end2, array('da', 'ia', 'la'))) { $str = substr($str, 0, -1).'um'; }
	elseif (in_array($end2, array('bi', 'ei', 'gi', 'li', 'mi', 'pi'))) { $str = substr($str, 0, -1).'us'; }
	else { if ($end1 == 's')	$str = substr($str, 0, -1); }
	return $str;
}

function object_sort(&$data, $key) {
  for ($i = count($data) - 1; $i >= 0; $i--) {
    $swapped = false;
    for ($j = 0; $j < $i; $j++){
      if ($data[$j]->$key > $data[$j + 1]->$key) { 
        $tmp = $data[$j];
        $data[$j] = $data[$j + 1];        
        $data[$j + 1] = $tmp;
        $swapped = true;
      }
    }
    if (!$swapped) return;
  }
}

function object_sort_r(&$object, $key) { 
  for ($i = count($object) - 1; $i >= 0; $i--) { 
    $swapped = false; 
    for ($j = 0; $j < $i; $j++) { 
      if ($object[$j]->$key < $object[$j + 1]->$key) { 
        $tmp = $object[$j]; 
        $object[$j] = $object[$j + 1];       
        $object[$j + 1] = $tmp; 
        $swapped = true; 
      } 
    } 
    if (!$swapped) return; 
  } 
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

function getQueryString() { // Used by the Dispatcher
  $path_segs = explode("?", $_SERVER['REQUEST_URI']);
  $uri = array_shift($path_segs);
  $qs = array_shift($path_segs);
  $args = array();
  parse_str($qs, $args);
  return $args;
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
  return (ShortStack::IsDocument($modelName)) ? doc($modelName, $id) : mdl($modelName, $id);
  // return ($modelName::$IsDocument) ? doc($modelName, $id) : mdl($modelName, $id); // Only works in 5.3+
}

// Validation support
function validate($src, $ruleset, &$err) {
  $err = array();
  foreach ($ruleset as $field => $rulesrc) {
    $rules = explode('|', $rulesrc);
    foreach ($rules as $testfunc) {
      $args = explode(':', $testfunc);
      $func = array_shift($args);
      if(function_exists('validator_'.$func)) {
        @array_unshift($args, $src[$field]);
        if(!call_user_func_array('validator_'.$func, $args)) {
          if(!array_key_exists($field, $err)) $err[$field] = array();
          $err[$field][] = $func;
        }
      }
      else if(function_exists($func)) {
        @array_unshift($args, $src[$field]);
        if(!call_user_func_array($func, $args)) {
          if(!array_key_exists($field, $err)) $err[$field] = array();
          $err[$field][] = $func;
        }
      }
      else {
        if(!array_key_exists($field, $err)) $err[$field] = array();
        throw new Exception("Validator $testfunc not found.");
//        $err[$field][] = $testfunc." <- Validator not found";
      }
    }
  }
  return (count($err) == 0);
}

function validator_required($value) {
  return (isset($value) && $value != null && $value != "" && $value != " " );
}

function validator_numeric($value) {
  if(isset($value)) {
    return is_numeric($value);
  }
  else {
    return true;
  }
}

function validator_contains() {
  $args = func_get_args();
  $value = array_shift($args);
  if(isset($value)) {
    return in_array($value, $args);
  }
  else {
    return true;
  }
}

function validator_email($value) {
  if(isset($value)) {
    return preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/", $value);
  }
  else {
    return true;
  }
}