<?php

# TODO : provide the ability to squelch params (password, credit card, etc)
# TODO : format params on the debug page
# TODO : parse fatal error message into file, line and error
# TODO : provide a better looking default fivehundred page
class Hopnote {

  protected static $api_key;
  protected static $root;
  protected static $environment;
  protected static $deployed;
  protected static $errors;
  protected static $fivehundred;
  protected static $fatals;

  public static function register_handlers($api_key, $options = array()) {

    $defaults = array(
      'api_key'     => $api_key,
      'environment' => 'production',
      'deployed'    => TRUE,
      'root'        => NULL,
      'errors'      => E_ALL,
      'fivehundred' => dirname(__FILE__) . '/500.html',
      'fatals'      => FALSE,
    );

    foreach($defaults as $opt => $default)
      if(isset($options[$opt]))
        self::$$opt = $options[$opt];
      else
        self::$$opt = $default;

    set_error_handler('Hopnote::basic_error_handler', self::$errors);

    set_exception_handler('Hopnote::exception_handler');
      
    if(self::$fatals) {
      ini_set('display_errors', 'On');
      ini_set('error_append_string', "HOPNOTE_FATAL");
      ob_start('Hopnote::fatal_error_handler');
    }

  }

  public static function basic_error_handler($errno, $errstr) {
    $error_classes = array(
      E_WARNING           => 'E_WARNING',
      E_USER_WARNING      => 'E_USER_WARNING',
      E_NOTICE            => 'E_NOTICE',
      E_USER_NOTICE       => 'E_USER_NOTICE',
      E_USER_ERROR        => 'E_USER_ERROR',
      E_STRICT            => 'E_STRICT',
      E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
      E_DEPRECATED        => 'E_DEPRECATED',
      E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    );
    $trace = debug_backtrace();
    $clean_ob = true;
    echo self::handle_error($error_classes[$errno], $errstr, $trace, $clean_ob);
    exit;
  }

  public static function exception_handler($e) {

    $trace = $e->getTrace();
    array_unshift($trace, array(
      'file' => $e->getFile(),
      'line' => $e->getLine(),
    ));

    $clean_ob = true;
    echo self::handle_error(get_class($e), $e->getMessage(), $trace, $clean_ob);
    exit;
  }

  /**
   * fatal_error_handler is an output buffer callback.  In order to work, 
   * it should be the first output-buffer opened.
   */
  public static function fatal_error_handler(&$buffer) {
    $lines = explode("\n", $buffer);
    $count = count($lines);
    if($lines[$count - 1] == 'HOPNOTE_FATAL') {
      $msg = $lines[$count - 2];
      $trace = array();  # php won't generate a stacktrace here
      $clean_ob = false; # calls to ob methods are not allowed here
      $buffer = self::handle_error('FATAL', $msg, $trace, $clean_ob);
    }
    return $buffer;
  }

  protected static function handle_error($error_class, $msg, $trace, $clean) {

    header('HTTP/1.1 500 Internal Server Error'); 

    # TODO : make sure this works in & out of fatal errors
    if($clean) {
      $level = ob_get_level();
      for($i = 0; $i < $level; ++$i)
        ob_end_clean();
    }

    $trace = self::parse_trace($trace);

    if(self::$deployed) {
      self::notify_hoptoad($error_class, $msg, $trace);
      return self::fivehundred_page();
    } else {
      return self::debug_page($error_class, $msg, $trace);
    }
  }

  protected static function parse_trace($backtrace) {

    # trigger_error inserts a funny blank entry on top of the backtrace,
    # we want to drop it if encountered
    if(!isset($backtrace[0]['file']) || !isset($backtrace[0]['line']))
      array_shift($backtrace);

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

  protected static function notify_hoptoad($error_class, $msg, $trace) {
    $ctx = stream_context_create(array(
      'http' => array(
        'method' => 'POST',
        'content' => self::request_xml($error_class, $msg, $trace),
        'header' => "Content-type: text/xml\r\n",
      ),
    ));
    $url = 'http://hoptoadapp.com/notifier_api/v2/notices';
    if($fp = fopen($url, 'rb', false, $ctx))
      stream_get_contents($fp);
  }

  protected static function fivehundred_page() {
    return file_get_contents(self::$fivehundred);
  }

  /** 
   * Returns an HTML error page suitable for displaying in a development
   * environment.  
   */
  protected static function debug_page($error_class, $msg, $trace) {

    $page = array();

    array_push($page, <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xml:lang='en' xmlns='http://www.w3.org/1999/xhtml'>
<head>
  <title>Error Encountered</title>
  <meta content='text/html;charset=UTF-8' http-equiv='content-type' />
  <style type="text/css">
    body {
      font-family: Arial, sans-serif;
      padding: 0 10px;
      margin: 0;
    }
    p#msg {
      padding: 10px;
      background-color: #ccc;
    }
    #trace {
      list-style-type: none; 
      padding: 10px; 
      margin: 0; 
      background-color: #ccc;
    }
    #trace li {
      padding: 5px; 
      margin: 0; 
    }
    .code {
      font-family: courier, serif;
    }
  </style>
</head>
<body>

  <h1>Error Encountered ($error_class)</h1>
  <p id="msg" class="code">$msg</p>

  <h2>Backtrace</h2>
EOT
    );

    if(Hopnote::$root) {
      $root = Hopnote::$root;
      $p = "<p id=\"app_root\"><span>APP_ROOT:</span>";
      $p .= Hopnote::$root;
      $p .= '</p>';
      array_push($page, $p);
    }

    array_push($page, '<ol id="trace" class="code">');
    foreach($trace as $line) {
      $function = $line['function'] ? ":in {$line['function']}" : '';
      array_push($page, "<li>{$line['file']}:{$line['line']}$function</li>");
    }
    array_push($page, '</ol>');

    if(isset($_REQUEST)) {
      array_push($page, '<h2>Request</h2>');
      array_push($page, '<div id="params"><pre>');
      #array_push($page, print_r($_REQUEST, true));
      array_push($page, '</pre></div>');
    }

    return implode("\n", $page);

  }

  protected static function request_xml($error_class, $msg, $trace) {

    $xml = array();

    $api_key = self::$api_key;
    array_push($xml, '<' . '?xml version="1.0" encoding="UTF-8"?' . '>');
    array_push($xml, '<notice version="2.0">');
    array_push($xml, "<api-key>$api_key</api-key>");

    # notifier
    array_push($xml, '<notifier>');
    array_push($xml, '<name>Hopnote</name>');
    array_push($xml, '<version>1.0</version>');
    array_push($xml, '<url>http://hopnote.lanalot.com</url>');
    array_push($xml, '</notifier>');

    # error, class, message and backtrace
    array_push($xml, '<error>');
    array_push($xml, "<class>$error_class</class>");
    array_push($xml, "<message>$msg</message>");
    if(!empty($trace)) {
      array_push($xml, '<backtrace>');
      foreach($trace as $t) {
        $method = $t['function'] ? " method='{$t['function']}'" : '';
        array_push($xml, "<line$method file='{$t['file']}' number='{$t['line']}'/>");
      }
      array_push($xml, '</backtrace>');
    }
    array_push($xml, '</error>');

    # request
    # TODO : add support for controller & action 
    $port = $_SERVER['SERVER_PORT'] == 80 ? '' : ":{$_SERVER['SERVER_PORT']}";
    $url = "http://{$_SERVER['HTTP_HOST']}$port{$_SERVER['REQUEST_URI']}";
    $url = htmlentities($url);
    array_push($xml, '<request>');
    array_push($xml, "<url>$url</url>");
    array_push($xml, '<component>abc</component>');
    array_push($xml, '<action>xyz</action>');
    if(isset($_REQUEST) && !empty($_REQUEST)) {
      array_push($xml, '<params>');
      foreach($_REQUEST as $k => $v) {
        $v = htmlentities($v);
        array_push($xml, "<var key='$k'>$v</var>");
      }
      array_push($xml, '</params>');
    }
    if(session_id() && !empty($_SESSION)) {
      array_push($xml, '<session>');
      foreach($_SESSION as $k => $v) {
        $v = htmlentities($v);
        array_push($xml, "<var key='$k'>$v</var>");
      }
      array_push($xml, '</session>');
    }
    if(isset($_SERVER) && !empty($_SERVER)) {
      array_push($xml, '<cgi-data>');
      foreach($_SERVER as $k => $v) {
        $v = htmlentities($v);
        array_push($xml, "<var key='$k'>$v</var>");
      }
      array_push($xml, '</cgi-data>');
    }
    array_push($xml, '</request>');

    # server environment, project root and env name
    $root = self::$root;
    $env = self::$environment;
    array_push($xml, '<server-environment>');
    if($root)
      array_push($xml, "<project-root>$root</project-root>");
    array_push($xml, "<environment-name>$env</environment-name>");
    array_push($xml, '</server-environment>');

    # close the notice
    array_push($xml, '</notice>');

    return implode("\n", $xml);
  }
  
}
