<?xml version="1.0" encoding="UTF-8"?>
<notice version="2.0">
  <api-key><?php echo Hopnote::$api_key ?></api-key>
  <notifier>
    <name>Hopnote</name>
    <version>1.0</version>
    <url>http://hopnote.lanalot.com</url>
  </notifier>
  <error>
    <class><?php echo $error_class ?></class>
    <message><?php echo $msg ?></message>
    <backtrace>
<?php foreach($trace as $entry): ?>
<?php $method = $entry['function'] ? " method=\"{$entry['function']}\"" : '' ?>
      <line<?php echo $method ?> file="<?php echo $entry['file'] ?>" number="<?php echo $entry['line'] ?>"/>
<?php endforeach; ?>
    </backtrace>
  </error>
  <request>
<?php
  $port = $_SERVER['SERVER_PORT'] == 80 ? '' : ":{$_SERVER['SERVER_PORT']}";
  $url = "http://{$_SERVER['HTTP_HOST']}$port{$_SERVER['REQUEST_URI']}";
?>
    <url><?php echo htmlentities($url) ?></url>
    <component>abc</component>
    <action>xyz</action>
<?php if(isset($_REQUEST) && !empty($_REQUEST)): ?>
    <params>
<?php foreach($_REQUEST as $k => $v): ?>
      <var key="<?php echo $k ?>"><?php echo htmlentities($v) ?></var>
<?php endforeach; ?>
    </params>
<?php endif; ?>
<?php if(session_id() && !empty($_SESSION)): ?>
    <session>
<?php foreach($_SESSION as $k => $v): ?>
      <var key="<?php echo $k ?>"><?php echo htmlentities($v) ?></var>
<?php endforeach; ?>
    </session>
<?php endif; ?>
<?php if(isset($_SERVER) && !empty($_SERVER)): ?>
    <cgi-data>
<?php foreach($_SERVER as $k => $v): ?>
      <var key="<?php echo $k ?>"><?php echo htmlentities($v) ?></var>
<?php endforeach; ?>
    </cgi-data>
<?php endif; ?>
  </request>
  <server-environment>
<?php if(Hopnote::$root): ?>
    <project-root><?php echo Hopnote::$root ?></project-root>
<?php endif; ?>
    <environment-name><?php echo Hopnote::$environment ?></environment-name>
  </server-environment>
</notice>
