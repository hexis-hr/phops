<?php

/**
 * This file contains tools that enforce safe programing.
 *
 */


// we promote correct coding ! :)
//error_reporting(E_ALL | E_STRICT);
//ini_set('display_errors', 1);

_safety_report_data::$_configuration = array(
  'enabled' => true,
  'send_reports' => false,
  //'report_url' => 'http://ipv4.localhost/issues/report/',
  'report_url' => null,
  'display_errors' => true,
  'mode' => 'transitional', // transitional or strict
  'error_reporting' => E_ALL | E_STRICT,
  /*
  'report_exception' => array(
  ),
  'report_error_exception' => array(
  ),
  'report_caught_exception' => array(
  ),
  'report_everything' => array(
  ),
  /**/
);





if (isset($_SERVER['safety']))
  _safety_report_data::$_configuration = array_merge(_safety_report_data::$_configuration, $_SERVER['safety']);





function assertTrue ($condition, $message = null) {
  $args = func_get_args();
  $class = isset($args[2]) && is_string($args[2]) ? $args[2] : 'AssertException';
  $types = isset($args[2]) && is_array($args[2]) ? $args[2] : (isset($args[3]) ? $args[3] : null);
  if (!$condition) {
    $e = new $class($message);
    if (isset($types))
      $e->types = $types;
    throw $e;
  }
}

function rethrowIfNot (Exception $e, $types) {
  if (is_string($types))
    $types = array($types);
  if (isset($e->types)) {
    assertTrue(is_array($e->types));
    foreach ($types as $type) {
      if (in_array($type, $e->types))
        return;
    }
  }
  throw $e;
}

class AssertException extends Exception {}
class ShutdownException extends ErrorException {}



function exception_error_handler ($errno, $errstr, $errfile, $errline) {
  $e = new ErrorException($errstr, 0, $errno, $errfile, $errline);
  // try-catch should be able to catch php errors in strict mode which are then reported with report_caught_exception - don't call report_exception in this case
  if (_safety_report_data::$_configuration['mode'] == 'strict')
    throw $e;
  if (_safety_report_data::$_configuration['error_reporting'] & $errno)
    //report_error_exception($e);
    report_exception($e);
}

function shutdown_error_handler () {
  $error = error_get_last();
  if (isset($error)) {
    $error = (object) $error;
    // $error->type != E_WARNING is a workaround for error_get_last() being set even if error has been handled - php bug ?
    // checking 'Uncaught exception ' is a workaround for error_get_last() being set even if exception has been handled by set_exception_handler - php bug ?
    //if ($error->type != E_WARNING && substr($error->message, 0, strlen('Uncaught exception ')) != 'Uncaught exception ')
    if ($error->type != E_WARNING)
      if (_safety_report_data::$_configuration['error_reporting'] & $error->type)
        report_exception(new ShutdownException($error->message, 0, $error->type, $error->file, $error->line));
  }
}

function uncaught_exception_handler ($e) {
  //echo $e;
  //_send_exception_report($e);
  //var_dump(generate_json_exception_report($e)); echo $e; exit;
  report_exception($e);
}






// report_caught_exception ([$types,] $e)
function report_caught_exception ($e) {

  $arguments = func_get_args();
  
  foreach ($arguments as $index => $argument) {
    if (is_object($argument) && $argument instanceof Exception) {
      $arguments[$index] = clone $arguments[$index];
      $arguments[$index]->caught = true;
    }
  }
  
  return call_user_func_array('report_exception', $arguments);

  //echo $e;
  //var_dump(generate_json_exception_report($e));exit;
  
  /*

  if (!_safety_report_data::$_configuration['enabled'])
    return;
    
  $args = func_get_args();
  $types = isset($args[1]) ? $args[0] : null;
  $e = isset($args[1]) ? $args[1] : $args[0];

  if (isset($types)) {
    if (!isset($e->types))
      $e->types = array();
    $e->types = array_merge($e->types, (array) $types);
  }

  _send_exception_report('caught_exception', $e);

  /*
  $subject = "Caught exception (" . $_SERVER['baseHostname'] . ' - ' .  (isset($_SERVER['environment']) ? $_SERVER['environment'] : 'unknown') . "): " . $e->getMessage();
  $message = generate_exception_report($e);

  if (_safety_report_data::$_configuration['send_reports'])
    _send_exception_report(_safety_report_data::$_configuration['report_caught_exception'], $subject, $message);
  
  _send_exception_report(_safety_report_data::$_configuration['report_everything'], $subject, $message);
  /*
  
  if (_safety_report_data::$_configuration['display_errors']) {
    echo "<pre>";
    echo "Caught exception: <br />";
    echo htmlspecialchars($e);
    echo "</pre>";
  }

  /**/

}

/*
// report_error_exception([$types, ] $e)
function report_error_exception ($e) {
  //return;
  
  if (!_safety_report_data::$_configuration['enabled'])
    return;
  
  $args = func_get_args();
  $types = isset($args[1]) ? $args[0] : null;
  $e = isset($args[1]) ? $args[1] : $args[0];

  if (isset($types)) {
    if (!isset($e->types))
      $e->types = array();
    $e->types = array_merge($e->types, (array) $types);
  }
  
  _send_exception_report('error_exception', $e);


  /*
  $subject = "Error exception (" . $_SERVER['baseHostname'] . ' - ' .  (isset($_SERVER['environment']) ? $_SERVER['environment'] : 'unknown') . "): " . $e->getMessage();
  $message = generate_exception_report($e);

  if (_safety_report_data::$_configuration['send_reports'])
    _send_exception_report(_safety_report_data::$_configuration['report_error_exception'], $subject, $message);
  
  _send_exception_report(_safety_report_data::$_configuration['report_everything'], $subject, $message);
  /*
  
  if (_safety_report_data::$_configuration['display_errors']) {
    echo "<pre>";
    echo "Error exception: <br />";
    echo htmlspecialchars($e);
    echo "</pre>";
  }

}
/**/

/*
function report_shutdown_exception ($e) {

  if (!_safety_report_data::$_configuration['enabled'])
    return;
  
  _send_exception_report('shutdown_exception', $e);
  
  /*
  $subject = "Shutdown exception (" . $_SERVER['baseHostname'] . ' - ' . (isset($_SERVER['environment']) ? $_SERVER['environment'] : 'unknown') . "): " . $e->getMessage();
  $message = generate_exception_report($e);
  
  if (_safety_report_data::$_configuration['send_reports'])
    _send_exception_report(_safety_report_data::$_configuration['report_exception'], $subject, $message);
  
  _send_exception_report(_safety_report_data::$_configuration['report_everything'], $subject, $message);
  /*

  if (_safety_report_data::$_configuration['display_errors']) {
    echo "<pre>";
    echo "Shutdown exception: <br />";
    echo htmlspecialchars($e);
    echo "</pre>";
  }

}
/**/

// report_exception ([$types,] $e)
function report_exception ($e) {

  if (!_safety_report_data::$_configuration['enabled'])
    return;

  $args = func_get_args();
  $types = isset($args[1]) ? $args[0] : null;
  $e = isset($args[1]) ? $args[1] : $args[0];

  if (isset($types)) {
    if (!isset($e->types))
      $e->types = array();
    $e->types = array_merge($e->types, (array) $types);
  }

  //_send_exception_report('exception', $e);
  _send_exception_report($e);

  /*
  $subject = "Uncaught exception (" . $_SERVER['baseHostname'] . ' - ' . (isset($_SERVER['environment']) ? $_SERVER['environment'] : 'unknown') . "): " . $e->getMessage();
  $message = generate_exception_report($e);

  if (_safety_report_data::$_configuration['send_reports'])
    _send_exception_report(_safety_report_data::$_configuration['report_exception'], $subject, $message);

  _send_exception_report(_safety_report_data::$_configuration['report_everything'], $subject, $message);
  /**/
  
  if (_safety_report_data::$_configuration['display_errors']) {
    echo "<pre>";
    //echo "Exception: <br />";
    echo (isset($e->caught) && $e->caught ? 'Caught' : 'Uncaught') . ' ' . get_class($e) . ": <br />";
    echo htmlspecialchars($e);
    echo "</pre>";
  }

}

/*
function generate_exception_report ($e) {

  if (!session_id())
    session_start();

  $file = str_replace(array('\\', '/'), array(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR), $e->getFile());
  if (isset($_SERVER['SCRIPT_FILENAME']))
    $baseCodePath = str_replace(array('\\', '/'), array(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR), dirname($_SERVER['SCRIPT_FILENAME']));

  if (isset($baseCodePath) && substr($file, 0, strlen($baseCodePath)) == dirname($baseCodePath))
    $relativeFile = ltrim(substr($file, strlen($baseCodePath), '/\\'));
    
  $fileContent = file_get_contents($file);
  $currentLine = $e->getLine() - 10 >= 0 ? $e->getLine() - 10 : 0;
  $codeSnippetLines = array_slice(explode("\n", $fileContent), $currentLine, 20);
  foreach ($codeSnippetLines as $k => $codeSnippetLine) {
    $currentLine++;
    if (!isset($maxLineNumberLength))
      $maxLineNumberLength = strlen($currentLine) + 1;
    //$codeSnippetLines[$k] = ($currentLine == $e->getLine() ? str_repeat('*', $maxLineNumberLength) : str_pad($currentLine, $maxLineNumberLength, '0', STR_PAD_LEFT))
    //  . " " . $codeSnippetLine;
    $codeSnippetLines[$k] = str_pad($currentLine, $maxLineNumberLength, '0', STR_PAD_LEFT) . " " . ($currentLine == $e->getLine() ? ">" : " ") . " | " . $codeSnippetLine;
  }
  $codeSnippet = implode("\n", $codeSnippetLines);

  ob_start();
  echo "<!DOCTYPE html><html><head><title>" . htmlspecialchars($e->getMessage()) . "</title></head><body>";
  
  echo "<h1>General info</h1>";
  echo "<pre>";
  ob_start();
  echo "Message: " . $e->getMessage() . "\r\n";
  if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI']))
    echo "URL"
      . (isset($_SERVER['REQUEST_METHOD']) ? ' (' . strtoupper($_SERVER['REQUEST_METHOD']) . ')' : '')
      . ': '
      . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}" . "\r\n";
  if (isset($_SERVER['HTTP_REFERER']))
    echo "URL Referer: " . $_SERVER['HTTP_REFERER'] . "\r\n";
  echo "Code location: " . (isset($relativeFile) ? $relativeFile : $file) . ':' . $e->getLine() . "\r\n";
  if (isset($_SERVER['requestId']) || session_id())
    echo (isset($_SERVER['requestId']) ? "Request" : '')
      . (isset($_SERVER['requestId']) && session_id() ? '/' : '')
      . (session_id() ? 'Session' : '') . ' id: '
      . (isset($_SERVER['requestId']) ? $_SERVER['requestId'] : '')
      . (isset($_SERVER['requestId']) && session_id() ? '/' : '')
      . (session_id() ? session_id() : '')
      . "\r\n";
  echo "Time: " . date('Y-m-d H:i:s') . " (" . date('r') . ")" . "\r\n";
  if (isset($e->types))
    echo "Exception types: " . implode(", ", $e->types) . "\r\n";
  if (isset($_POST) || isset($_SESSION)) {
    if (isset($_POST))
      echo "POST " . (count($_POST) == 0 ? 'is empty' : 'is not empty');
    if (isset($_POST) && isset($_SESSION))
      echo ", ";
    if (isset($_SESSION))
      echo "SESSION " . (count($_SESSION) == 0 ? 'is empty' : 'is not empty');
    echo "\r\n";
  }
  echo htmlspecialchars(ob_get_clean());
  echo "</pre>";
  
  echo "<h1>Code snippet</h1>";
  echo "<pre>";
  ob_start();
  echo "$file\r\n\r\n";
  echo $codeSnippet . "\r\n";
  echo htmlspecialchars(ob_get_clean());
  echo "</pre>";

  echo "<h1>Exception stack</h1>";
  echo "<pre>" . htmlspecialchars((string) $e) . "</pre>";
  
  echo "<h1>GET</h1>";
  echo "<pre>";
  ob_start();
  print_r($_GET);
  echo htmlspecialchars(ob_get_clean());
  echo "</pre>";
  
  echo "<h1>POST</h1>";
  echo "<pre>";
  ob_start();
  print_r($_POST);
  echo htmlspecialchars(ob_get_clean());
  echo "</pre>";
  
  if (isset($_SESSION)) {
    echo "<h1>SESSION</h1>";
    echo "<pre>";
    ob_start();
    print_r($_SESSION);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }
  
  if (isset($_COOKIE)) {
    echo "<h1>COOKIE</h1>";
    echo "<pre>";
    ob_start();
    print_r($_COOKIE);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }
  
  if (isset(safety_report_data()->request)) {
    echo "<h1>Request</h1>";
    echo "<pre>";
    ob_start();
    print_r(safety_report_data()->request);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }
  
  if (isset(safety_report_data()->parameters)) {
    echo "<h1>Parameters</h1>";
    echo "<pre>";
    ob_start();
    print_r(safety_report_data()->parameters);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }

  if (isset($_FILES)) {
    echo "<h1>FILES</h1>";
    echo "<pre>";
    ob_start();
    print_r($_FILES);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }

  echo "<h1>SERVER</h1>";
  echo "<pre>";
  ob_start();
  print_r($_SERVER);
  echo htmlspecialchars(ob_get_clean());
  echo "</pre>";

  echo "<h1>Server info</h1>";
  echo "<pre>";
  ob_start();
  echo "Uname: " . php_uname() . "\r\n";
  if (function_exists('gethostname'))
    echo "Hostname: " . gethostname() . "\r\n";
  echo "PHP version: " . phpversion() . "\r\n";
  if (isset($baseCodePath))
    echo "Base code path: " . $baseCodePath . "\r\n";
  echo htmlspecialchars(ob_get_clean());
  echo "</pre>";

  $message = ob_get_clean();
  return $message;
}
/**/


//function generate_exception_report ($e, $type = null) {
function generate_exception_report ($e) {
  //echo jsonEncode($e);
  //echo serialize($e);
  //echo json_encode($e);
  //var_dump($e->__sleep());
  //exit;

  if (!session_id())
    session_start();
  
  $report = (object) array();
  
  //if (isset($type))
  //  $report->type = $type;
  
  $file = str_replace(array('\\', '/'), array('/', '/'), $e->getFile());
  if (isset($_SERVER['SCRIPT_FILENAME']))
    $baseCodePath = str_replace(array('\\', '/'), array('/', '/'), dirname($_SERVER['SCRIPT_FILENAME']));
    
  
  /*
  foreach ($e->getTrace() as $traceItem) {
    var_dump($traceItem);
    exit;
  }
  var_dump($baseCodePath);
  var_dump($file);
  exit;
  /**/

  //if (isset($baseCodePath) && substr($file, 0, strlen($baseCodePath)) == dirname($baseCodePath))
  //  $relativeFile = ltrim(substr($file, strlen($baseCodePath), '/\\'));
    
  $fileContent = file_get_contents($file);
  //$currentLine = $e->getLine() - 10 >= 0 ? $e->getLine() - 10 : 0;
  $codeSnippetBeginLine = $e->getLine() - 10 >= 0 ? $e->getLine() - 10 : 0;
  $codeSnippetLines = array_slice(explode("\n", $fileContent), $codeSnippetBeginLine, 20);
  /*
  foreach ($codeSnippetLines as $k => $codeSnippetLine) {
    $currentLine++;
    if (!isset($maxLineNumberLength))
      $maxLineNumberLength = strlen($currentLine) + 1;
    //$codeSnippetLines[$k] = ($currentLine == $e->getLine() ? str_repeat('*', $maxLineNumberLength) : str_pad($currentLine, $maxLineNumberLength, '0', STR_PAD_LEFT))
    //  . " " . $codeSnippetLine;
    $codeSnippetLines[$k] = str_pad($currentLine, $maxLineNumberLength, '0', STR_PAD_LEFT) . " " . ($currentLine == $e->getLine() ? ">" : " ") . " | " . $codeSnippetLine;
  }
  /**/
  $codeSnippet = implode("\n", $codeSnippetLines);

  //ob_start();
  //echo "<!DOCTYPE html><html><head><title>" . htmlspecialchars($e->getMessage()) . "</title></head><body>";
  
  //$report->time = date('Y-m-d H:i:s') . " (" . date('r') . ")";
  $report->time = microtime(true);
  
  $report->serverInformation = (object) array();
  $report->serverInformation->uname = php_uname();
  if (function_exists('gethostname'))
    $report->serverInformation->hostname = gethostname();
  $report->serverInformation->phpVersion = phpversion();

  $report->request = (object) array();
  if (isset($_SERVER['REQUEST_METHOD']))
    $report->request->method = strtolower($_SERVER['REQUEST_METHOD']);
  if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI']))
    $report->request->url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
  if (isset($_SERVER['HTTP_REFERER']))
    $report->request->referer = $_SERVER['HTTP_REFERER'];
  if (isset($_SERVER['requestId']))
    $report->request->id = $_SERVER['requestId'];
  if (session_id())
    $report->request->session = session_id();
//exit;
  
  //$report->exception = exception_to_stdclass($e);
  $report->exception = exception_to_stdclass($e);

  
  $code = $report->exception;
  foreach (array_merge(array($report->exception), $report->exception->trace) as $traceItem) {
    //$withinBasePath = isset($report->code) && isset($report->code->basePath) && substr($traceItem->file, 0, strlen($report->code->basePath)) == $report->code->basePath;
    $withinBasePath = isset($baseCodePath) && substr($traceItem->file, 0, strlen($baseCodePath)) == $baseCodePath;
    if ($withinBasePath) {
      //var_dump("hahah");
      $code = $traceItem;
      break;
    }
  }

  $report->code = (object) array();
  if (isset($baseCodePath))
    $report->code->basePath = $baseCodePath;
  //$report->code->file = $report->exception->file;
  $report->code->file = $code->file;
  //$report->code->line = $report->exception->line;
  $report->code->line = $code->line;
  //$report->code->snippetBeginLine = $codeSnippetBeginLine;
  //$report->code->snippet = $codeSnippet;
  //$report->code->snippet = $report->exception->snippet;
  $report->code->snippet = $code->snippet;
  
  //var_dump($code->snippet);exit;
  //$report->code->snippet = extract_code_snippet($file, $traceItem->line);

  
  $report->rawData = (object) array();
  
  if (isset($_GET))
    $report->rawData->get = $_GET;
  if (isset($_POST))
    $report->rawData->post = $_POST;
  if (isset($_SESSION))
    $report->rawData->session = $_SESSION;
  if (isset($_COOKIE))
    $report->rawData->cookie = $_COOKIE;
  if (isset($_SERVER))
    $report->rawData->server = $_SERVER;
  if (isset($_FILES))
    $report->rawData->files = $_FILES;

  $report->additional = (object) array();
  foreach (safety_report_data() as $name => $value)
    $report->additional->$name = $value;

  //return json_decode(json_encode($report));
  //return json_encode($report);
  return $report;
  
}



//function _send_exception_report ($emails, $subject, $message) {
function _send_exception_report ($report) {

  if (is_object($report) && $report instanceof Exception)
    $report = generate_exception_report($report);
  /*
  $headers = array(
    "MIME-Version" => "1.0",
    "Content-type" => "text/html; charset=utf-8",
    "From" => "mailer@internet-inovacije.com",
  );
  send_mail($emails, $subject, $message, $headers);
  /**/

  if (_safety_report_data::$_configuration['report_url']) {
    $postData = 'data=' . urlencode(json_encode($report));

    $curl = curl_init(_safety_report_data::$_configuration['report_url']);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, 100);

    $response = curl_exec($curl);
    // assertTrue($response !== false);
    //echo microtime(true) - $t;
    //var_dump($response);
    //echo "OK";
    //exit;
  }
  //assertTrue(isset(report_url));

/*
$t = microtime(true);
$url = 'http://ipv4.localhost/issues/report/';
$myvars = 'data=' . urlencode(json_encode($report));

$ch = curl_init( $url );
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_POSTFIELDS, $myvars);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($curl, CURLOPT_HEADER, 0);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

curl_setopt($curl, CURLOPT_TIMEOUT, 0);
curl_setopt($curl, CURLOPT_TIMEOUT_MS, 100);

$t = microtime(true);
$response = curl_exec($curl );
echo microtime(true) - $t;
var_dump($response);
echo "OK";
  exit;
  //file_put_contents("http://localhost/issues/report/", json_encode($report));
  /**/
}

function exception_to_stdclass ($exception) {
  $rawException = (object) array();
  $rawException->{'class'} = get_class($exception);
  if (isset($exception->caught))
    $rawException->caught = $exception->caught;
  $rawException->message = $exception->getMessage();
  $rawException->code = $exception->getCode();
  $rawException->file = str_replace(array('\\', '/'), array('/', '/'), $exception->getFile());
  $rawException->line = $exception->getLine();
  $rawException->snippet = extract_code_snippet($rawException->file, $rawException->line);
  if (isset($exception->types))
    $rawException->types = $exception->types;
  $rawException->trace = $exception->getTrace();
  foreach ($rawException->trace as $traceIndex => $traceItem) {
    //$traceItem['functionName'] = $traceItem['function'];
    //var_dump($traceItem['function']);
    $traceItem = (object) $traceItem;
    //var_dump($traceItem->{'function'});
    //exit;
    
    $args = array();
    foreach ($traceItem->args as $argumentIndex => $argument) {
      $args[$argumentIndex] = (object) array('type' => gettype($argument));
      if (is_object($argument))
        $args[$argumentIndex]->{'class'} = get_class($argument);
      else if (is_array($argument))
        $args[$argumentIndex]->length = count($argument);
      else {
        $args[$argumentIndex]->length = strlen($argument);
        $args[$argumentIndex]->value = substr($argument, 0, 300);
      }
    }
    unset($traceItem->args);
    $traceItem->arguments = $args;
    
    $traceItem->file = str_replace(array('\\', '/'), array('/', '/'), $traceItem->file);
    //var_dump($traceItem->file);exit;

    //$file = str_replace(array('\\', '/'), array('/', '/'), $exception->getFile());
    
    $traceItem->snippet = extract_code_snippet($traceItem->file, $traceItem->line);
    
    /*
    $traceItem->snippet = (object) array();
    
    $fileContent = file_get_contents($traceItem->file);
    //$currentLine = $e->getLine() - 10 >= 0 ? $e->getLine() - 10 : 0;
    $traceItem->snippet->beginLine = $traceItem->line - 10 >= 0 ? $traceItem->line - 10 : 0;
    //$codeSnippetLines = array_slice(explode("\n", $fileContent), $traceItem->snippet->beginLine, 20);
    $traceItem->snippet->content = implode("\n", array_slice(explode("\n", $fileContent), $traceItem->snippet->beginLine, 20));
  /*
  foreach ($codeSnippetLines as $k => $codeSnippetLine) {
    $currentLine++;
    if (!isset($maxLineNumberLength))
      $maxLineNumberLength = strlen($currentLine) + 1;
    //$codeSnippetLines[$k] = ($currentLine == $e->getLine() ? str_repeat('*', $maxLineNumberLength) : str_pad($currentLine, $maxLineNumberLength, '0', STR_PAD_LEFT))
    //  . " " . $codeSnippetLine;
    $codeSnippetLines[$k] = str_pad($currentLine, $maxLineNumberLength, '0', STR_PAD_LEFT) . " " . ($currentLine == $e->getLine() ? ">" : " ") . " | " . $codeSnippetLine;
  }
  /**/
  
    //$traceItem->snippet = (object) array();
    //$traceItem->snippet->content = implode("\n", $codeSnippetLines);
    //$traceItem->snippet->beginLine = $codeSnippetBeginLine;
    //$codeSnippet = implode("\n", $codeSnippetLines);

    //$report->code->snippetBeginLine = $codeSnippetBeginLine;
    //$report->code->snippet = $codeSnippet;

    
    
    $rawException->trace[$traceIndex] = $traceItem;
  }
  //var_dump($rawException->trace);
  //exit;
  if ($exception->getPrevious() !== null)
    $rawException->previous = exception_to_stdclass($exception->getPrevious());
  return $rawException;
}

function extract_code_snippet ($file, $line) {
  $snippet = (object) array();
  $snippet->beginLine = $line - 10 >= 0 ? $line - 10 : 0;
  $snippet->content = implode("\n", array_slice(explode("\n", file_get_contents($file)), $snippet->beginLine, 20));
  return $snippet;
}



function safety_report_data () {
  return _safety_report_data::instance();
}

class _safety_report_data {

  public static $_variables;
  public static $_configuration;
  private static $_instance;
  
  static function instance () {
    if (!isset(self::$_instance))
      self::$_instance = new self();
    return self::$_instance;
  }
  
  public function touch ($name, $value = null) {
    if (!isset($this->$name))
      $this->$name = $value;
  }
  
}


/*

function ensure_safety_mode ($mode) {
  if (!isset(_safety_report_data::$_variables))
    _safety_report_data::$_variables = (object) array();
  if (!isset(_safety_report_data::$_variables->safety_mode_stack))
    _safety_report_data::$_variables->safety_mode_stack = array();
  _safety_report_data::$_variables->safety_mode_stack[] = _safety_report_data::$_configuration['mode'];
  _safety_report_data::$_configuration['mode'] = $mode;
}

function restore_safety_mode () {
  _safety_report_data::$_configuration['mode'] = end(_safety_report_data::$_variables->safety_mode_stack);
  array_pop(_safety_report_data::$_variables->safety_mode_stack);
}
/**/



if (_safety_report_data::$_configuration['enabled']) {
  set_exception_handler('uncaught_exception_handler');
  set_error_handler('exception_error_handler');
  register_shutdown_function('shutdown_error_handler');
}


