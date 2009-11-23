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

  <h1>Error Encountered (<?php echo $error_class ?>)</h1>
  <p id="msg" class="code"><?php echo $msg ?></p>

  <h2>Backtrace</h2>
  <?php if(Hopnote::$root): ?>
    <p id="app_root"><span>APP_ROOT:</span> <?php echo Hopnote::$root ?></p>
  <?php endif; ?>
  <ol id="trace" class="code">
    <?php foreach($trace as $line): ?>
      <?php $function = $line['function'] ? ":in {$line['function']}" : '' ?>
      <li><?php echo "{$line['file']}:{$line['line']}$function" ?></li>
    <?php endforeach; ?>
  </ol>

  <h2>Request</h2>
  <div id="params">
    <pre>
<?php echo print_r($_REQUEST, true) ?>
    </pre>
  </div>

</body>
</html>
