<?php

class Hopnote {

  protected static $api_key;
  protected static $root;
  protected static $environment;
  protected static $deployed;
  protected static $errors;
  protected static $fiveohoh;

  public static function register_handlers($api_key, $options = array()) {

    self::$api_key = $api_key;

    $defaults = array(
      'environment' => 'production',
      'deployed' => true,
      'root' => NULL,
      'errors' => E_ALL,
      'fiveohoh' => dirname(__FILE__) . '/500.html',
    );

    foreach($defaults as $opt => $default)
      if(isset($options[$opt]))
        self::$$opt = $options[$opt];
      else
        self::$$opt = $default;

    set_error_handler('Hopnote::error_handler', self::$errors);
    set_exception_handler('Hopnote::exception_handler');
  }

  public static function error_handler($errno, $errstr) {
    switch($errno) {
      case E_WARNING:
        $error_class = 'E_WARNING';
        break;
      case E_USER_WARNING:
        $error_class = 'E_USER_WARNING';
        break;
      case E_NOTICE:
        $error_class = 'E_NOTICE';
        break;
      case E_USER_NOTICE:
        $error_class = 'E_USER_NOTICE';
        break;
      case E_USER_ERROR:
        $error_class = 'E_USER_ERROR';
        break;
      case E_STRICT:
        $error_class =  "E_STRICT";
        break;
      case E_RECOVERABLE_ERROR:
        $error_class =  "E_RECOVERABLE_ERROR";
        break;
      case E_DEPRECATED:
        $error_class =  "E_DEPRECATED";
        break;
      case E_USER_DEPRECATED:
        $error_class =  "E_USER_DEPRECATED";
        break;
    }

    # trigger_error inserts a funny blank entry on top of the backtrace,
    # we want to drop it if encountered
    $trace = debug_backtrace();
    if(!isset($trace[0]['file']) || !isset($trace[0]['line']))
      array_shift($trace);

    self::handle_error($error_class, $errstr, $trace);
  }

  public static function exception_handler($e) {
    $trace = $e->getTrace();
    array_unshift($trace, array(
      'file' => $e->getFile(),
      'line' => $e->getLine(),
    ));
    self::handle_error(get_class($e), $e->getMessage(), $trace);
  }

  protected static function handle_error($error_class, $msg, $trace) {

    $trace = self::parse_trace($trace);

    # empty output buffers
    $level = ob_get_level();
    for($i = 0; $i < $level; ++$i)
      ob_end_clean();

    if(self::$deployed) {
      self::notify_hoptoad($error_class, $msg, $trace);
      self::display_500_page();
    } else {
      self::display_error_page($error_class, $msg, $trace);
    }
    exit;
  }

  protected static function parse_trace($backtrace) {
    $root = self::$root;
    $trace = array();
    for($i = 0; $i < count($backtrace); ++$i) {

      $entry = $backtrace[$i];

      # parse the file
      $file = $entry['file'];
      if($root && preg_match("#^$root(.+)#", $file, $matches))
        $file = "APP_ROOT{$matches[1]}";

      # check for a function
      $next_entry = isset($backtrace[$i + 1]) ? $backtrace[$i + 1] : NULL;
      if($next_entry && array_key_exists('function', $next_entry))
        $function = $next_entry['function'];
      else
        $function = '';

      array_push($trace, array(
        'file' => $file,
        'line' => $entry['line'],
        'function' => $function,
      ));
    }
    return $trace;
  }

  protected static function display_error_page($error_class, $msg, $trace) {
    ob_start();
    # TODO : display status 500
    include(dirname(__FILE__) . '/notice.html.php');
    ob_end_flush();
  }

  protected static function notify_hoptoad($error_class, $msg, $trace) {
    ob_start();
    include(dirname(__FILE__) . '/notice.xml.php');
    $xml = ob_get_contents();
    ob_end_clean();

    echo $xml;
    exit;

    $ctx = stream_context_create(array(
      'http' => array(
        'method' => 'POST',
        'content' => $xml,
        'header' => "Content-type: text/xml\r\n",
      ),
    ));
    $url = 'http://hoptoadapp.com/notifier_api/v2/notices';
    if($fp = fopen($url, 'rb', false, $ctx))
      stream_get_contents($fp);
  }

  protected static function display_500_page() {
    header('HTTP/1.1 500 Internal Server Error'); 
    include(self::$fiveohoh);
  }
  
}
