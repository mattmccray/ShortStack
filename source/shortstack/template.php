<?php
/**
 *
 */
class Template {
  public $context;
  private $path;
  private $silent;
  /**
   * 
   */
  function __construct($templatePath, $vars=array(), $swallowErrors=true) {
    $this->path = $templatePath;
    $this->context = $vars;
    $this->silent = $swallowErrors;
  }

  function __set($key, $value) {
    $this->context[$key] = $value;
  }

  function __get($key) {
    if(array_key_exists($key, $this->context)) {
      return $this->context[$key];
    }
    else {
      if($this->silent)
        return "";
      else
        throw new Exception("$key does not exist in template: ".$this->templatePath);
    }
  }

  function __call($name, $args=array()) {
    if($name == 'render') {
      @list($view, $params) = $args;
      if(strpos($view, '.php') < 1) $view .= ".php";
      $tmp = new Template($view, $this->context);
      $inlineContent = $tmp->fetch($params);
      $this->context = $tmp->context; // Allow the partial to 'pollute' the main content for message passing.
      return $inlineContent;
    }
    else if(function_exists($name)) {
      if(is_array($args)) {
        return call_user_func_array($name, $args);
      }
      else {
        return call_user_func($name, $args);
      }
    }
    else if($this->silent) {
      return "";
    }
    else {
      throw new Exception("Method $name doesn't exist!!");
    }
  }

  function assign($key, $value) {
    $this->context[$key] = $value;
  }

  function display($params=array()) {
    echo $this->fetch($params);
  }

  function fetch($params=array()) {
//    set_include_path($templathPath); // May be needed... Sometimes
//    $oldCtx = $this->context;
    if(is_array($params))
      $this->context = array_merge($this->context, $params);
    ob_start();
    extract( $this->context, EXTR_PREFIX_INVALID, 'ss' ); 
    if (FORCESHORTTAGS) { 
      echo eval('?>'.preg_replace("/;*\s*\?>/", "; ?>", str_replace('<?=', '<?php echo ', file_get_contents($this->path))));
    } else {
      include $this->path; 
    }
    $buffer = ob_get_contents();
    @ob_end_clean();
//    $this->context = $oldCtx;
    return $buffer;
  }

}